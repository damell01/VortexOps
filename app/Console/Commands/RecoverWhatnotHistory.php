<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Unified historical recovery for Whatnot.
 *
 * This command intentionally uses App\Services\WhatnotScraper only. That service
 * launches scripts/whatnot-scraper.cjs, so historical recovery gets the same
 * Xvfb/CDP browser launch, multi-channel role switching and fail-closed channel
 * verification as the working live scraper. It does not invoke the older
 * scripts/whatnot-production-sync.cjs path used by whatnot:backfill-history.
 *
 * Recovery has two phases for every enabled channel:
 *   1. discover/import the channel's historical shows and analytics;
 *   2. fill missing shipments in small, paced batches.
 *
 * A successful shipment page with zero rows still counts as checked. Shows can
 * legitimately have no shipments, so last_shipments_synced_at is stamped only
 * after that show key is positively returned by the scraper.
 */
class RecoverWhatnotHistory extends Command
{
    protected $signature = 'whatnot:recover-history
                            {--limit=500 : Maximum historical shows to discover per channel}
                            {--batch=20 : Shipment shows per browser batch}
                            {--sleep=20 : Seconds to pause between shipment batches}
                            {--max-batches=0 : Stop after this many shipment batches per channel; 0 means no cap}
                            {--channel= : Only recover one channel name or Whatnot username}
                            {--dry-run : Scan Whatnot and compare remote show ids with VortexOps without changing data}
                            {--verify : Verify one channel and one missing shipment before a full recovery}
                            {--debug : Stream scraper diagnostics}';

    protected $description = 'Discover missing historical Whatnot shows and recover their analytics and shipments using the main channel-safe scraper';

    public function handle(WhatnotScraper $scraper): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $batchSize = max(1, min(50, (int) $this->option('batch')));
        $sleep = max(0, (int) $this->option('sleep'));
        $maxBatches = max(0, (int) $this->option('max-batches'));
        $debug = (bool) $this->option('debug');

        $channels = $this->channels();
        if ($channels->isEmpty()) {
            $this->error('No enabled active Whatnot channels matched this recovery run.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Unified Whatnot historical recovery');
        $this->line('  Main scraper: scripts/whatnot-scraper.cjs');
        $this->line('  Channels: ' . $channels->pluck('name')->join(', '));
        $this->line('  Discovery limit: ' . $limit . ' per channel');
        $this->newLine();

        $this->printSnapshot('Before recovery', $channels);

        if ($this->option('dry-run')) {
            return $this->dryRun($scraper, $channels, $limit, $debug);
        }

        if ($this->option('verify')) {
            return $this->verify($scraper, $channels->first(), $debug);
        }

        $failedChannels = [];

        foreach ($channels as $position => $channel) {
            $this->newLine();
            $this->line(sprintf(
                '<fg=cyan>[%d/%d] %s</> <fg=gray>(@%s)</>',
                $position + 1,
                $channels->count(),
                $channel->name,
                $channel->whatnot_username,
            ));

            try {
                $this->line('  <fg=gray>Phase 1: discover missing shows + refresh analytics</>');

                // seedLiveId:'' deliberately means "do not trust the local index as
                // the starting point". The scraper first discovers what Whatnot
                // currently exposes for this verified channel, then walks history.
                // That is what lets this phase find an entirely missing show rather
                // than only enrich rows we already know about.
                $result = $scraper->importShows(
                    channel: $channel,
                    limit: $limit,
                    debug: $debug,
                    withOrders: false,
                    onProgress: function (string $line): void {
                        $this->line('    ' . $line);
                    },
                    seedLiveId: '',
                );

                $this->line(sprintf(
                    '  <fg=green>Discovery complete:</> %d created, %d updated, %d skipped',
                    (int) ($result['created'] ?? 0),
                    (int) ($result['updated'] ?? 0),
                    (int) ($result['skipped'] ?? 0),
                ));

                $seen = (int) ($result['created'] ?? 0)
                    + (int) ($result['updated'] ?? 0)
                    + (int) ($result['skipped'] ?? 0);
                if ($seen >= $limit) {
                    $this->warn("  Discovery reached the --limit={$limit} ceiling. Re-run with a larger --limit before calling the catalogue complete.");
                }

                $missingAnalytics = $this->pastShows($channel)->missingAnalytics()->count();
                $this->line("  Analytics still missing after discovery: {$missingAnalytics}");

                $this->line('  <fg=gray>Phase 2: recover missing shipments</>');
                $shipmentResult = $this->recoverShipments(
                    $scraper,
                    $channel,
                    $batchSize,
                    $sleep,
                    $maxBatches,
                    $debug,
                );

                $this->line(sprintf(
                    '  <fg=green>Shipment recovery:</> %d show(s) checked, %d shipment(s) created, %d updated, %d unresolved',
                    $shipmentResult['checked'],
                    $shipmentResult['created'],
                    $shipmentResult['updated'],
                    $shipmentResult['unresolved'],
                ));
            } catch (\Throwable $e) {
                $failedChannels[] = $channel->name;
                $this->error("  {$channel->name} stopped: {$e->getMessage()}");
                $this->line('  <fg=gray>No data from an unverified channel context is accepted. Continuing to the next enabled channel.</>');
            }
        }

        $this->newLine();
        $this->printSnapshot('After recovery', $channels);

        if ($failedChannels !== []) {
            $this->newLine();
            $this->error('Recovery finished with channel failures: ' . implode(', ', $failedChannels));
            return self::FAILURE;
        }

        $remainingAnalytics = $this->allPastShows($channels)->missingAnalytics()->count();
        $remainingShipments = $this->allPastShows($channels)->missingShipments()->count();

        $this->newLine();
        if ($remainingAnalytics === 0 && $remainingShipments === 0) {
            $this->info('Historical recovery complete: no known past show is missing analytics or an unchecked shipment sync.');
        } else {
            $this->warn("Recovery pass complete: {$remainingAnalytics} still missing analytics; {$remainingShipments} still missing shipments.");
            $this->line('  <fg=gray>Run this command again to continue. If discovery hit its limit, increase --limit first.</>');
        }

        return self::SUCCESS;
    }

    private function dryRun(WhatnotScraper $scraper, Collection $channels, int $limit, bool $debug): int
    {
        $this->newLine();
        $this->comment('Dry run: scanning each verified channel without writing to VortexOps.');

        $failed = false;
        $rows = [];

        foreach ($channels as $channel) {
            try {
                $remote = $scraper->fetchShows(
                    limit: $limit,
                    debug: $debug,
                    channelUsername: $channel->whatnot_username,
                    seedLiveId: '',
                );

                $remoteIds = collect($this->showRows($remote))
                    ->map(fn (array $row) => $this->liveId($row))
                    ->filter()
                    ->unique()
                    ->values();

                $localIds = $this->pastShows($channel)
                    ->pluck('whatnot_show_id')
                    ->filter()
                    ->map(fn ($id) => strtolower((string) $id))
                    ->flip();

                $missingIds = $remoteIds
                    ->filter(fn ($id) => ! $localIds->has(strtolower((string) $id)))
                    ->values();

                $rows[] = [
                    $channel->name,
                    $remoteIds->count(),
                    $missingIds->count(),
                    $this->pastShows($channel)->missingAnalytics()->count(),
                    $this->pastShows($channel)->missingShipments()->count(),
                ];

                if ($remoteIds->count() >= $limit) {
                    $this->warn("{$channel->name}: remote scan hit --limit={$limit}; increase the limit before treating missing-show count as exhaustive.");
                }

                if ($missingIds->isNotEmpty()) {
                    $sample = $missingIds->take(10)->join(', ');
                    $this->warn("{$channel->name}: missing show ids detected: {$sample}" . ($missingIds->count() > 10 ? ' …' : ''));
                }
            } catch (\Throwable $e) {
                $failed = true;
                $rows[] = [$channel->name, 'FAILED', '—', '—', '—'];
                $this->error("{$channel->name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(
            ['Channel', 'Remote shows seen', 'Missing locally', 'Missing analytics', 'Missing shipments'],
            $rows,
        );

        $this->comment('Dry run made no database changes.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function verify(WhatnotScraper $scraper, WhatnotChannel $channel, bool $debug): int
    {
        $this->newLine();
        $this->line("<fg=cyan>Verify:</> {$channel->name} (@{$channel->whatnot_username})");
        $beforeShows = $this->pastShows($channel)->count();

        try {
            $result = $scraper->importShows(
                channel: $channel,
                limit: 1,
                debug: true,
                withOrders: false,
                onProgress: fn (string $line) => $this->line('  ' . $line),
                seedLiveId: '',
            );
        } catch (\Throwable $e) {
            $this->error('Discovery/analytics verification failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $afterShows = $this->pastShows($channel)->count();
        $this->line(sprintf(
            '  Show discovery/analytics path passed: %d created, %d updated, local shows %d → %d',
            (int) ($result['created'] ?? 0),
            (int) ($result['updated'] ?? 0),
            $beforeShows,
            $afterShows,
        ));

        $show = $this->pastShows($channel)
            ->missingShipments()
            ->orderByDesc('show_date')
            ->first();

        if (! $show) {
            $this->info('  No missing shipment sync exists on this channel. Main scraper verification passed.');
            return self::SUCCESS;
        }

        $liveId = $this->showLiveId($show);
        if (! $liveId) {
            $this->error("Shipment verification show #{$show->id} has no usable Whatnot livestream id.");
            return self::FAILURE;
        }

        try {
            $map = $scraper->fetchShipmentsForShows(
                [['live_id' => $liveId, 'show_key' => $show->id]],
                $channel->whatnot_username,
                true,
                fn (string $line) => $this->line('  ' . $line),
            );
        } catch (\Throwable $e) {
            $this->error('Shipment verification failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (! array_key_exists($show->id, $map) && ! array_key_exists((string) $show->id, $map)) {
            $this->error("Shipment scraper completed but did not return show key {$show->id}; nothing was stamped as verified.");
            return self::FAILURE;
        }

        $rows = $map[$show->id] ?? $map[(string) $show->id] ?? [];
        $this->persistShipmentResult($scraper, $show, is_array($rows) ? $rows : []);

        $this->info(sprintf(
            '  Shipment path passed on "%s": %d row(s) returned. Safe to run the full recovery.',
            $show->title ?: $show->whatnot_show_id,
            is_array($rows) ? count($rows) : 0,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{checked:int,created:int,updated:int,unresolved:int,batches:int}
     */
    private function recoverShipments(
        WhatnotScraper $scraper,
        WhatnotChannel $channel,
        int $batchSize,
        int $sleep,
        int $maxBatches,
        bool $debug,
    ): array {
        $totals = ['checked' => 0, 'created' => 0, 'updated' => 0, 'unresolved' => 0, 'batches' => 0];

        while (true) {
            if ($maxBatches > 0 && $totals['batches'] >= $maxBatches) {
                break;
            }

            $shows = $this->pastShows($channel)
                ->missingShipments()
                ->orderByDesc('show_date')
                ->limit($batchSize)
                ->get();

            if ($shows->isEmpty()) {
                break;
            }

            $sources = [];
            $byId = [];
            foreach ($shows as $show) {
                $liveId = $this->showLiveId($show);
                if (! $liveId) {
                    $totals['unresolved']++;
                    $this->warn("    Show #{$show->id} has no usable livestream id; skipped.");
                    continue;
                }
                $sources[] = ['live_id' => $liveId, 'show_key' => $show->id];
                $byId[$show->id] = $show;
            }

            if ($sources === []) {
                break;
            }

            $totals['batches']++;
            $this->line(sprintf(
                '    Batch %d: %d show(s), %d missing shipments before batch',
                $totals['batches'],
                count($sources),
                $this->pastShows($channel)->missingShipments()->count(),
            ));

            $map = $scraper->fetchShipmentsForShows(
                $sources,
                $channel->whatnot_username,
                $debug,
                $debug ? fn (string $line) => $this->line('      ' . $line) : null,
            );

            $progress = 0;
            foreach ($byId as $showId => $show) {
                $hasKey = array_key_exists($showId, $map) || array_key_exists((string) $showId, $map);
                if (! $hasKey) {
                    $totals['unresolved']++;
                    continue;
                }

                $rows = $map[$showId] ?? $map[(string) $showId] ?? [];
                $persisted = $this->persistShipmentResult($scraper, $show, is_array($rows) ? $rows : []);
                $totals['checked']++;
                $totals['created'] += $persisted['created'];
                $totals['updated'] += $persisted['updated'];
                $progress++;
            }

            if ($progress === 0) {
                throw new \RuntimeException('Shipment batch returned no matching show keys. Stopping instead of looping on the same rows.');
            }

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        return $totals;
    }

    /** @return array{created:int,updated:int} */
    private function persistShipmentResult(WhatnotScraper $scraper, Show $show, array $rows): array
    {
        // The shipments scrape can also carry weight/carrier/status for existing
        // order rows. Mirror WhatnotScraper::refreshShipmentsForShows so those
        // fields are not discarded while creating Shipment records.
        $orderResult = $scraper->persistShowOrders($show, $rows);
        $shipmentResult = $scraper->persistShipments($show, $rows);

        $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
        $show->raw_import_payload = array_merge($raw, [
            '_shipments_synced_at' => now()->toIso8601String(),
            '_shipment_row_count' => count($rows),
            '_historical_recovery' => true,
        ]);
        $show->setAttribute('last_shipments_synced_at', now());
        $show->last_synced_at = now();
        $show->save();

        ShowIngestionLog::create([
            'show_id' => $show->id,
            'whatnot_channel_id' => $show->whatnot_channel_id,
            'source' => 'whatnot_recent_refresh',
            'status' => 'success',
            'raw_payload' => [
                'live_id' => $show->whatnot_show_id,
                'shipment_count' => count($rows),
                '_historical_recovery' => true,
                '_channel_id' => $show->whatnot_channel_id,
            ],
        ]);

        return [
            'created' => (int) ($shipmentResult['created'] ?? 0),
            'updated' => (int) ($shipmentResult['updated'] ?? 0) + (int) ($orderResult['updated'] ?? 0),
        ];
    }

    private function channels(): Collection
    {
        $query = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id');

        $requested = trim((string) $this->option('channel'));
        if ($requested !== '') {
            $needle = strtolower(ltrim($requested, '@'));
            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) = ?', [$needle])
                    ->orWhereRaw('LOWER(whatnot_username) = ?', [$needle]);
            });
        }

        return $query->get();
    }

    private function pastShows(WhatnotChannel $channel)
    {
        return Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->whereNotNull('whatnot_show_id')
            ->whereDate('show_date', '<=', today());
    }

    private function allPastShows(Collection $channels)
    {
        return Show::query()
            ->whereIn('whatnot_channel_id', $channels->pluck('id')->all())
            ->whereNotNull('whatnot_show_id')
            ->whereDate('show_date', '<=', today());
    }

    private function printSnapshot(string $label, Collection $channels): void
    {
        $rows = [];
        foreach ($channels as $channel) {
            $rows[] = [
                $channel->name,
                $this->pastShows($channel)->count(),
                $this->pastShows($channel)->missingAnalytics()->count(),
                $this->pastShows($channel)->missingShipments()->count(),
            ];
        }

        $this->line("<fg=yellow>{$label}</>");
        $this->table(['Channel', 'Past shows', 'Missing analytics', 'Missing shipments'], $rows);
    }

    private function showLiveId(Show $show): ?string
    {
        $id = trim((string) $show->whatnot_show_id);
        if ($this->isUuid($id)) {
            return strtolower($id);
        }

        $url = (string) $show->detail_url;
        if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $url, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function liveId(array $row): ?string
    {
        foreach (['whatnot_live_id', 'live_id', 'whatnot_show_id', 'id'] as $key) {
            $candidate = trim((string) ($row[$key] ?? ''));
            if ($this->isUuid($candidate)) {
                return strtolower($candidate);
            }
        }

        foreach (['detail_url', 'open_url', 'url'] as $key) {
            $url = (string) ($row[$key] ?? '');
            if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $url, $m)) {
                return strtolower($m[1]);
            }
        }

        return null;
    }

    /** @return array<int,array<string,mixed>> */
    private function showRows(array $data): array
    {
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        foreach (['shows', 'results', 'data'] as $key) {
            if (isset($data[$key]) && is_array($data[$key]) && array_is_list($data[$key])) {
                return array_values(array_filter($data[$key], 'is_array'));
            }
        }

        $rows = [];
        foreach (['current', 'upcoming', 'past'] as $key) {
            foreach (($data[$key] ?? []) as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }
}

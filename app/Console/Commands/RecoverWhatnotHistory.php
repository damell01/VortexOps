<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RecoverWhatnotHistory extends Command
{
    protected $signature = 'whatnot:recover-history
                            {--limit=500 : Maximum historical shows to discover per channel}
                            {--channel= : Only recover one channel name or Whatnot username}
                            {--dry-run : Scan Whatnot and compare remote show ids with VortexOps without changing data}
                            {--verify : Verify one channel and one missing shipment before a full recovery}
                            {--debug : Stream scraper diagnostics}';

    protected $description = 'Discover missing historical Whatnot shows and recover analytics and shipments with channel-safe scraping';

    public function handle(WhatnotScraper $scraper): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $debug = (bool) $this->option('debug');
        $channels = $this->channels();

        if ($channels->isEmpty()) {
            $this->error('No enabled active Whatnot channels matched this recovery run.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Unified Whatnot historical recovery');
        $this->line('  Channels: ' . $channels->pluck('name')->join(', '));
        $this->line('  Discovery limit: ' . $limit . ' per channel');
        $this->line('  Shipments: all missing shows for each channel in one scraper run');
        $this->newLine();
        $this->printSnapshot('Before recovery', $channels);

        if ($this->option('dry-run')) {
            return $this->dryRun($scraper, $channels, $limit, $debug);
        }

        if ($this->option('verify')) {
            return $this->verify($scraper, $channels->first(), $debug);
        }

        $failed = [];

        foreach ($channels as $position => $channel) {
            $this->newLine();
            $this->line(sprintf(
                '<fg=cyan>[%d/%d] %s</> <fg=gray>(@%s)</>',
                $position + 1,
                $channels->count(),
                $channel->name,
                $channel->whatnot_username,
            ));

            $seed = $this->seedForChannel($channel);

            $this->line('  <fg=gray>Phase 1: discover missing shows + refresh analytics</>');
            if ($seed) {
                $this->line("    Analytics seed: {$seed}");
                try {
                    $result = $scraper->importShows(
                        channel: $channel,
                        limit: $limit,
                        debug: $debug,
                        withOrders: false,
                        onProgress: fn (string $line) => $this->line('    ' . $line),
                        seedLiveId: $seed,
                    );

                    $this->line(sprintf(
                        '  <fg=green>Discovery complete:</> %d created, %d updated, %d unchanged/invalid skipped',
                        (int) ($result['created'] ?? 0),
                        (int) ($result['updated'] ?? 0),
                        (int) ($result['skipped'] ?? 0),
                    ));

                    $seen = (int) ($result['created'] ?? 0)
                        + (int) ($result['updated'] ?? 0)
                        + (int) ($result['skipped'] ?? 0);
                    if ($seen >= $limit) {
                        $this->warn("  Discovery reached --limit={$limit}. Increase it before treating discovery as exhaustive.");
                    }
                } catch (\Throwable $e) {
                    $failed[] = $channel->name . ' analytics';
                    $this->error("  Analytics failed: {$e->getMessage()}");
                    $this->warn('  Continuing to shipment recovery using the show UUIDs already stored in VortexOps.');
                }
            } else {
                $this->warn('    No known show UUID exists for this channel, so analytics discovery cannot be seeded.');
                $this->line('    Continuing to shipment recovery from any existing DB shows.');
            }

            $missingAnalytics = $this->pastShows($channel)->missingAnalytics()->count();
            $this->line("  Analytics still missing: {$missingAnalytics}");

            $this->line('  <fg=gray>Phase 2: recover all missing shipments in one run</>');
            try {
                $shipmentResult = $this->recoverShipments($scraper, $channel, $debug);
                $this->line(sprintf(
                    '  <fg=green>Shipment recovery:</> %d show(s) checked, %d shipment(s) created, %d updated, %d unresolved',
                    $shipmentResult['checked'],
                    $shipmentResult['created'],
                    $shipmentResult['updated'],
                    $shipmentResult['unresolved'],
                ));
            } catch (\Throwable $e) {
                $failed[] = $channel->name . ' shipments';
                $this->error("  Shipment recovery failed: {$e->getMessage()}");
                $this->line('  <fg=gray>No data from an unverified channel context is accepted. Continuing to the next enabled channel.</>');
            }
        }

        $this->newLine();
        $this->printSnapshot('After recovery', $channels);

        $remainingAnalytics = $this->allPastShows($channels)->missingAnalytics()->count();
        $remainingShipments = $this->allPastShows($channels)->missingShipments()->count();

        $this->newLine();
        if ($failed !== []) {
            $this->error('Recovery finished with failures: ' . implode(', ', array_unique($failed)));
            return self::FAILURE;
        }

        if ($remainingAnalytics === 0 && $remainingShipments === 0) {
            $this->info('Historical recovery complete: no known past show is missing analytics or an unchecked shipment sync.');
        } else {
            $this->warn("Recovery pass complete: {$remainingAnalytics} still missing analytics; {$remainingShipments} still missing shipments.");
        }

        return self::SUCCESS;
    }

    private function dryRun(WhatnotScraper $scraper, Collection $channels, int $limit, bool $debug): int
    {
        $rows = [];
        $failed = false;

        foreach ($channels as $channel) {
            $seed = $this->seedForChannel($channel);
            if (! $seed) {
                $rows[] = [$channel->name, 'NO SEED', '—', $this->pastShows($channel)->missingAnalytics()->count(), $this->pastShows($channel)->missingShipments()->count()];
                continue;
            }

            try {
                $remote = $scraper->fetchShows(
                    limit: $limit,
                    debug: $debug,
                    channelUsername: $channel->whatnot_username,
                    seedLiveId: $seed,
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
            } catch (\Throwable $e) {
                $failed = true;
                $rows[] = [$channel->name, 'FAILED', '—', '—', '—'];
                $this->error("{$channel->name}: {$e->getMessage()}");
            }
        }

        $this->table(['Channel', 'Remote shows seen', 'Missing locally', 'Missing analytics', 'Missing shipments'], $rows);
        $this->comment('Dry run made no database changes.');
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function verify(WhatnotScraper $scraper, WhatnotChannel $channel, bool $debug): int
    {
        $this->line("<fg=cyan>Verify:</> {$channel->name} (@{$channel->whatnot_username})");
        $seed = $this->seedForChannel($channel);

        if ($seed) {
            try {
                $scraper->importShows(
                    channel: $channel,
                    limit: 1,
                    debug: true,
                    withOrders: false,
                    onProgress: fn (string $line) => $this->line('  ' . $line),
                    seedLiveId: $seed,
                );
                $this->info('  Analytics path passed.');
            } catch (\Throwable $e) {
                $this->error('  Analytics verification failed: ' . $e->getMessage());
            }
        } else {
            $this->warn('  Analytics verification skipped: no known show UUID for this channel.');
        }

        $show = $this->pastShows($channel)->missingShipments()->orderByDesc('show_date')->get()->first(fn (Show $show) => (bool) $this->showLiveId($show));
        if (! $show) {
            $this->info('  No missing shipment sync with a usable UUID exists on this channel.');
            return $seed ? self::SUCCESS : self::FAILURE;
        }

        try {
            $map = $scraper->fetchShipmentsForShows(
                [['live_id' => $this->showLiveId($show), 'show_key' => $show->id]],
                $channel->whatnot_username,
                $debug,
                fn (string $line) => $this->line('  ' . $line),
            );
        } catch (\Throwable $e) {
            $this->error('  Shipment verification failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (! array_key_exists($show->id, $map) && ! array_key_exists((string) $show->id, $map)) {
            $this->error("  Shipment scraper did not return show key {$show->id}.");
            return self::FAILURE;
        }

        $rows = $map[$show->id] ?? $map[(string) $show->id] ?? [];
        $this->persistShipmentResult($scraper, $show, is_array($rows) ? $rows : []);
        $this->info('  Shipment path passed.');
        return self::SUCCESS;
    }

    private function recoverShipments(WhatnotScraper $scraper, WhatnotChannel $channel, bool $debug): array
    {
        $totals = ['checked' => 0, 'created' => 0, 'updated' => 0, 'unresolved' => 0];
        $shows = $this->pastShows($channel)->missingShipments()->orderByDesc('show_date')->get();

        if ($shows->isEmpty()) {
            $this->line('    No missing shipment syncs for this channel.');
            return $totals;
        }

        $sources = [];
        $byId = [];
        foreach ($shows as $show) {
            $liveId = $this->showLiveId($show);
            if (! $liveId) {
                $totals['unresolved']++;
                continue;
            }
            $sources[] = ['live_id' => $liveId, 'show_key' => $show->id];
            $byId[$show->id] = $show;
        }

        if ($sources === []) {
            $this->warn('    Missing shipment shows exist, but none has a usable Whatnot UUID.');
            return $totals;
        }

        $this->line(sprintf('    Shipments: scraping %d show(s) in one run', count($sources)));
        $map = $scraper->fetchShipmentsForShows(
            $sources,
            $channel->whatnot_username,
            $debug,
            $debug ? fn (string $line) => $this->line('      ' . $line) : null,
        );

        foreach ($byId as $showId => $show) {
            if (! array_key_exists($showId, $map) && ! array_key_exists((string) $showId, $map)) {
                $totals['unresolved']++;
                continue;
            }

            $rows = $map[$showId] ?? $map[(string) $showId] ?? [];
            $persisted = $this->persistShipmentResult($scraper, $show, is_array($rows) ? $rows : []);
            $totals['checked']++;
            $totals['created'] += $persisted['created'];
            $totals['updated'] += $persisted['updated'];
        }

        return $totals;
    }

    private function persistShipmentResult(WhatnotScraper $scraper, Show $show, array $rows): array
    {
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
                'live_id' => $this->showLiveId($show),
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

    private function seedForChannel(WhatnotChannel $channel): ?string
    {
        $shows = Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->orderByDesc('show_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['whatnot_show_id', 'detail_url']);

        foreach ($shows as $show) {
            $id = trim((string) $show->whatnot_show_id);
            if ($this->isUuid($id)) return strtolower($id);

            if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', (string) $show->detail_url, $m)) {
                return strtolower($m[1]);
            }
        }

        return $scraperSeed = app(WhatnotScraper::class)->seedLiveIdFor($channel);
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
        if ($this->isUuid($id)) return strtolower($id);

        if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', (string) $show->detail_url, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function liveId(array $row): ?string
    {
        foreach (['whatnot_live_id', 'live_id', 'whatnot_show_id', 'id'] as $key) {
            $candidate = trim((string) ($row[$key] ?? ''));
            if ($this->isUuid($candidate)) return strtolower($candidate);
        }

        foreach (['detail_url', 'open_url', 'url'] as $key) {
            if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', (string) ($row[$key] ?? ''), $m)) {
                return strtolower($m[1]);
            }
        }

        return null;
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    private function showRows(array $payload): array
    {
        if (array_is_list($payload)) return array_values(array_filter($payload, 'is_array'));
        foreach (['shows', 'data', 'results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }
        return [];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Support\WhatnotPipelineLock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReconcileWhatnotShipments extends Command
{
    protected $signature = 'whatnot:reconcile-shipments
        {--days=3650 : Only reconcile shows this many days back}
        {--batch=4 : Number of shows per browser batch}
        {--channel= : Channel name or Whatnot username}
        {--limit=0 : Maximum shows per channel; 0 means all in range}
        {--skip-if-busy : Exit cleanly if another Whatnot pipeline is running}
        {--no-dedupe : Do not compact exact duplicate tracking rows afterward}';

    protected $description = 'Re-scrape historical Whatnot shipment pages and repair VortexOps shipment data from Seller Hub';

    public function handle(WhatnotScraper $scraper): int
    {
        $wait = $this->option('skip-if-busy') ? 0 : 14400;
        $lock = WhatnotPipelineLock::acquire('Full shipment reconciliation', $wait);

        if (! $lock) {
            $this->warn(WhatnotPipelineLock::busyMessage());
            return $this->option('skip-if-busy') ? self::SUCCESS : self::FAILURE;
        }

        try {
            $days = max(1, (int) $this->option('days'));
            $batchSize = max(1, min(10, (int) $this->option('batch')));
            $limit = max(0, (int) $this->option('limit'));
            $channelFilter = trim((string) $this->option('channel'));

            $channels = WhatnotChannel::query()
                ->where('include_in_import', true)
                ->where('status', 'active')
                ->when($channelFilter !== '', function ($query) use ($channelFilter) {
                    $needle = ltrim($channelFilter, '@');
                    $query->where(function ($q) use ($channelFilter, $needle) {
                        $q->where('name', $channelFilter)
                            ->orWhere('whatnot_username', $channelFilter)
                            ->orWhere('whatnot_username', $needle)
                            ->orWhere('whatnot_username', '@' . $needle);
                    });
                })
                ->orderBy('id')
                ->get();

            if ($channels->isEmpty()) {
                $this->error('No matching active Whatnot channels found.');
                return self::FAILURE;
            }

            $grandShows = 0;
            $grandCreated = 0;
            $grandUpdated = 0;
            $grandSkipped = 0;

            foreach ($channels as $channel) {
                $query = Show::query()
                    ->where('whatnot_channel_id', $channel->id)
                    ->whereNotNull('detail_url')
                    ->whereBetween('show_date', [now()->subDays($days)->startOfDay(), now()->endOfDay()])
                    ->orderBy('show_date')
                    ->orderBy('id');

                if ($limit > 0) {
                    $query->limit($limit);
                }

                $shows = $query->get();
                $this->newLine();
                $this->info("{$channel->name}: {$shows->count()} show(s) queued for shipment reconciliation.");

                if ($shows->isEmpty()) {
                    continue;
                }

                $processed = 0;
                foreach ($shows->chunk($batchSize) as $chunk) {
                    /** @var Collection<int, Show> $chunk */
                    $first = $processed + 1;
                    $last = $processed + $chunk->count();
                    $this->line("  Shows {$first}-{$last}/{$shows->count()}...");

                    $before = Shipment::query()
                        ->whereIn('show_id', $chunk->pluck('id'))
                        ->count();

                    $result = $scraper->refreshShipmentsForShows($chunk, $channel->whatnot_username);

                    $after = Shipment::query()
                        ->whereIn('show_id', $chunk->pluck('id'))
                        ->count();

                    $created = max(0, $after - $before);
                    $updated = max(0, (int) ($result['updated'] ?? 0) - $created);
                    $skipped = (int) ($result['skipped_shows'] ?? 0);

                    $grandCreated += $created;
                    $grandUpdated += $updated;
                    $grandSkipped += $skipped;
                    $processed += $chunk->count();

                    $this->line("    complete: +{$created} shipment rows, {$updated} linked/updated, {$skipped} skipped show(s)");
                }

                $grandShows += $shows->count();
            }

            if (! $this->option('no-dedupe')) {
                $removed = $this->dedupeShipments();
                $this->info("Removed {$removed} exact duplicate shipment row(s) by show + tracking number.");
            }

            $repaired = $this->normalizeAllShipments();
            $this->info("Normalized {$repaired} shipment row(s) from stored raw payloads.");

            $this->newLine();
            $this->info("Shipment reconciliation complete: {$grandShows} shows checked, {$grandCreated} rows created, {$grandUpdated} linked/updated, {$grandSkipped} shows skipped.");

            return self::SUCCESS;
        } finally {
            WhatnotPipelineLock::release($lock);
        }
    }

    private function normalizeAllShipments(): int
    {
        $changed = 0;

        Shipment::query()->orderBy('id')->chunkById(250, function ($rows) use (&$changed): void {
            foreach ($rows as $shipment) {
                $shipment->normalizeScrapedPayload();
                if ($shipment->isDirty()) {
                    $shipment->saveQuietly();
                    $shipment->reconcileBundledOrders();
                    $changed++;
                }
            }
        });

        return $changed;
    }

    private function dedupeShipments(): int
    {
        $removed = 0;

        $duplicates = Shipment::query()
            ->selectRaw('show_id, tracking_number, COUNT(*) as row_count')
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '<>', '')
            ->groupBy('show_id', 'tracking_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            $rows = Shipment::query()
                ->where('show_id', $group->show_id)
                ->where('tracking_number', $group->tracking_number)
                ->orderByDesc('id')
                ->get();

            $keeper = $rows->shift();
            if (! $keeper) {
                continue;
            }

            foreach ($rows as $duplicate) {
                foreach ([
                    'buyer_username', 'created_at_whatnot', 'item_count', 'shipping_cost',
                    'weight_oz', 'dimensions_json', 'status', 'carrier', 'raw_payload',
                ] as $field) {
                    if (($keeper->{$field} === null || $keeper->{$field} === '' || $keeper->{$field} === [])
                        && $duplicate->{$field} !== null && $duplicate->{$field} !== '') {
                        $keeper->{$field} = $duplicate->{$field};
                    }
                }
                $duplicate->delete();
                $removed++;
            }

            if ($keeper->isDirty()) {
                $keeper->saveQuietly();
            }
        }

        return $removed;
    }
}

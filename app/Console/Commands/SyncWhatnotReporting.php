<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncWhatnotReporting extends Command
{
    protected $signature = 'whatnot:sync-reporting
        {--since=2026-07-01 : Earliest reporting date to keep fully synced}
        {--show-limit=25 : Number of shows to pull/update per channel pass}
        {--analytics-limit=25 : Number of missing analytics shows to fill per pass}
        {--shipment-batch=6 : Number of shows per shipment browser batch}
        {--archive-before : Mark shows before --since as historical/non-operational}
        {--dry-run : Show the plan and coverage without syncing}';

    protected $description = 'Keep Whatnot reporting data complete from a reporting start date, channel by channel';

    public function handle(WhatnotScraper $scraper): int
    {
        try {
            $since = Carbon::parse((string) $this->option('since'))->startOfDay();
        } catch (\Throwable) {
            $this->error('Invalid --since date. Use YYYY-MM-DD, for example 2026-07-01.');
            return self::FAILURE;
        }

        if ($since->isFuture()) {
            $this->error('--since cannot be in the future.');
            return self::FAILURE;
        }

        $days = max(1, (int) $since->diffInDays(today()) + 2);
        $showLimit = max(20, min(30, (int) $this->option('show-limit')));
        $analyticsLimit = max(20, min(25, (int) $this->option('analytics-limit')));
        $shipmentBatch = max(1, min(10, (int) $this->option('shipment-batch')));

        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            $this->error('No active Whatnot channels are enabled for import.');
            return self::FAILURE;
        }

        $this->info('Whatnot reporting sync');
        $this->line('Reporting start: ' . $since->toDateString());
        $this->line("Channels: {$channels->count()} · show pass: {$showLimit} · analytics pass: {$analyticsLimit}");
        $this->newLine();

        $this->reportCoverage($channels->pluck('id')->all(), $since);

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if ($this->option('archive-before')) {
            $archived = Show::query()
                ->whereIn('whatnot_channel_id', $channels->pluck('id'))
                ->whereDate('show_date', '<', $since->toDateString())
                ->where('is_operational', true)
                ->update(['is_operational' => false]);

            $this->line("Archived {$archived} pre-{$since->toDateString()} show(s) from the operational workflow. No rows were deleted.");
            $this->newLine();
        }

        foreach ($channels as $index => $channel) {
            $position = $index + 1;
            $this->info("[{$position}/{$channels->count()}] {$channel->name} (@{$channel->whatnot_username})");

            $this->line("  1. Shows / normal analytics (up to {$showLimit})");
            try {
                $result = $scraper->importShows(
                    channel: $channel,
                    limit: $showLimit,
                    debug: false,
                    withOrders: false,
                );
                $this->line('     created ' . ($result['created'] ?? 0) . ', updated ' . ($result['updated'] ?? 0));
            } catch (\Throwable $e) {
                $this->warn('     show pull failed: ' . $e->getMessage());
            }

            $this->line('  2. Orders / buyers / show data');
            $syncExit = $this->call('whatnot:sync', [
                '--channel' => $channel->name,
                '--type' => 'full',
            ]);
            if ($syncExit !== self::SUCCESS) {
                $this->warn("     full sync returned exit code {$syncExit}; continuing with the remaining sources.");
            }

            $this->line('  3. Missing show analytics');
            while (true) {
                $before = $this->missingAnalyticsCount($channel->id, $since);
                if ($before === 0) {
                    $this->line('     complete');
                    break;
                }

                $exit = $this->call('whatnot:backfill-missing-analytics', [
                    '--channel' => $channel->name,
                    '--days' => $days,
                    '--limit' => $analyticsLimit,
                ]);

                if ($exit !== self::SUCCESS) {
                    $this->warn("     analytics pass returned exit code {$exit}; moving on.");
                    break;
                }

                $after = $this->missingAnalyticsCount($channel->id, $since);
                $this->line('     ' . max(0, $before - $after) . " filled, {$after} remaining");

                if ($after === 0 || $after >= $before) {
                    if ($after >= $before && $after > 0) {
                        $this->warn('     no further analytics progress; moving on instead of looping forever.');
                    }
                    break;
                }
            }

            $this->line('  4. Shipments / tracking / fulfillment data');
            $shipmentExit = $this->call('whatnot:reconcile-shipments', [
                '--channel' => $channel->name,
                '--days' => $days,
                '--batch' => $shipmentBatch,
                '--limit' => 0,
            ]);
            if ($shipmentExit !== self::SUCCESS) {
                $this->warn("     shipment reconciliation returned exit code {$shipmentExit}.");
            }

            $this->line('  5. Ledger / post-show adjustments');
            try {
                $ledger = $scraper->importLedger(
                    $channel,
                    $since->toDateString(),
                    today()->toDateString(),
                    false,
                );
                $this->line('     ledger rows created ' . ($ledger['created'] ?? 0) . ', updated ' . ($ledger['updated'] ?? 0));
            } catch (\Throwable $e) {
                $this->warn('     ledger refresh failed: ' . $e->getMessage());
            }

            $this->newLine();
        }

        $this->info('Reporting sync finished.');
        $this->reportCoverage($channels->pluck('id')->all(), $since);

        return self::SUCCESS;
    }

    private function missingAnalyticsCount(int $channelId, Carbon $since): int
    {
        return Show::query()
            ->where('whatnot_channel_id', $channelId)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('detail_url')
            ->where(function ($query) {
                $query->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0)
                    ->orWhereNull('whatnot_net')->orWhere('whatnot_net', '<=', 0);
            })
            ->count();
    }

    private function reportCoverage(array $channelIds, Carbon $since): void
    {
        $shows = Show::query()
            ->whereIn('whatnot_channel_id', $channelIds)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', today());

        $total = (clone $shows)->count();
        $missingAnalytics = (clone $shows)
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($query) {
                $query->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0)
                    ->orWhereNull('whatnot_net')->orWhere('whatnot_net', '<=', 0);
            })
            ->count();
        $withoutShipments = (clone $shows)->doesntHave('shipments')->count();

        $this->line("Coverage since {$since->toDateString()}: {$total} shows · {$missingAnalytics} missing analytics · {$withoutShipments} with no shipment rows");
    }
}

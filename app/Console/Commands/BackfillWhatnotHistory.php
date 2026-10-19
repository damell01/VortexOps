<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Console\Command;

/**
 * Walk the back catalogue until every past show has its analytics and shipments.
 *
 * This command deliberately uses the dedicated historical commands instead of
 * whatnot:refresh-recent, which handles targeted orders/shipments/ledger work.
 * Show discovery has its own whatnot:refresh-recent-shows command.
 */
class BackfillWhatnotHistory extends Command
{
    protected $signature = 'whatnot:backfill-history
                            {--analytics-days=3650 : How far back to backfill missing show analytics}
                            {--shipment-days=3650 : How far back to reconcile shipment history}
                            {--analytics-limit=25 : Analytics shows per pass}
                            {--shipment-batch=4 : Shipment shows per browser batch}
                            {--sleep=20 : Seconds to wait between analytics passes}
                            {--dry-run : Report what is outstanding and stop}
                            {--verify : Verify saved sign-in and attempt one analytics target}
                            {--batches=0 : Maximum analytics passes per channel (0 = until complete)}';

    protected $description = 'Backfill all historical Whatnot analytics and shipment data into VortexOps';

    public function handle(): int
    {
        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            $this->error('No Whatnot channels are active and enabled for import.');
            return self::FAILURE;
        }

        $analyticsDays = max(1, (int) $this->option('analytics-days'));
        $shipmentDays = max(1, (int) $this->option('shipment-days'));
        $analyticsLimit = max(1, min(25, (int) $this->option('analytics-limit')));
        $shipmentBatch = max(1, min(10, (int) $this->option('shipment-batch')));
        $sleep = max(0, (int) $this->option('sleep'));

        $outstanding = $this->reportCoverage($channels->pluck('id')->all());
        $this->line("Analytics pass size: up to {$analyticsLimit} shows per channel.");

        if ($this->option('dry-run') || $outstanding === 0) {
            return self::SUCCESS;
        }

        if (\App\Support\WhatnotBrowserLock::isHeld()) {
            $this->error('Historical backfill stopped: the browser lock is held by another job.');
            $this->line('Inspect it with php artisan whatnot:unlock before retrying.');
            return self::FAILURE;
        }
        $verify = (bool) $this->option('verify');
        $maxPasses = $verify ? 1 : max(0, (int) $this->option('batches'));
        if ($verify) {
            $this->line('Verifying on one show');
            $analyticsLimit = 1;
            if (!app(\App\Services\WhatnotScraper::class)->hasCookieFile()) {
                $this->error('No saved Whatnot sign-in is available. Refresh the sign-in before verification.');
                return self::FAILURE;
            }
        }

        // Analytics is intentionally done one channel at a time and in repeated
        // bounded passes. BackfillMissingWhatnotAnalytics seeds every show UUID
        // individually, which is the reliable path for historical show metrics.
        foreach ($channels as $channel) {
            $this->newLine();
            $this->info("Analytics backfill: {$channel->name}");

            $passes = 0;
            while (true) {
                if ($maxPasses > 0 && $passes++ >= $maxPasses) break;
                $before = $this->missingAnalyticsCount($channel->id, $analyticsDays);
                if ($before === 0) {
                    $this->line('  Analytics complete.');
                    break;
                }

                $exit = $this->call('whatnot:backfill-missing-analytics', [
                    '--channel' => $channel->name,
                    '--days' => $analyticsDays,
                    '--limit' => $analyticsLimit,
                ]);

                if ($exit !== self::SUCCESS) {
                    $this->error("Analytics backfill stopped for {$channel->name} with exit code {$exit}.");
                    return self::FAILURE;
                }

                $after = $this->missingAnalyticsCount($channel->id, $analyticsDays);
                $filled = max(0, $before - $after);
                $this->line("  {$filled} show(s) filled; {$after} still missing analytics.");

                if ($after === 0) {
                    break;
                }

                if ($after >= $before) {
                    $this->warn("No analytics progress for {$channel->name}; stopping instead of looping forever.");
                    break;
                }

                if ($sleep > 0) {
                    sleep($sleep);
                }
            }
        }

        if ($verify) {
            return $this->reportCoverage($channels->pluck('id')->all()) < $outstanding ? self::SUCCESS : self::FAILURE;
        }

        // Shipment reconciliation already supports all shows in range, all active
        // imported channels, and --limit=0 means no show-count ceiling.
        $this->newLine();
        $this->info('Shipment history reconciliation: all active imported channels');
        $shipmentExit = $this->call('whatnot:reconcile-shipments', [
            '--days' => $shipmentDays,
            '--batch' => $shipmentBatch,
            '--limit' => 0,
        ]);

        if ($shipmentExit !== self::SUCCESS) {
            $this->error("Shipment reconciliation stopped with exit code {$shipmentExit}.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Historical Whatnot backfill finished.');
        $this->reportCoverage($channels->pluck('id')->all());

        return self::SUCCESS;
    }

    private function pastShows(array $channelIds)
    {
        return Show::whereIn('whatnot_channel_id', $channelIds)->readyForAnalytics();
    }

    private function missingAnalytics($query)
    {
        return $query->missingHistoricalAnalytics();
    }

    private function reportCoverage(array $channelIds): int
    {
        $past = $this->pastShows($channelIds);
        $total = (clone $past)->count();
        $missingAnalytics = $this->missingAnalytics(clone $past)->count();
        $missingShipments = (clone $past)->whereNull('last_shipments_synced_at')->doesntHave('shipments')->count();
        $outstanding = (clone $past)->where(function ($q) {
            $q->where(fn ($q) => $this->missingAnalytics($q))
                ->orWhere(fn ($q) => $q->whereNull('last_shipments_synced_at')->doesntHave('shipments'));
        })->count();
        $this->newLine();
        $this->line("  {$total} past shows · {$missingAnalytics} missing analytics · {$missingShipments} with no shipment sync");
        $this->line($outstanding ? "{$outstanding} still missing analytics or shipments" : 'Nothing outstanding');
        foreach (WhatnotChannel::whereNotIn('id', $channelIds)->where('status', 'active')->get() as $excluded) {
            $this->line($excluded->name);
            $this->line("  include in import is switched off; its shows are excluded.");
        }
        return $outstanding;
    }

    private function missingAnalyticsCount(int $channelId, int $days): int
    {
        return $this->missingAnalytics($this->pastShows([$channelId])
            ->whereDate('show_date', '>=', today()->subDays($days)))->count();
    }
}

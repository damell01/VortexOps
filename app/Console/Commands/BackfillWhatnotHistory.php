<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Console\Command;

/**
 * Walk the back catalogue until every past show has its analytics and shipments.
 *
 * This command deliberately uses the dedicated historical commands instead of
 * whatnot:refresh-recent. VortexOps has two commands named refresh-recent in the
 * codebase, and the registered one is the targeted orders/shipments/ledger job;
 * relying on that ambiguous command made this history runner pass options the
 * active command does not support.
 */
class BackfillWhatnotHistory extends Command
{
    protected $signature = 'whatnot:backfill-history
                            {--analytics-days=3650 : How far back to backfill missing show analytics}
                            {--shipment-days=3650 : How far back to reconcile shipment history}
                            {--analytics-limit=25 : Analytics shows per pass}
                            {--shipment-batch=4 : Shipment shows per browser batch}
                            {--sleep=20 : Seconds to wait between analytics passes}
                            {--dry-run : Report what is outstanding and stop}';

    protected $description = 'Backfill all historical Whatnot analytics and shipment data into VortexOps';

    public function handle(): int
    {
        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            $this->error('No active Whatnot channels are enabled for import.');
            return self::FAILURE;
        }

        $analyticsDays = max(1, (int) $this->option('analytics-days'));
        $shipmentDays = max(1, (int) $this->option('shipment-days'));
        $analyticsLimit = max(1, min(25, (int) $this->option('analytics-limit')));
        $shipmentBatch = max(1, min(10, (int) $this->option('shipment-batch')));
        $sleep = max(0, (int) $this->option('sleep'));

        $this->reportCoverage($channels->pluck('id')->all());

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        // Analytics is intentionally done one channel at a time and in repeated
        // bounded passes. BackfillMissingWhatnotAnalytics seeds every show UUID
        // individually, which is the reliable path for historical show metrics.
        foreach ($channels as $channel) {
            $this->newLine();
            $this->info("Analytics backfill: {$channel->name}");

            while (true) {
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

    private function reportCoverage(array $channelIds): void
    {
        $past = Show::query()
            ->whereIn('whatnot_channel_id', $channelIds)
            ->whereDate('show_date', '<=', today())
            ->whereNotNull('detail_url');

        $total = (clone $past)->count();
        $missingAnalytics = (clone $past)
            ->where(function ($q) {
                $q->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0)
                  ->orWhereNull('whatnot_net')->orWhere('whatnot_net', '<=', 0);
            })
            ->count();
        $missingShipments = (clone $past)->doesntHave('shipments')->count();

        $this->newLine();
        $this->line("  {$total} past shows · {$missingAnalytics} missing analytics · {$missingShipments} with no shipment rows");
    }

    private function missingAnalyticsCount(int $channelId, int $days): int
    {
        return Show::query()
            ->where('whatnot_channel_id', $channelId)
            ->whereDate('show_date', '<=', today())
            ->whereDate('show_date', '>=', today()->subDays($days))
            ->whereNotNull('detail_url')
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) {
                $q->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0)
                  ->orWhereNull('whatnot_net')->orWhere('whatnot_net', '<=', 0);
            })
            ->count();
    }
}

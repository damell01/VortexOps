<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotReportingReconciler;
use App\Services\WhatnotScraper;
use App\Support\WhatnotBrowserLock;
use App\Support\WhatnotPipelineLock;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

class SyncWhatnotReporting extends Command
{
    protected $signature = 'whatnot:sync-reporting
        {--since=2026-07-01 : Earliest reporting date to keep fully synced}
        {--show-limit=25 : Number of shows to pull/update per channel pass}
        {--analytics-limit=25 : Number of missing analytics shows to fill per channel run}
        {--shipment-batch=25 : Number of shows per shipment browser batch}
        {--shipments-only : Skip show refresh, analytics, orders, and ledger; reconcile historical shipments only}
        {--analytics-only : Walk each channel Seller Hub Past shows and refresh analytics in one browser session; skip discovery, orders, shipments, and ledger}
        {--without-orders : Skip order/buyer reconciliation (orders run by default)}
        {--order-batch=25 : Number of shows per authoritative order batch when --with-orders is used}
        {--wait=0 : Seconds to wait for another Whatnot pipeline; 0 fails fast}
        {--skip-if-busy : Exit cleanly if another Whatnot pipeline is active}
        {--dry-run : Show the plan and coverage without syncing}';

    protected $description = 'Keep Whatnot show analytics, shipments, and ledger reporting complete from a hard start date';

    public function handle(WhatnotScraper $scraper, WhatnotReportingReconciler $reconciler): int
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

        $showLimit = max(1, min(30, (int) $this->option('show-limit')));
        $analyticsOnly = (bool) $this->option('analytics-only');
        $analyticsLimit = max(1, min(25, (int) $this->option('analytics-limit')));
        $shipmentBatch = max(1, min(30, (int) $this->option('shipment-batch')));
        $shipmentsOnly = (bool) $this->option('shipments-only');
        if ($shipmentsOnly && $analyticsOnly) {
            $this->error('Choose either --shipments-only or --analytics-only, not both.');
            return self::FAILURE;
        }
        $withOrders = ! $shipmentsOnly && ! $analyticsOnly && ! (bool) $this->option('without-orders');
        $orderBatch = max(1, min(30, (int) $this->option('order-batch')));
        $waitSeconds = max(0, min(14400, (int) $this->option('wait')));

        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            $this->error('No active Whatnot channels are enabled for import.');
            return self::FAILURE;
        }

        $this->info($shipmentsOnly ? 'WHATNOT HISTORICAL SHIPMENT BACKFILL' : ($analyticsOnly ? 'WHATNOT HISTORICAL ANALYTICS BACKFILL' : 'COORDINATED WHATNOT REPORTING SYNC'));
        $this->line('Reporting start: '.$since->toDateString());
        if ($shipmentsOnly) {
            $this->line("Channels: {$channels->count()} · shipment batch {$shipmentBatch} · SHIPMENTS ONLY · orders OFF");
        } elseif ($analyticsOnly) {
            $this->line("Channels: {$channels->count()} · ANALYTICS ONLY · Seller Hub Past-show walk · discovery OFF · orders OFF · shipments OFF · ledger OFF");
        } else {
            $this->line(
                "Channels: {$channels->count()} · show {$showLimit} · analytics {$analyticsLimit} · shipment {$shipmentBatch}".
                ($withOrders ? " · orders {$orderBatch}" : ' · orders OFF')
            );
        }
        $this->newLine();
        $this->reportCoverage($channels->pluck('id')->all(), $since);

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        WhatnotPipelineLock::recoverIfStale();
        $lock = WhatnotPipelineLock::acquire(
            ($shipmentsOnly ? 'Historical shipment backfill from ' : 'Coordinated reporting sync from ').$since->toDateString(),
            $this->option('skip-if-busy') ? 0 : $waitSeconds,
        );

        if (! $lock) {
            $message = WhatnotPipelineLock::busyMessage();
            if ($this->option('skip-if-busy')) {
                $this->line("Reporting sync skipped — {$message}");
                return self::SUCCESS;
            }
            $this->error('Reporting sync did not start — '.$message);
            $this->line('Stop/wait for that process, then rerun. To intentionally wait, pass --wait=<seconds>.');
            return self::FAILURE;
        }

        // Scheduled runs are single-flight. Holding the coordinator is not enough:
        // a direct/manual scraper may already own the shared browser profile. In that
        // case --skip-if-busy must leave immediately instead of becoming a second
        // long-lived PHP process waiting behind Chrome.
        if ($this->option('skip-if-busy')) {
            WhatnotBrowserLock::recoverIfStale();
            $browserHolder = WhatnotBrowserLock::holder();

            if ($browserHolder !== null && $browserHolder['alive']) {
                WhatnotPipelineLock::release($lock);
                $this->line("Reporting sync skipped — shared Whatnot browser is already active (PID {$browserHolder['pid']}).");
                return self::SUCCESS;
            }

            // Close the tiny race between the preflight above and each scraper call.
            // Any browser contention inside this scheduled run fails immediately;
            // scheduled work is refreshed by the next cadence instead of piling up.
            config(['vortex.whatnot.browser_lock_fail_fast' => true]);
        }

        $this->line('Coordinator lock acquired. Starting channel work now.');
        $progress = fn (string $line) => $this->line('      <fg=gray>'.OutputFormatter::escape($line).'</>');

        try {
            foreach ($channels as $index => $channel) {
                $position = $index + 1;
                $step = 1;
                $this->info("[{$position}/{$channels->count()}] {$channel->name} (@{$channel->whatnot_username})");

                if ($shipmentsOnly) {
                    $this->line("  {$step}. Historical shipments / fulfillment ({$shipmentBatch}-show batches)");
                    try {
                        $shipments = $reconciler->reconcileShipments($channel, $since, $shipmentBatch, $progress);
                        $this->line("     {$shipments['checked']} checked · {$shipments['created']} created · {$shipments['updated']} updated · {$shipments['skipped']} skipped");
                    } catch (\Throwable $e) {
                        $this->warn('     shipment reconciliation failed: '.$e->getMessage());
                    }

                    $this->reportChannelCoverage($channel->id, $since);
                    $this->newLine();
                    continue;
                }

                if (! $analyticsOnly) {
                    $this->line("  {$step}. Discover Current / Upcoming / Past shows (Scrapling Seller Hub)");
                    $step++;
                    try {
                        $result = $reconciler->discoverShows($channel, $progress);
                        $this->line('     created '.($result['created'] ?? 0).', refreshed '.($result['updated'] ?? 0).', skipped '.($result['skipped'] ?? 0));
                    } catch (\Throwable $e) {
                        $this->warn('     show discovery failed: '.$e->getMessage());
                    }
                }

                if ($analyticsOnly) {
                    $missingBefore = $this->missingAnalyticsCount($channel->id, $since);
                    $this->line("  {$step}. Historical analytics channel walk ({$missingBefore} database show(s) currently missing gross/net; Seller Hub Past shows scanned once)");
                    $step++;
                    try {
                        $analytics = $reconciler->backfillAnalytics($channel, $since, $analyticsLimit, $progress);
                        $remaining = $this->missingAnalyticsCount($channel->id, $since);
                        $this->line("     completed: {$analytics['updated']} updated · {$analytics['failed']} failed · {$analytics['skipped']} skipped · {$remaining} remaining");
                    } catch (\Throwable $e) {
                        $this->warn('     analytics backfill failed: '.$e->getMessage());
                    }

                    $this->reportChannelCoverage($channel->id, $since);
                    $this->newLine();
                    continue;
                }

                if ($withOrders) {
                    $this->line("  {$step}. Authoritative orders / buyers ({$orderBatch}-show batches)");
                    $step++;
                    try {
                        $orders = $reconciler->reconcileOrders($channel, $since, $orderBatch, $progress);
                        $this->line("     {$orders['checked']} checked · {$orders['created']} current rows · {$orders['replaced']} old rows replaced · {$orders['rejected']} rejected · {$orders['skipped']} skipped");
                    } catch (\Throwable $e) {
                        $this->warn('     order reconciliation failed: '.$e->getMessage());
                    }
                }

                $this->line("  {$step}. Missing analytics (up to {$analyticsLimit} this run)");
                $step++;
                try {
                    $analytics = $reconciler->backfillAnalytics($channel, $since, $analyticsLimit, $progress);
                    $this->line("     {$analytics['updated']} updated · {$analytics['failed']} failed · {$analytics['skipped']} skipped");
                } catch (\Throwable $e) {
                    $this->warn('     analytics backfill failed: '.$e->getMessage());
                }

                $this->line("  {$step}. Shipments / fulfillment ({$shipmentBatch}-show batches)");
                $step++;
                try {
                    $shipments = $reconciler->reconcileShipments($channel, $since, $shipmentBatch, $progress);
                    $this->line("     {$shipments['checked']} checked · {$shipments['created']} created · {$shipments['updated']} updated · {$shipments['skipped']} skipped");
                } catch (\Throwable $e) {
                    $this->warn('     shipment reconciliation failed: '.$e->getMessage());
                }

                $this->line("  {$step}. Ledger / post-show adjustments");
                try {
                    $ledger = $scraper->importLedger($channel, $since->toDateString(), today()->toDateString(), false);
                    $this->line('     ledger rows created '.($ledger['created'] ?? 0).', updated '.($ledger['updated'] ?? 0));
                } catch (\Throwable $e) {
                    $this->warn('     ledger refresh failed: '.$e->getMessage());
                }

                $this->reportChannelCoverage($channel->id, $since);
                $this->newLine();
            }
        } finally {
            WhatnotPipelineLock::release($lock);
        }

        if (! $shipmentsOnly) {
            $this->reconcileEndedShowState($channels->pluck('id')->all(), $since);
        }

        $this->info($shipmentsOnly ? 'Historical shipment backfill finished.' : 'Reporting sync finished.');
        $this->reportCoverage($channels->pluck('id')->all(), $since);
        return self::SUCCESS;
    }

    private function reconcileEndedShowState(array $channelIds, Carbon $since): void
    {
        $cutoff = now()->subHours(12);
        $candidates = Show::query()
            ->whereIn('whatnot_channel_id', $channelIds)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', $cutoff->toDateString())
            ->whereNotIn('status', ['cancelled', 'closed'])
            ->withCount(['orders', 'shipments'])
            ->get();

        $flagged = 0;
        foreach ($candidates as $show) {
            $hasActivity = (int) $show->orders_count > 0
                || (int) $show->shipments_count > 0
                || (int) ($show->units_sold ?? 0) > 0
                || (float) ($show->gross_revenue ?? 0) > 0
                || (float) ($show->whatnot_net ?? 0) > 0;

            if ($hasActivity) {
                continue;
            }

            $notes = trim((string) $show->notes);
            $flag = '[SYSTEM] Past show has no Whatnot sales, orders, shipments, gross, or net data. Verify whether the show happened or was cancelled.';
            if (! str_contains($notes, $flag)) {
                $show->forceFill(['notes' => trim($notes."\n".$flag)])->saveQuietly();
                $flagged++;
            }
        }

        $this->line("Ended-show check: {$flagged} show(s) newly flagged for happened/cancelled verification.");
    }

    private function missingAnalyticsCount(int $channelId, Carbon $since): int
    {
        return Show::query()
            ->where('whatnot_channel_id', $channelId)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('whatnot_show_id')
            ->where(fn ($q) => $q->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0)->orWhereNull('whatnot_net')->orWhere('whatnot_net', '<=', 0))
            ->count();
    }

    private function reportChannelCoverage(int $channelId, Carbon $since): void
    {
        $shows = Show::query()
            ->where('whatnot_channel_id', $channelId)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled']);

        $total = (clone $shows)->count();
        $analytics = (clone $shows)->where(function ($q) {
            $q->whereNull('gross_revenue')
                ->orWhere('gross_revenue', '<=', 0)
                ->orWhereNull('whatnot_net')
                ->orWhere('whatnot_net', '<=', 0);
        })->count();
        $shipments = (clone $shows)->doesntHave('shipments')->count();

        $this->line("     coverage: {$total} shows · {$analytics} missing analytics · {$shipments} no shipments");
    }

    private function reportCoverage(array $channelIds, Carbon $since): void
    {
        $shows = Show::query()
            ->whereIn('whatnot_channel_id', $channelIds)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled']);

        $total = (clone $shows)->count();
        $missingAnalytics = (clone $shows)->where(function ($q) {
            $q->whereNull('gross_revenue')
                ->orWhere('gross_revenue', '<=', 0)
                ->orWhereNull('whatnot_net')
                ->orWhere('whatnot_net', '<=', 0);
        })->count();
        $withoutShipments = (clone $shows)->doesntHave('shipments')->count();

        $this->line("Coverage since {$since->toDateString()}: {$total} shows · {$missingAnalytics} missing analytics · {$withoutShipments} with no shipment rows");
    }
}

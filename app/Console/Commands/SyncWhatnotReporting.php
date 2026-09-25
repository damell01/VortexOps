<?php

namespace App\Console\Commands;

use App\Models\Setting;
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
        {--analytics-limit=0 : Number of incomplete analytics shows per channel run; 0 processes all}
        {--shipment-batch=25 : Number of shows per shipment browser batch}
        {--shipments-only : Skip show refresh, analytics, orders, and ledger; reconcile historical shipments only}
        {--analytics-only : Walk each channel Seller Hub Past shows and refresh analytics in one browser session; skip discovery, orders, shipments, and ledger}
        {--without-orders : Skip order/buyer reconciliation (orders run by default)}
        {--order-batch=25 : Number of shows per authoritative order batch when --with-orders is used}
        {--wait=0 : Seconds to wait for another Whatnot pipeline; 0 fails fast}
        {--skip-if-busy : Exit cleanly if another Whatnot pipeline is active}
        {--test : Production smoke test: one channel and one item through each data phase}
        {--max-runtime=10800 : Stop starting new channel work after this many seconds}
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
        $analyticsLimit = max(0, (int) $this->option('analytics-limit'));
        $shipmentBatch = max(1, min(30, (int) $this->option('shipment-batch')));
        $shipmentsOnly = (bool) $this->option('shipments-only');
        if ($shipmentsOnly && $analyticsOnly) {
            $this->error('Choose either --shipments-only or --analytics-only, not both.');
            return self::FAILURE;
        }
        $withOrders = ! $shipmentsOnly && ! $analyticsOnly && ! (bool) $this->option('without-orders');
        $orderBatch = max(1, min(30, (int) $this->option('order-batch')));
        $waitSeconds = max(0, min(14400, (int) $this->option('wait')));
        $maxRuntime = max(300, min(21600, (int) $this->option('max-runtime')));
        $startedAt = microtime(true);
        $testMode = (bool) $this->option('test');
        if ($testMode) {
            $showLimit = 1;
            $analyticsLimit = 1;
            $shipmentBatch = 1;
            $orderBatch = 1;
        }

        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->when($testMode, fn ($q) => $q->limit(1))
            ->get();

        if ($channels->isEmpty()) {
            $this->error('No active Whatnot channels are enabled for import.');
            return self::FAILURE;
        }

        $this->info($testMode ? 'WHATNOT PRODUCTION SMOKE TEST' : ($shipmentsOnly ? 'WHATNOT HISTORICAL SHIPMENT BACKFILL' : ($analyticsOnly ? 'WHATNOT HISTORICAL ANALYTICS BACKFILL' : 'COORDINATED WHATNOT REPORTING SYNC')));
        $this->line('Reporting start: '.$since->toDateString());
        if ($testMode) $this->line('TEST MODE: first enabled channel · one analytics target · one order target · one shipment target · ledger still verified');
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
        $progress = function (string $line) {
            $this->line('      <fg=gray>'.OutputFormatter::escape($line).'</>');
            $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
            if (in_array($state['status'] ?? null, ['queued', 'running'], true)) {
                $state['status'] = 'running';
                $state['phase'] = $line;
                $state['updated_at'] = now()->toIso8601String();
                Setting::set('whatnot_ui_job', json_encode($state));
            }
        };
        $retry = function (callable $work, string $phase) use ($progress) {
            $last = null;
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    return $work();
                } catch (\Throwable $e) {
                    $last = $e;
                    if ($attempt < 2) {
                        $progress("{$phase}: transient failure; retrying once in 3 seconds — {$e->getMessage()}");
                        sleep(3);
                    }
                }
            }
            throw $last;
        };
        $smoke = [];

        try {
            foreach ($channels as $index => $channel) {
                if (! $testMode && (microtime(true) - $startedAt) >= $maxRuntime) {
                    $this->warn("Runtime ceiling reached ({$maxRuntime}s); stopping cleanly before the next channel. The next scheduled pass will continue.");
                    break;
                }
                $position = $index + 1;
                $step = 1;
                $this->info("[{$position}/{$channels->count()}] {$channel->name} (@{$channel->whatnot_username})");

                if ($shipmentsOnly) {
                    $this->line("  {$step}. Historical shipments / fulfillment ({$shipmentBatch}-show batches)");
                    try {
                        $shipments = $retry(fn () => $reconciler->reconcileShipments($channel, $since, $shipmentBatch, $progress, $testMode ? 1 : null), 'shipments');
                    if ($testMode) $smoke['Shipments'] = true;
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
                        $result = $retry(fn () => $reconciler->discoverShows($channel, $progress), 'discovery');
                        if ($testMode) {
                            $counts = $result['counts'] ?? [];
                            $smoke['Authentication / channel'] = true;
                            $smoke['Current discovery'] = array_key_exists('current', $counts);
                            $smoke['Upcoming discovery'] = array_key_exists('upcoming', $counts);
                            $smoke['Past discovery'] = (int) ($counts['past'] ?? 0) > 0;
                            $smoke['Show DB upsert'] = (($result['created'] ?? 0) + ($result['updated'] ?? 0)) > 0;
                        }
                        $this->line('     created '.($result['created'] ?? 0).', refreshed '.($result['updated'] ?? 0).', skipped '.($result['skipped'] ?? 0));
                    } catch (\Throwable $e) {
                        $this->warn('     show discovery failed: '.$e->getMessage());
                    }
                }

                if ($analyticsOnly) {
                    $missingBefore = $this->missingAnalyticsCount($channel->id, $since);
                    $this->line("  {$step}. Historical analytics channel walk ({$missingBefore} database show(s) currently due for analytics refresh; Seller Hub Past shows scanned once)");
                    $step++;
                    try {
                        $analytics = $retry(fn () => $reconciler->backfillAnalytics($channel, $since, $analyticsLimit, $progress), 'analytics');
                    if ($testMode) $smoke['Analytics'] = true;
                        $remaining = $this->missingAnalyticsCount($channel->id, $since);
                        $this->line("     completed: {$analytics['updated']} updated · {$analytics['failed']} failed · {$analytics['skipped']} skipped · {$remaining} due for refresh");
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
                        $orders = $retry(fn () => $reconciler->reconcileOrders($channel, $since, $orderBatch, $progress), 'orders');
                        if ($testMode) $smoke['Orders'] = true;
                        $this->line("     {$orders['checked']} checked · {$orders['created']} current rows · {$orders['replaced']} old rows replaced · {$orders['rejected']} rejected · {$orders['skipped']} skipped");
                    } catch (\Throwable $e) {
                        $this->warn('     order reconciliation failed: '.$e->getMessage());
                    }
                }

                $this->line("  {$step}. Missing analytics (up to {$analyticsLimit} this run)");
                $step++;
                try {
                    $analytics = $retry(fn () => $reconciler->backfillAnalytics($channel, $since, $analyticsLimit, $progress), 'analytics');
                    if ($testMode) $smoke['Analytics'] = true;
                    $this->line("     {$analytics['updated']} updated · {$analytics['failed']} failed · {$analytics['skipped']} skipped");
                } catch (\Throwable $e) {
                    $this->warn('     analytics backfill failed: '.$e->getMessage());
                }

                $this->line("  {$step}. Shipments / fulfillment ({$shipmentBatch}-show batches)");
                $step++;
                try {
                    $shipments = $retry(fn () => $reconciler->reconcileShipments($channel, $since, $shipmentBatch, $progress), 'shipments');
                    if ($testMode) $smoke['Shipments'] = true;
                    $this->line("     {$shipments['checked']} checked · {$shipments['created']} created · {$shipments['updated']} updated · {$shipments['skipped']} skipped");
                } catch (\Throwable $e) {
                    $this->warn('     shipment reconciliation failed: '.$e->getMessage());
                }

                $this->line("  {$step}. Ledger / post-show adjustments");
                try {
                    $ledger = $retry(fn () => $scraper->importLedger($channel, $since->toDateString(), today()->toDateString(), false), 'ledger');
                    if ($testMode) $smoke['Ledger'] = true;
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

        if ($testMode) {
            $smoke['Browser cleanup'] = WhatnotBrowserLock::holder() === null;
            $expected = ['Authentication / channel','Current discovery','Upcoming discovery','Past discovery','Show DB upsert','Orders','Analytics','Shipments','Ledger','Browser cleanup'];
            $rows = [];
            $passed = true;
            foreach ($expected as $phase) {
                $ok = (bool) ($smoke[$phase] ?? false);
                $passed = $passed && $ok;
                $rows[] = [$phase, $ok ? 'PASS' : 'FAIL'];
            }
            $this->newLine();
            $this->table(['Production smoke test', 'Result'], $rows);
            $this->line('RESULT: '.($passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>'));
            return $passed ? self::SUCCESS : self::FAILURE;
        }

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
            ->missingAnalytics()
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
        $analytics = (clone $shows)->missingAnalytics()->count();
        $shipments = (clone $shows)->doesntHave('shipments')->count();

        $this->line("     coverage: {$total} shows · {$analytics} due for analytics refresh · {$shipments} no shipments");
    }

    private function reportCoverage(array $channelIds, Carbon $since): void
    {
        $shows = Show::query()
            ->whereIn('whatnot_channel_id', $channelIds)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled']);

        $total = (clone $shows)->count();
        $missingAnalytics = (clone $shows)->missingAnalytics()->count();
        $withoutShipments = (clone $shows)->doesntHave('shipments')->count();

        $this->line("Coverage since {$since->toDateString()}: {$total} shows · {$missingAnalytics} due for analytics refresh · {$withoutShipments} with no shipment rows");
    }
}

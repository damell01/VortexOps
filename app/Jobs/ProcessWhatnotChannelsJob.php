<?php

namespace App\Jobs;

use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Support\WhatnotBrowserLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fast hourly Whatnot show/analytics pull.
 *
 * Keep the critical path intentionally small: each enabled channel gets a show
 * and analytics refresh, then we immediately move to the next channel. Orders,
 * shipments, ledger and historical reconciliation run on separate schedules so
 * a slow fulfillment page can never block fresh show data for another channel.
 */
class ProcessWhatnotChannelsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;
    public int $uniqueFor = 4200;

    public function __construct(
        public readonly string $type = 'incremental',
        public readonly int $ledgerDays = 30,
        public readonly int $shipmentLimit = 50,
        public readonly bool $showProgress = false,
    ) {}

    public function uniqueId(): string
    {
        return 'whatnot-hourly-show-analytics-pull';
    }

    private function progress(string $message): void
    {
        if (! $this->showProgress || app()->runningUnitTests()) return;
        fwrite(STDOUT, $message . PHP_EOL);
        fflush(STDOUT);
    }

    public function handle(WhatnotScraper $scraper): void
    {
        $recovery = WhatnotBrowserLock::recoverIfStale();
        if ($recovery['recovered']) {
            Log::warning('ProcessWhatnotChannelsJob: recovered stale Whatnot browser state', [
                'stale_holder_pid' => $recovery['holder_pid'],
                'killed_orphan_browser_pids' => $recovery['killed_pids'],
                'removed_profile_locks' => $recovery['removed'],
            ]);
        }

        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            Log::warning('ProcessWhatnotChannelsJob: no enabled active Whatnot channels found');
            return;
        }

        $count = $channels->count();
        Log::info('ProcessWhatnotChannelsJob: starting hourly show/analytics pull', [
            'channels' => $channels->pluck('whatnot_username')->values()->all(),
        ]);

        foreach ($channels as $position => $channel) {
            $number = $position + 1;
            $startedAt = microtime(true);
            $this->progress("[{$number}/{$count}] {$channel->name}: shows / analytics");

            try {
                $result = $scraper->importShows(
                    channel: $channel,
                    limit: (int) config('vortex.whatnot.limit', 50),
                    debug: false,
                    withOrders: false,
                );

                Log::info("Whatnot hourly pull [{$number}/{$count}]: finished {$channel->name}", [
                    'created' => $result['created'] ?? 0,
                    'updated' => $result['updated'] ?? 0,
                    'skipped' => $result['skipped'] ?? 0,
                    'elapsed_seconds' => (int) round(microtime(true) - $startedAt),
                ]);
            } catch (\Throwable $e) {
                // A bad channel must not prevent the remaining channels from
                // receiving their hourly show/analytics refresh.
                Log::error("Whatnot hourly pull [{$number}/{$count}]: {$channel->name} failed", [
                    'exception' => $e->getMessage(),
                ]);
                $this->progress("  ERROR: {$e->getMessage()}");
            }
        }

        Log::info('ProcessWhatnotChannelsJob: hourly show/analytics pull complete');
    }
}

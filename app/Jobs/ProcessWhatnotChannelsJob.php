<?php

namespace App\Jobs;

use App\Models\ShowIngestionLog;
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
use Illuminate\Support\Str;

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
        $runId = (string) Str::uuid();
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
            ShowIngestionLog::create([
                'source' => 'whatnot_show_analytics',
                'status' => 'failed',
                'raw_payload' => ['run_id' => $runId, 'channels' => 0],
                'error_message' => 'No enabled active Whatnot channels found.',
            ]);
            Log::warning('ProcessWhatnotChannelsJob: no enabled active Whatnot channels found');
            return;
        }

        $count = $channels->count();
        $successes = 0;
        $failures = 0;
        $errors = [];

        Log::info('ProcessWhatnotChannelsJob: starting hourly show/analytics pull', [
            'channels' => $channels->pluck('whatnot_username')->values()->all(),
            'run_id' => $runId,
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

                $payload = [
                    'run_id' => $runId,
                    'created' => $result['created'] ?? 0,
                    'updated' => $result['updated'] ?? 0,
                    'skipped' => $result['skipped'] ?? 0,
                    'elapsed_seconds' => (int) round(microtime(true) - $startedAt),
                ];

                ShowIngestionLog::create([
                    'whatnot_channel_id' => $channel->id,
                    'source' => 'whatnot_show_analytics',
                    'status' => 'success',
                    'raw_payload' => $payload,
                ]);

                $successes++;
                Log::info("Whatnot hourly pull [{$number}/{$count}]: finished {$channel->name}", $payload);
            } catch (\Throwable $e) {
                $failures++;
                $errors[] = $channel->name . ': ' . $e->getMessage();

                ShowIngestionLog::create([
                    'whatnot_channel_id' => $channel->id,
                    'source' => 'whatnot_show_analytics',
                    'status' => 'failed',
                    'raw_payload' => [
                        'run_id' => $runId,
                        'elapsed_seconds' => (int) round(microtime(true) - $startedAt),
                    ],
                    'error_message' => $e->getMessage(),
                ]);

                Log::error("Whatnot hourly pull [{$number}/{$count}]: {$channel->name} failed", [
                    'exception' => $e->getMessage(),
                ]);
                $this->progress("  ERROR: {$e->getMessage()}");
            }
        }

        $status = $failures === 0 ? 'success' : ($successes > 0 ? 'partial' : 'failed');

        ShowIngestionLog::create([
            'source' => 'whatnot_show_analytics',
            'status' => $status,
            'raw_payload' => [
                'run_id' => $runId,
                'channels_total' => $count,
                'channels_succeeded' => $successes,
                'channels_failed' => $failures,
            ],
            'error_message' => $errors ? implode("\n", $errors) : null,
        ]);

        Log::info('ProcessWhatnotChannelsJob: hourly show/analytics pull complete', [
            'run_id' => $runId,
            'status' => $status,
            'channels_succeeded' => $successes,
            'channels_failed' => $failures,
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use App\Support\WhatnotPipelineLock;

class RunWhatnotReportingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 14400;
    public int $backoff = 30;

    private const MAX_BUSY_WAIT_SECONDS = 3600;

    public function __construct(public readonly string $mode, public readonly int $launchedBy)
    {
        $this->onQueue('whatnot');
    }

    public function handle(): void
    {
        $args = match ($this->mode) {
            'test' => ['--test' => true],
            'freshness' => ['--since' => now()->subDays(7)->toDateString(), '--show-limit' => 10, '--order-batch' => 10, '--analytics-limit' => 5, '--shipment-batch' => 10, '--max-runtime' => 2700, '--skip-if-busy' => true],
            'analytics' => ['--since' => '2026-07-01', '--analytics-only' => true, '--analytics-limit' => 0, '--max-runtime' => 21600, '--skip-if-busy' => true],
            'full' => ['--since' => '2026-07-01', '--show-limit' => 30, '--order-batch' => 30, '--analytics-limit' => 25, '--shipment-batch' => 30, '--max-runtime' => 10800, '--skip-if-busy' => true],
            default => throw new \InvalidArgumentException("Unknown Whatnot reporting mode: {$this->mode}"),
        };
        $existing = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
        $startedAt = $existing['started_at'] ?? now()->toIso8601String();
        $waitStartedAt = $existing['wait_started_at'] ?? now()->toIso8601String();

        // UI-dispatched work should queue behind a legitimate scheduled/manual
        // pipeline instead of immediately becoming BLOCKED. Release this queue
        // job so the worker stays free, then retry until the coordinator clears.
        $holder = WhatnotPipelineLock::holder();
        if ($holder && ($holder['alive'] ?? false)) {
            $waitAge = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($waitStartedAt));
            if ($waitAge >= self::MAX_BUSY_WAIT_SECONDS) {
                Setting::set('whatnot_ui_job', json_encode(array_merge($existing, [
                    'mode' => $this->mode,
                    'status' => 'blocked',
                    'launched_by' => $this->launchedBy,
                    'started_at' => $startedAt,
                    'finished_at' => now()->toIso8601String(),
                    'phase' => 'Timed out waiting for the active Whatnot pipeline',
                    'active_holder' => $holder,
                ])));
                return;
            }

            Setting::set('whatnot_ui_job', json_encode(array_merge($existing, [
                'mode' => $this->mode,
                'status' => 'waiting',
                'launched_by' => $this->launchedBy,
                'started_at' => $startedAt,
                'wait_started_at' => $waitStartedAt,
                'phase' => 'Waiting for the active Whatnot pipeline to finish',
                'active_holder' => $holder,
                'next_retry_at' => now()->addSeconds(30)->toIso8601String(),
                'error' => null,
            ])));
            $this->release(30);
            return;
        }

        Setting::set('whatnot_ui_job', json_encode(array_merge($existing, [
            'mode'=>$this->mode,
            'status'=>'running',
            'launched_by'=>$this->launchedBy,
            'started_at'=>$startedAt,
            'phase'=>'Starting coordinated Scrapling pipeline',
            'active_holder'=>null,
            'next_retry_at'=>null,
        ])));
        try {
            $code = Artisan::call('whatnot:sync-reporting', $args);
            $output = trim(Artisan::output());

            // --skip-if-busy intentionally returns success for scheduled jobs,
            // but a UI run must never display that as a completed scrape.
            if (str_contains($output, 'Reporting sync skipped') || str_contains($output, 'Reporting sync did not start')) {
                $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
                Setting::set('whatnot_ui_job', json_encode(array_merge($state, [
                    'status' => 'waiting',
                    'phase' => 'Another Whatnot pipeline acquired the lock first; retrying automatically',
                    'output' => $output !== '' ? mb_substr($output, -12000) : null,
                    'wait_started_at' => $state['wait_started_at'] ?? now()->toIso8601String(),
                    'next_retry_at' => now()->addSeconds(30)->toIso8601String(),
                    'error' => null,
                ])));
                $this->release(30);
                return;
            }

            // The reporting command can catch channel-level failures and still
            // return exit code 0 so scheduled work can continue. For an explicit
            // UI run, surface those failures instead of showing a false green
            // COMPLETED badge.
            $failureMarkers = [
                'analytics backfill failed:',
                'discovery failed:',
                'order reconciliation failed:',
                'shipment reconciliation failed:',
                'BROWSER_LOCK_TIMEOUT',
                'Permission denied',
                'TargetClosedError',
            ];
            $detectedFailures = array_values(array_filter(
                $failureMarkers,
                fn (string $marker) => stripos($output, $marker) !== false
            ));

            if ($detectedFailures !== []) {
                $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
                Setting::set('whatnot_ui_job', json_encode(array_merge($state, [
                    'status' => 'failed',
                    'finished_at' => now()->toIso8601String(),
                    'phase' => 'Pipeline finished with scraper/import errors',
                    'output' => $output !== '' ? mb_substr($output, -12000) : null,
                    'error' => 'Detected: '.implode(', ', $detectedFailures),
                ])));
                return;
            }

            $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
            Setting::set('whatnot_ui_job', json_encode(array_merge($state, ['status'=>$code===0?'completed':'failed','finished_at'=>now()->toIso8601String(),'phase'=>$code===0?'Pipeline completed successfully':'Pipeline failed','output'=>$output !== '' ? mb_substr($output,-12000) : 'Command completed without captured console output.'])));
            if ($code !== 0) {
                $lines = preg_split('/\R/', $output) ?: [];
                $useful = array_values(array_filter($lines, fn ($line) =>
                    str_contains(strtolower($line), 'failed') ||
                    str_contains(strtolower($line), 'error') ||
                    str_contains(strtolower($line), 'did not start')
                ));
                $reason = trim((string) end($useful));
                throw new \RuntimeException($reason !== '' ? $reason : "Whatnot reporting exited with code {$code}");
            }
        } catch (\Throwable $e) {
            $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
            Setting::set('whatnot_ui_job', json_encode(array_merge($state, ['status'=>'failed','finished_at'=>now()->toIso8601String(),'error'=>$e->getMessage()])));
            throw $e;
        }
    }
}

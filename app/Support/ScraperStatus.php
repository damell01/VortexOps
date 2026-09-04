<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use Illuminate\Support\Carbon;

class ScraperStatus
{
    private const JOBS = [
        'whatnot_show_analytics' => [
            'label' => 'Shows + Analytics',
            'detail' => 'Discovers shows and refreshes top-level analytics for every enabled channel.',
            'every' => 'Hourly at :05',
            'stale_after_minutes' => 150,
            'channel_scoped' => true,
        ],
        'whatnot_orders' => [
            'label' => 'Recent Orders',
            'detail' => 'Refreshes recent shows that may still be missing order rows.',
            'every' => 'Every 30 minutes at :20 / :50',
            'stale_after_minutes' => 90,
            'channel_scoped' => true,
        ],
        'whatnot_shipments' => [
            'label' => 'Shipments',
            'detail' => 'Refreshes unresolved shipment status, tracking, package and carrier data.',
            'every' => 'Hourly at :35',
            'stale_after_minutes' => 150,
            'channel_scoped' => true,
        ],
        'whatnot_ledger' => [
            'label' => 'Rolling Ledger',
            'detail' => 'Reconciles the rolling 30-day Whatnot ledger window.',
            'every' => 'Every 6 hours at :10',
            'stale_after_minutes' => 480,
            'channel_scoped' => true,
        ],
        'whatnot_nightly_reconciliation' => [
            'label' => 'Nightly Reconciliation',
            'detail' => 'Repairs gaps across the last 30 days without blocking hourly ingestion.',
            'every' => 'Daily at 12:30 AM',
            'stale_after_minutes' => 1800,
            'channel_scoped' => false,
        ],
        'whatnot_deep_backfill' => [
            'label' => 'Deep Backfill',
            'detail' => 'Runs the expensive historical ledger backfill.',
            'every' => 'Sunday at 1:00 AM',
            'stale_after_minutes' => 11520,
            'channel_scoped' => false,
        ],
    ];

    public static function jobs(): array
    {
        $paused = static::isPaused();

        return collect(self::JOBS)->map(function (array $job, string $source) use ($paused) {
            $latest = ShowIngestionLog::query()
                ->where('source', $source)
                ->whereNull('whatnot_channel_id')
                ->latest('created_at')
                ->first();

            $lastSuccess = ShowIngestionLog::query()
                ->where('source', $source)
                ->whereNull('whatnot_channel_id')
                ->where('status', 'success')
                ->latest('created_at')
                ->first();

            $lastFailure = ShowIngestionLog::query()
                ->where('source', $source)
                ->whereNull('whatnot_channel_id')
                ->whereIn('status', ['failed', 'partial'])
                ->latest('created_at')
                ->first();

            $state = static::stateForLog($latest, $job['stale_after_minutes'], $paused);

            return [
                'key' => $source,
                'label' => $job['label'],
                'detail' => $job['detail'],
                'every' => $paused ? 'Paused' : $job['every'],
                'success_at' => $lastSuccess?->created_at,
                'failure_at' => $lastFailure?->created_at,
                'last_at' => $latest?->created_at,
                'last_status' => $latest?->status,
                'state' => $state,
                'note' => static::noteForLog($latest, $job['stale_after_minutes'], $paused),
                'error' => $latest?->error_message,
            ];
        })->values()->all();
    }

    public static function overall(): string
    {
        if (static::isPaused()) return 'paused';

        $states = array_column(static::jobs(), 'state');
        foreach (['failing', 'degraded', 'stale', 'unknown'] as $worst) {
            if (in_array($worst, $states, true)) return $worst;
        }
        return 'ok';
    }

    public static function isPaused(): bool
    {
        return ! config('vortex.whatnot.schedule_enabled', true);
    }

    public static function schedulerLastRanAt(): ?Carbon
    {
        return static::timestamp('scheduler_last_heartbeat');
    }

    public static function schedulerIsRunning(): bool
    {
        $at = static::schedulerLastRanAt();
        return $at !== null && $at->diffInMinutes(now()) < 15;
    }

    public static function session(): array
    {
        $path = config('vortex.whatnot.cookies_file')
            ?: app(\App\Services\WhatnotScraper::class)->cookiesFilePath();

        return [
            'exists' => file_exists($path),
            'path' => $path,
            'saved_at' => file_exists($path) ? Carbon::createFromTimestamp(filemtime($path)) : null,
        ];
    }

    public static function byChannel(): array
    {
        $channels = WhatnotChannel::orderBy('name')->get();
        $channelSources = collect(self::JOBS)->filter(fn ($job) => $job['channel_scoped'])->keys();

        return $channels->map(function (WhatnotChannel $channel) use ($channelSources) {
            $pipelineRecords = $channelSources->mapWithKeys(function (string $source) use ($channel) {
                $record = ShowIngestionLog::query()
                    ->where('whatnot_channel_id', $channel->id)
                    ->where('source', $source)
                    ->latest('created_at')
                    ->first();

                return [$source => $record];
            });

            $pipelines = $channelSources->map(function (string $source) use ($pipelineRecords) {
                $record = $pipelineRecords->get($source);

                return [
                    'source' => $source,
                    'label' => self::JOBS[$source]['label'],
                    'status' => $record?->status ?? 'unknown',
                    'at' => $record?->created_at,
                    'error' => $record?->error_message,
                ];
            })->values()->all();

            $latest = $pipelineRecords
                ->filter()
                ->sortByDesc(fn (ShowIngestionLog $record) => $record->created_at)
                ->first();

            // Count only pipelines whose CURRENT state is bad. Historical/legacy
            // failures stay in the detailed log, but they must not make a healthy
            // channel look like it currently has 10+ broken jobs.
            $currentProblems = $pipelineRecords
                ->filter(fn (?ShowIngestionLog $record) => $record && in_array($record->status, ['failed', 'partial'], true))
                ->count();

            return [
                'channel' => $channel,
                'last_at' => $latest?->created_at,
                'failures_24h' => $currentProblems,
                'pipelines' => $pipelines,
            ];
        })->all();
    }

    private static function stateForLog(?ShowIngestionLog $latest, int $staleAfter, bool $paused): string
    {
        if ($paused) return 'paused';
        if (! $latest) return 'unknown';
        if ($latest->status === 'failed') return 'failing';
        if ($latest->status === 'partial') return 'degraded';
        return $latest->created_at->diffInMinutes(now()) >= $staleAfter ? 'stale' : 'ok';
    }

    private static function noteForLog(?ShowIngestionLog $latest, int $staleAfter, bool $paused): string
    {
        if ($paused) return 'The schedule is switched off.';
        if (! $latest) return 'No run has been recorded for this pipeline yet.';

        $when = $latest->created_at->diffForHumans();
        return match ($latest->status) {
            'failed' => 'Last attempt failed ' . $when . '.',
            'partial' => 'Last attempt partially completed ' . $when . '.',
            default => $latest->created_at->diffInMinutes(now()) >= $staleAfter
                ? 'Last successful run was ' . $when . ' — later than expected.'
                : 'Last successful run was ' . $when . '.',
        };
    }

    private static function timestamp(string $key): ?Carbon
    {
        return static::parse(Setting::get($key));
    }

    private static function parse(mixed $value): ?Carbon
    {
        if (blank($value)) return null;
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

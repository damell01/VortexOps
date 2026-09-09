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
            'every' => 'Every 2 hours',
            'stale_after_minutes' => 180,
            'channel_scoped' => true,
        ],
        'whatnot_orders' => [
            'label' => 'Recent Orders',
            'detail' => 'Refreshes recent shows that may still be missing order rows.',
            'every' => 'Every 2 hours',
            'stale_after_minutes' => 180,
            'channel_scoped' => true,
        ],
        'whatnot_shipments' => [
            'label' => 'Shipments',
            'detail' => 'Refreshes unresolved shipment status, tracking, package and carrier data.',
            'every' => 'Every 2 hours',
            'stale_after_minutes' => 180,
            'channel_scoped' => true,
        ],
        'whatnot_ledger' => [
            'label' => 'Rolling Ledger',
            'detail' => 'Reconciles ledger/post-show adjustments inside the coordinated reporting pipeline.',
            'every' => 'Coordinated reporting',
            'stale_after_minutes' => 480,
            'channel_scoped' => true,
        ],
        'whatnot_nightly_reconciliation' => [
            'label' => 'Nightly Reconciliation',
            'detail' => 'Runs a wider reconciliation pass to catch older reporting gaps.',
            'every' => 'Nightly',
            'stale_after_minutes' => 1800,
            'channel_scoped' => false,
        ],
        'whatnot_deep_backfill' => [
            'label' => 'Deep Backfill',
            'detail' => 'Historical repair work that runs only when needed.',
            'every' => 'On demand',
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
        $path = static::sessionPath();

        return [
            'exists' => file_exists($path),
            'path' => $path,
            'saved_at' => file_exists($path) ? Carbon::createFromTimestamp(filemtime($path)) : null,
        ];
    }

    private static function sessionPath(): string
    {
        if ($configured = config('vortex.whatnot.cookies_file')) {
            return $configured;
        }

        $bootstrap = storage_path('whatnot-cookies.json');
        $live = storage_path('whatnot-live-cookies.json');
        $bootstrapMtime = file_exists($bootstrap) ? (filemtime($bootstrap) ?: 0) : 0;
        $liveMtime = file_exists($live) ? (filemtime($live) ?: 0) : 0;

        return $liveMtime > $bootstrapMtime ? $live : $bootstrap;
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

<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use Illuminate\Support\Carbon;

/**
 * Is the Whatnot importer working, and when did it last do anything?
 *
 * The scheduler has been recording the answer all along. routes/console.php
 * hangs onSuccess/onFailure handlers on three of its jobs, and of the five
 * timestamps they write only one — whatnot_last_import_success_at — was ever
 * read back. So a job that had been failing every ten minutes for two days
 * showed up nowhere: the success time simply stopped moving, and nothing in
 * the app said why, or that a failure was the reason.
 *
 * This reads all of them, plus the two things that explain most failures:
 * whether the schedule is switched off, and how old the stored session is.
 * Cloudflare will not let the scraper sign itself in, so an expired cookie
 * file is a dead importer until a person refreshes it.
 */
class ScraperStatus
{
    /**
     * The scheduled jobs that record an outcome, in the order they matter.
     *
     * Keyed by the settings prefix each one writes; the cron strings mirror
     * routes/console.php so the page can say when the next attempt is due
     * without anyone reading the schedule file.
     */
    private const JOBS = [
        'whatnot_last_import' => [
            'label'    => 'Show index',
            'detail'   => 'Pulls the list of completed shows and their headline numbers.',
            'every'    => 'Every 10 minutes',
            'stale_after_minutes' => 30,
        ],
        'whatnot_last_recent_refresh' => [
            'label'    => 'Recent-show refresh',
            'detail'   => 'Re-pulls analytics and shipments for shows from the last 30 days.',
            'every'    => 'Twice an hour',
            'stale_after_minutes' => 180,
        ],
    ];

    /**
     * @return array<int, array{key:string,label:string,detail:string,every:string,
     *     success_at:?Carbon,failure_at:?Carbon,state:string,note:string}>
     */
    public static function jobs(): array
    {
        $paused = static::isPaused();

        return collect(self::JOBS)->map(function (array $job, string $prefix) use ($paused) {
            $success = static::timestamp("{$prefix}_success_at");
            $failure = static::timestamp("{$prefix}_failure_at");

            return [
                'key'        => $prefix,
                'label'      => $job['label'],
                'detail'     => $job['detail'],
                'every'      => $paused ? 'Paused' : $job['every'],
                'success_at' => $success,
                'failure_at' => $failure,
                'state'      => static::stateFor($success, $failure, $job['stale_after_minutes'], $paused),
                'note'       => static::noteFor($success, $failure, $job['stale_after_minutes'], $paused),
            ];
        })->values()->all();
    }

    /**
     * One word for the whole importer, for a heading or a badge.
     */
    public static function overall(): string
    {
        if (static::isPaused()) {
            return 'paused';
        }

        $states = array_column(static::jobs(), 'state');

        foreach (['failing', 'stale', 'unknown'] as $worst) {
            if (in_array($worst, $states, true)) {
                return $worst;
            }
        }

        return 'ok';
    }

    public static function isPaused(): bool
    {
        return ! config('vortex.whatnot.schedule_enabled', true);
    }

    /**
     * The scheduler itself. A silent importer usually means cron stopped, and
     * that looks identical from the importer's side to "nothing to import".
     */
    public static function schedulerLastRanAt(): ?Carbon
    {
        return static::timestamp('scheduler_last_heartbeat');
    }

    public static function schedulerIsRunning(): bool
    {
        $at = static::schedulerLastRanAt();

        // The heartbeat is written every five minutes; fifteen allows for a
        // slow run without calling a healthy scheduler dead.
        return $at !== null && $at->diffInMinutes(now()) < 15;
    }

    /**
     * Age of the stored session, which is the first thing to check when every
     * job starts failing at once.
     *
     * @return array{exists:bool,path:string,saved_at:?Carbon}
     */
    public static function session(): array
    {
        $path = config('vortex.whatnot.cookies_file')
            ?: app(\App\Services\WhatnotScraper::class)->cookiesFilePath();

        return [
            'exists'   => file_exists($path),
            'path'     => $path,
            'saved_at' => file_exists($path) ? Carbon::createFromTimestamp(filemtime($path)) : null,
        ];
    }

    /**
     * Per-channel ingestion, for the summary strip above the table.
     *
     * @return array<int, array{channel:WhatnotChannel,last_at:?Carbon,last_success_at:?Carbon,
     *     failures_24h:int,runs_24h:int}>
     */
    public static function byChannel(): array
    {
        $channels = WhatnotChannel::orderBy('name')->get();

        if ($channels->isEmpty()) {
            return [];
        }

        $ids = $channels->pluck('id');

        $latest = ShowIngestionLog::selectRaw('whatnot_channel_id, MAX(created_at) AS at')
            ->whereIn('whatnot_channel_id', $ids)
            ->groupBy('whatnot_channel_id')
            ->pluck('at', 'whatnot_channel_id');

        $latestOk = ShowIngestionLog::selectRaw('whatnot_channel_id, MAX(created_at) AS at')
            ->whereIn('whatnot_channel_id', $ids)
            ->where('status', 'success')
            ->groupBy('whatnot_channel_id')
            ->pluck('at', 'whatnot_channel_id');

        $since = now()->subDay();

        $runs = ShowIngestionLog::selectRaw('whatnot_channel_id, COUNT(*) AS c')
            ->whereIn('whatnot_channel_id', $ids)
            ->where('created_at', '>=', $since)
            ->groupBy('whatnot_channel_id')
            ->pluck('c', 'whatnot_channel_id');

        $failures = ShowIngestionLog::selectRaw('whatnot_channel_id, COUNT(*) AS c')
            ->whereIn('whatnot_channel_id', $ids)
            ->where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->groupBy('whatnot_channel_id')
            ->pluck('c', 'whatnot_channel_id');

        return $channels->map(fn (WhatnotChannel $channel) => [
            'channel'         => $channel,
            'last_at'         => static::parse($latest[$channel->id] ?? null),
            'last_success_at' => static::parse($latestOk[$channel->id] ?? null),
            'runs_24h'        => (int) ($runs[$channel->id] ?? 0),
            'failures_24h'    => (int) ($failures[$channel->id] ?? 0),
        ])->all();
    }

    // ── internals ─────────────────────────────────────────────────────────────

    private static function stateFor(?Carbon $success, ?Carbon $failure, int $staleAfter, bool $paused): string
    {
        if ($paused) {
            return 'paused';
        }

        if (! $success && ! $failure) {
            return 'unknown';
        }

        // A failure newer than the last success is the live state, whatever
        // the success timestamp says.
        if ($failure && (! $success || $failure->greaterThan($success))) {
            return 'failing';
        }

        return $success->diffInMinutes(now()) >= $staleAfter ? 'stale' : 'ok';
    }

    private static function noteFor(?Carbon $success, ?Carbon $failure, int $staleAfter, bool $paused): string
    {
        return match (static::stateFor($success, $failure, $staleAfter, $paused)) {
            'paused'  => 'The schedule is switched off (WHATNOT_SCHEDULE_ENABLED).',
            'unknown' => 'Has not run since this was set up.',
            'failing' => 'Last attempt failed ' . $failure->diffForHumans()
                . ($success ? ', last worked ' . $success->diffForHumans() : ', has never succeeded') . '.',
            'stale'   => 'Last succeeded ' . $success->diffForHumans() . ' — longer ago than expected.',
            default   => 'Last succeeded ' . $success->diffForHumans() . '.',
        };
    }

    private static function timestamp(string $key): ?Carbon
    {
        return static::parse(Setting::get($key));
    }

    private static function parse(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            // A hand-edited settings row should not take the page down.
            return null;
        }
    }
}

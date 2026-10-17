<?php

namespace App\Support;

use App\Jobs\RunWhatnotReportingJob;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Illuminate\Support\Facades\Process;

/**
 * "Run now" on Scraper Status: stop whatever Whatnot scraping is going on and
 * start a fresh run straight away, without waiting on a queue worker.
 *
 * Stopping is two-handed. Processes on this machine are ended directly (the
 * browser owner's process tree, and the pipeline holder). Anything we can't
 * reach — a run in the scheduler container, say — sees the stop request on
 * its next line of progress and unwinds itself (SyncWhatnotReporting).
 */
class WhatnotRunControl
{
    public const STOP_KEY = 'whatnot_stop_requested_at';

    public static function requestStop(): void
    {
        Setting::set(self::STOP_KEY, (string) microtime(true));
    }

    /** Has a stop been asked for since a run that began at $startedAt (microtime)? */
    public static function stopRequestedSince(float $startedAt): bool
    {
        return (float) Setting::get(self::STOP_KEY, 0) > $startedAt;
    }

    /** Stop everything and clear the locks. Returns a line saying what was done. */
    public static function stopEverything(int $waitSeconds = 8): string
    {
        self::requestStop();
        $done = [];

        $browser = WhatnotBrowserLock::stopActiveOwner();
        if (! empty($browser['killed_pids'])) {
            $done[] = 'stopped the browser';
        }

        $holder = WhatnotPipelineLock::holder();
        if ($holder && $holder['alive'] && function_exists('posix_kill')) {
            @posix_kill($holder['pid'], defined('SIGTERM') ? SIGTERM : 15);
            $done[] = 'stopped the running sync';
        }

        // A run somewhere else stops on its next progress line; give it a moment
        // to let go of the lock cleanly before taking it away.
        for ($i = 0; $i < $waitSeconds * 2 && Cache::has(WhatnotPipelineLock::HOLDER_KEY); $i++) {
            usleep(500_000);
            $holder = WhatnotPipelineLock::holder();
            if ($holder && $holder['alive'] && $i === 6 && function_exists('posix_kill')) {
                @posix_kill($holder['pid'], defined('SIGKILL') ? SIGKILL : 9);
            }
        }

        WhatnotPipelineLock::forceRelease();
        WhatnotBrowserLock::forceRelease();

        // Runs queued earlier would otherwise start on top of this one later.
        $cleared = self::clearQueuedRuns();
        if ($cleared) {
            $done[] = "cancelled {$cleared} queued " . Str::plural('run', $cleared);
        }

        return $done ? ucfirst(implode(', ', $done)) . '.' : 'Nothing else was running.';
    }

    public static function clearQueuedRuns(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        try {
            return DB::table(config('queue.connections.database.table', 'jobs'))
                ->where('payload', 'like', '%RunWhatnotReportingJob%')
                ->whereNull('reserved_at')
                ->delete();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Start a run in its own background process, right now. Falls back to the
     * queue only if PHP can't be found to launch it.
     *
     * @return bool true if it was launched directly, false if it was queued
     */
    public static function launch(string $mode, int $userId, string $runId): bool
    {
        $php = (new PhpExecutableFinder())->find(false);
        if (! $php) {
            return self::queue($mode, $userId, $runId);
        }

        $command = implode(' ', array_map('escapeshellarg', [$php, base_path('artisan'), 'whatnot:run-now', $mode, "--user={$userId}", "--run={$runId}"]));
        $log = escapeshellarg(storage_path('logs/whatnot-run-now.log'));
        // setsid + nohup: the run outlives this web request and its worker.
        $shell = "if command -v setsid >/dev/null 2>&1; then setsid nohup {$command} >{$log} 2>&1 </dev/null & else nohup {$command} >{$log} 2>&1 </dev/null & fi";

        $result = Process::path(base_path())->timeout(10)->run($shell);

        return $result->successful() ? true : self::queue($mode, $userId, $runId);
    }

    private static function queue(string $mode, int $userId, string $runId): bool
    {
        RunWhatnotReportingJob::dispatch($mode, $userId, $runId);

        return false;
    }
}

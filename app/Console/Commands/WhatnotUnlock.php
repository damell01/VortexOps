<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WhatnotUnlock extends Command
{
    protected $signature = 'whatnot:unlock {--force : Skip the liveness check and release unconditionally}';

    protected $description = 'Clear a stuck whatnot:browser lock left behind by a process killed before it could release cleanly (pkill -9, Ctrl+C, OOM)';

    public function handle(): int
    {
        $pid = Cache::get('whatnot:browser:holder_pid');

        if ($pid && ! $this->option('force') && $this->pidIsAlive((int) $pid)) {
            $this->error("PID {$pid} (recorded as holding the lock) is still alive — a scraper run looks genuinely in progress.");
            $this->line('Confirm with `ps aux | grep whatnot-scraper` before forcing this — releasing a lock that\'s actually in use lets a second Chrome instance start against the same profile and corrupts it.');
            $this->line('If you\'re certain it\'s not real, re-run with --force.');
            return self::FAILURE;
        }

        Cache::lock('whatnot:browser')->forceRelease();
        Cache::forget('whatnot:browser:holder_pid');

        $this->info($pid
            ? "Lock released — holder PID {$pid} was not alive."
            : 'Lock released (no holder PID was recorded — it may have predated this tracking, or already been cleared).');

        return self::SUCCESS;
    }

    private function pidIsAlive(int $pid): bool
    {
        // /proc/<pid> existence works across users (readable regardless of who
        // owns the process, unlike posix_kill()'s permission-gated result) and
        // this app only ever runs on Linux, so no portability fallback needed.
        return is_dir("/proc/{$pid}");
    }
}

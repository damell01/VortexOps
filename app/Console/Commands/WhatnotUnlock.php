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
            $command = $this->commandLine((int) $pid);

            // A live PID is not proof the scraper is running. PIDs are recycled,
            // and the recorded one can be reassigned to something entirely
            // unrelated — at which point refusing to release the lock blocks
            // every future run for ever. Ask what the process actually is.
            if (! $this->looksLikeAWhatnotJob($command)) {
                $this->warn("PID {$pid} is alive but is not a Whatnot job — the PID has been reused.");
                $this->line('  <fg=gray>' . ($command ?: 'command line unavailable') . '</>');
                $this->line('  <fg=gray>Releasing: the run that recorded this PID is long gone.</>');
                $this->newLine();

                return $this->release($pid);
            }

            $this->error("PID {$pid} is a Whatnot job and is still running. Not releasing the lock.");
            $this->line('  <fg=gray>' . $command . '</>');

            if ($started = $this->runningSince((int) $pid)) {
                $this->line("  <fg=gray>Running for {$started}.</>");
            }

            $this->newLine();
            $this->line('Let it finish, or stop it with <comment>kill ' . $pid . '</comment> and run this again.');
            $this->line('Forcing the lock open while it really is running lets a second Chrome start');
            $this->line('against the same profile, which corrupts the saved Whatnot session.');

            return self::FAILURE;
        }

        return $this->release($pid);
    }

    private function release(mixed $pid): int
    {
        Cache::lock('whatnot:browser')->forceRelease();
        Cache::forget('whatnot:browser:holder_pid');

        $this->info($pid
            ? "Lock released — holder PID {$pid} is not a running Whatnot job."
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

    /** The process's argv, space-separated, or null if it cannot be read. */
    private function commandLine(int $pid): ?string
    {
        $raw = @file_get_contents("/proc/{$pid}/cmdline");

        if ($raw === false || $raw === '') {
            return null;
        }

        return trim(str_replace("\0", ' ', $raw)) ?: null;
    }

    /**
     * Whether a command line belongs to something that drives the browser.
     *
     * Deliberately generous: an unreadable command line, or one this does not
     * recognise, is treated as a real job. Refusing to unlock costs a support
     * question; releasing a lock that is genuinely held corrupts the profile.
     */
    private function looksLikeAWhatnotJob(?string $command): bool
    {
        if ($command === null) {
            return true;
        }

        return (bool) preg_match('/whatnot|artisan|chrome|chromium|playwright/i', $command);
    }

    private function runningSince(int $pid): ?string
    {
        $stat = @stat("/proc/{$pid}");

        if ($stat === false) {
            return null;
        }

        return now()->setTimestamp($stat['ctime'])->diffForHumans(syntax: true);
    }
}

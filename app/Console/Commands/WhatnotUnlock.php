<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WhatnotUnlock extends Command
{
    protected $signature = 'whatnot:unlock {--force : Release unconditionally, and kill an orphaned Chrome still holding the profile}';

    protected $description = 'Clear a stuck whatnot:browser lock left behind by a process killed before it could release cleanly (pkill -9, Ctrl+C, OOM)';

    public function handle(): int
    {
        $holder = \App\Support\WhatnotBrowserLock::holder();
        $pid    = $holder['pid'] ?? null;

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
        \App\Support\WhatnotBrowserLock::forceRelease();

        $this->info($pid
            ? "Lock released — holder PID {$pid} is not a running Whatnot job."
            : 'Lock released (no holder PID was recorded — it may have predated this tracking, or already been cleared).');

        $this->clearChromeProfileLock();

        return self::SUCCESS;
    }

    /**
     * Chrome keeps its own lock on the profile, and killing a run leaves it.
     *
     * There are two locks over the same resource. Releasing only the cache lock
     * let the next run start and then fail at launch with "Failed to create a
     * ProcessSingleton for your profile directory" — which reads like a broken
     * install rather than the leftover it is, and no amount of whatnot:unlock
     * fixed it. The files are only removed once we have already established
     * that nothing is holding the profile.
     */
    private function clearChromeProfileLock(): void
    {
        $profile = storage_path('whatnot-browser-profile');
        $removed = [];

        foreach (['SingletonLock', 'SingletonSocket', 'SingletonCookie'] as $name) {
            $path = $profile . '/' . $name;

            // is_link first: SingletonLock is a dangling symlink whose target
            // encodes hostname-pid, so file_exists() reports false for it.
            if (! is_link($path) && ! file_exists($path)) {
                continue;
            }

            if ($holder = $this->chromeProfileHolder($path)) {
                // We only reach here having already decided no artisan job owns
                // the cache lock. A Chrome sitting on the profile with no job
                // behind it is an orphan — Playwright's browser outlives the
                // node process when a run is interrupted, and then nothing can
                // ever scrape again until somebody kills it by hand.
                if ($this->isOurChrome($holder) && $this->option('force')) {
                    posix_kill($holder, SIGTERM);
                    usleep(500_000);

                    if ($this->pidIsAlive($holder)) {
                        posix_kill($holder, SIGKILL);
                        usleep(250_000);
                    }

                    $this->info("Killed orphaned Chrome (PID {$holder}) — it was holding the profile with no job behind it.");

                    if (@unlink($path)) {
                        $removed[] = $name;
                    }

                    continue;
                }

                $this->warn("Chrome still holds the profile (PID {$holder}) — left {$name} in place.");

                if ($this->isOurChrome($holder)) {
                    $this->line('  <fg=gray>No artisan job owns the browser lock, so this is an orphan left by an</>');
                    $this->line('  <fg=gray>interrupted run: Playwright\'s Chrome outlives the node process that</>');
                    $this->line('  <fg=gray>started it. Clear it with php artisan whatnot:unlock --force.</>');
                } else {
                    $this->line('  <fg=gray>That process is not using this profile. Stop it before running a scrape,</>');
                    $this->line('  <fg=gray>or the profile can be corrupted.</>');
                }

                return;
            }

            if (@unlink($path)) {
                $removed[] = $name;
            }
        }

        if ($removed !== []) {
            $this->info('Cleared Chrome\'s stale profile lock (' . implode(', ', $removed) . ').');
        }
    }

    /**
     * Whether that PID is a browser running against *this* profile.
     *
     * Checked before killing anything: the PID comes out of a file Chrome
     * wrote, and a recycled PID would otherwise make this kill a stranger.
     */
    private function isOurChrome(int $pid): bool
    {
        $command = $this->commandLine($pid);

        if ($command === null) {
            return false;
        }

        return str_contains($command, storage_path('whatnot-browser-profile'))
            && preg_match('/chrome|chromium/i', $command) === 1;
    }

    /** The live PID recorded in a SingletonLock symlink, if there is one. */
    private function chromeProfileHolder(string $path): ?int
    {
        if (! is_link($path)) {
            return null;
        }

        // Target looks like "hostname-12345".
        $target = @readlink($path);

        if ($target === false || ! preg_match('/-(\d+)$/', $target, $matches)) {
            return null;
        }

        $pid = (int) $matches[1];

        return $this->pidIsAlive($pid) ? $pid : null;
    }

    private function pidIsAlive(int $pid): bool
    {
        return \App\Support\WhatnotBrowserLock::pidIsAlive($pid);
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

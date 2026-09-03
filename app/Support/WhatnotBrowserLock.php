<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The one lock over the Scrapling-owned Whatnot browser profile.
 */
class WhatnotBrowserLock
{
    public const KEY = 'whatnot:browser';
    public const TTL = 13800;

    public static function owner(): string
    {
        return getmypid() . '@' . gethostname();
    }

    public static function make(?int $ttl = null): Lock
    {
        // Every scraper operation comes through make(). Recover here instead of
        // only at the beginning of the hourly pipeline, because a child can die
        // between shows/orders/shipments/ledger and leave a stale lock behind.
        $recovery = self::recoverIfStale();

        if ($recovery['recovered']) {
            Log::warning('WhatnotBrowserLock: automatically recovered stale Scrapling browser state', [
                'stale_holder_pid' => $recovery['holder_pid'],
                'killed_orphan_browser_pids' => $recovery['killed_pids'],
                'removed_profile_locks' => $recovery['removed'],
            ]);
        }

        return Cache::lock(self::KEY, $ttl ?? self::TTL, self::owner());
    }

    /** @return array{pid:int,host:string,alive:bool}|null */
    public static function holder(): ?array
    {
        $owner = self::storedOwner();

        if ($owner === null || ! preg_match('/^(\d+)@(.*)$/', $owner, $matches)) {
            $legacy = Cache::get(self::KEY . ':holder_pid');

            return $legacy
                ? ['pid' => (int) $legacy, 'host' => gethostname(), 'alive' => self::pidIsAlive((int) $legacy)]
                : null;
        }

        $pid = (int) $matches[1];

        return [
            'pid' => $pid,
            'host' => $matches[2],
            'alive' => $matches[2] === gethostname() && self::pidIsAlive($pid),
        ];
    }

    public static function isHeld(): bool
    {
        return self::storedOwner() !== null;
    }

    public static function forceRelease(): void
    {
        Cache::lock(self::KEY)->forceRelease();
        Cache::forget(self::KEY . ':holder_pid');
    }

    /**
     * Recover only when the cache lock's recorded local owner is definitely dead.
     * The separate persistent browser service/profile is deliberately not part of
     * this recovery path; production scraping uses Scrapling's owned profile.
     *
     * @return array{recovered:bool,holder_pid:?int,killed_pids:array<int,int>,removed:array<int,string>}
     */
    public static function recoverIfStale(): array
    {
        $result = [
            'recovered' => false,
            'holder_pid' => null,
            'killed_pids' => [],
            'removed' => [],
        ];

        if (! self::isHeld()) {
            return $result;
        }

        $holder = self::holder();
        if ($holder === null) {
            return $result;
        }

        $result['holder_pid'] = $holder['pid'];

        if ($holder['host'] !== gethostname() || $holder['alive']) {
            return $result;
        }

        self::forceRelease();
        $result['recovered'] = true;
        self::recoverProfile(storage_path('whatnot-scrapling-profile'), $result);

        return $result;
    }

    /**
     * True only for a real non-zombie PID. /proc existence alone is insufficient
     * because zombies retain a proc entry until their parent reaps them.
     */
    public static function pidIsAlive(int $pid): bool
    {
        if ($pid <= 0 || ! is_dir("/proc/{$pid}")) {
            return false;
        }

        $stat = @file_get_contents("/proc/{$pid}/stat");
        if (is_string($stat) && $stat !== '') {
            $close = strrpos($stat, ') ');
            if ($close !== false && isset($stat[$close + 2])) {
                return strtoupper($stat[$close + 2]) !== 'Z';
            }
        }

        $status = @file_get_contents("/proc/{$pid}/status");
        if (is_string($status) && preg_match('/^State:\s*([A-Z])/mi', $status, $matches) === 1) {
            return strtoupper($matches[1]) !== 'Z';
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        // If procfs exists but cannot be inspected, leave the lock alone rather
        // than risk disrupting legitimate work.
        return true;
    }

    /** @param array{recovered:bool,holder_pid:?int,killed_pids:array<int,int>,removed:array<int,string>} $result */
    private static function recoverProfile(string $profile, array &$result): void
    {
        if (! is_dir($profile)) {
            return;
        }

        foreach (['SingletonLock', 'SingletonSocket', 'SingletonCookie'] as $name) {
            $path = $profile . '/' . $name;

            if (! is_link($path) && ! file_exists($path)) {
                continue;
            }

            $pid = self::chromeProfileHolder($path);

            if ($pid !== null) {
                // Only terminate Chromium that is actually using Scrapling's exact
                // profile. Never kill unrelated Chrome processes on the server.
                if (! self::isOurChrome($pid, $profile)) {
                    continue;
                }

                @posix_kill($pid, SIGTERM);
                usleep(500_000);

                if (self::pidIsAlive($pid)) {
                    @posix_kill($pid, SIGKILL);
                    usleep(250_000);
                }

                if (self::pidIsAlive($pid)) {
                    continue;
                }

                $result['killed_pids'][] = $pid;
            }

            if (@unlink($path)) {
                $result['removed'][] = $path;
            }
        }
    }

    private static function chromeProfileHolder(string $path): ?int
    {
        if (! is_link($path)) {
            return null;
        }

        $target = @readlink($path);
        if ($target === false || ! preg_match('/-(\d+)$/', $target, $matches)) {
            return null;
        }

        $pid = (int) $matches[1];
        return self::pidIsAlive($pid) ? $pid : null;
    }

    private static function isOurChrome(int $pid, string $profile): bool
    {
        $command = self::commandLine($pid);

        return $command !== null
            && str_contains($command, $profile)
            && preg_match('/chrome|chromium/i', $command) === 1;
    }

    private static function commandLine(int $pid): ?string
    {
        $raw = @file_get_contents("/proc/{$pid}/cmdline");
        if ($raw === false || $raw === '') {
            return null;
        }

        return trim(str_replace("\0", ' ', $raw)) ?: null;
    }

    protected static function storedOwner(): ?string
    {
        $lock = Cache::lock(self::KEY);

        try {
            $owner = \Closure::bind(
                fn () => $this->getCurrentOwner(),
                $lock,
                $lock::class,
            )();
        } catch (\Throwable) {
            return null;
        }

        return is_string($owner) && $owner !== '' ? $owner : null;
    }
}

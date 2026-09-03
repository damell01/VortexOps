<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * The one lock over the one browser, and a straight answer about who holds it.
 */
class WhatnotBrowserLock
{
    public const KEY = 'whatnot:browser';

    /** Long enough to outlast the slowest legitimate scrape. */
    public const TTL = 13800;

    /** This process, in a form another process can read back. */
    public static function owner(): string
    {
        return getmypid() . '@' . gethostname();
    }

    public static function make(?int $ttl = null): Lock
    {
        return Cache::lock(self::KEY, $ttl ?? self::TTL, self::owner());
    }

    /**
     * Who holds the lock right now.
     *
     * @return array{pid:int,host:string,alive:bool}|null
     */
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
            'pid'   => $pid,
            'host'  => $matches[2],
            'alive' => $matches[2] === gethostname() && self::pidIsAlive($pid),
        ];
    }

    /** Whether anything holds the lock, regardless of who. */
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
     * Recover a lock whose recorded owner is definitely gone.
     *
     * This is intentionally conservative. A live Whatnot owner is never touched.
     * When the owner PID is dead, the cache lock cannot represent useful work any
     * longer, so it is safe to release. We then clean Chrome profile locks left by
     * browser children that outlived the dead scraper, but never kill the managed
     * persistent browser service.
     *
     * @return array{recovered:bool,holder_pid:?int,killed_pids:array<int,int>,removed:array<int,string>}
     */
    public static function recoverIfStale(): array
    {
        $result = [
            'recovered'  => false,
            'holder_pid' => null,
            'killed_pids'=> [],
            'removed'    => [],
        ];

        if (! self::isHeld()) {
            return $result;
        }

        $holder = self::holder();

        // An unattributable lock is left alone. Its TTL is the final safety net.
        if ($holder === null) {
            return $result;
        }

        $result['holder_pid'] = $holder['pid'];

        // A lock from another host or a live local owner may still be legitimate.
        if ($holder['host'] !== gethostname() || $holder['alive']) {
            return $result;
        }

        self::forceRelease();
        $result['recovered'] = true;

        foreach (self::profileDirectories() as $profile) {
            self::recoverProfile($profile, $result);
        }

        return $result;
    }

    /**
     * A process that has exited but not been reaped keeps its /proc entry, so
     * existence alone counts a dead job as a live one and leaves the lock stuck.
     */
    public static function pidIsAlive(int $pid): bool
    {
        if ($pid <= 0 || ! is_dir("/proc/{$pid}")) {
            return false;
        }

        $status = @file_get_contents("/proc/{$pid}/status");

        return $status === false || preg_match('/^State:\s*Z/m', $status) !== 1;
    }

    /** @return array<int,string> */
    private static function profileDirectories(): array
    {
        return array_values(array_unique([
            storage_path('whatnot-scrapling-profile'),
            storage_path('whatnot-browser-profile'),
        ]));
    }

    /**
     * @param array{recovered:bool,holder_pid:?int,killed_pids:array<int,int>,removed:array<int,string>} $result
     */
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
                // Only touch a Chrome/Chromium process using exactly this VortexOps
                // profile. Never stop the persistent systemd-owned browser service.
                if (! self::isOurChrome($pid, $profile) || self::isManagedBrowserService($pid)) {
                    continue;
                }

                @posix_kill($pid, SIGTERM);
                usleep(500_000);

                if (self::pidIsAlive($pid)) {
                    @posix_kill($pid, SIGKILL);
                    usleep(250_000);
                }

                if (self::pidIsAlive($pid)) {
                    // Permissions or some other protection prevented termination.
                    // Do not unlink Chrome's live lock underneath it.
                    continue;
                }

                $result['killed_pids'][] = $pid;
            }

            if (@unlink($path)) {
                $result['removed'][] = $profile . '/' . $name;
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

    /**
     * The persistent browser service is supposed to live without an Artisan
     * parent. Its cgroup is the reliable distinction between that managed
     * process and an orphaned browser child from an interrupted scrape.
     */
    private static function isManagedBrowserService(int $pid): bool
    {
        $cgroup = @file_get_contents("/proc/{$pid}/cgroup");

        return is_string($cgroup)
            && str_contains($cgroup, 'vortexops-whatnot-browser.service');
    }

    private static function commandLine(int $pid): ?string
    {
        $raw = @file_get_contents("/proc/{$pid}/cmdline");

        if ($raw === false || $raw === '') {
            return null;
        }

        return trim(str_replace("\0", ' ', $raw)) ?: null;
    }

    /** The owner token currently stored against the lock. */
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

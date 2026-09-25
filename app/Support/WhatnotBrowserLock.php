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

    /** @return array{ok:bool,pid:?int,message:string,killed_pids:array<int,int>,removed:array<int,string>} */
    public static function stopActiveOwner(): array
    {
        $holder = self::holder();
        if (! $holder) {
            self::forceRelease();
            return ['ok' => true, 'pid' => null, 'message' => 'Browser lock was already free.', 'killed_pids' => [], 'removed' => []];
        }

        if ($holder['host'] !== gethostname()) {
            return ['ok' => false, 'pid' => $holder['pid'], 'message' => 'Browser owner is on another host; refusing to terminate it.', 'killed_pids' => [], 'removed' => []];
        }

        $pid = (int) $holder['pid'];
        $originalIdentity = self::processIdentity($pid);
        if ($holder['alive']) {
            if (! function_exists('posix_kill')) {
                return ['ok' => false, 'pid' => $pid, 'message' => 'PHP POSIX process control is unavailable on this server; active owner was not stopped.', 'killed_pids' => [], 'removed' => []];
            }

            // The lock owner can be a wrapper (flock/PHP/Python) whose parent and
            // children keep one another alive. Stop only the tightly-related
            // Whatnot process tree, never arbitrary Chrome/PHP processes.
            $tree = self::relatedWhatnotProcessTree($pid);
            // Children first, lock owner last. Killing the wrapper first can
            // orphan its scraper/browser descendants.
            foreach ($tree as $target) {
                @posix_kill($target, defined('SIGTERM') ? SIGTERM : 15);
            }

            for ($i = 0; $i < 20 && self::pidIsAlive($pid); $i++) {
                usleep(250_000);
            }

            if (self::pidIsAlive($pid)) {
                foreach ($tree as $target) {
                    if (self::pidIsAlive($target)) {
                        @posix_kill($target, defined('SIGKILL') ? SIGKILL : 9);
                    }
                }
                usleep(500_000);
            }

            if (self::pidIsAlive($pid) && self::sameProcessIdentity($pid, $originalIdentity)) {
                return [
                    'ok' => false,
                    'pid' => $pid,
                    'message' => "Whatnot process tree containing PID {$pid} did not stop; lock was left intact.",
                    'killed_pids' => array_values(array_filter($tree, fn (int $target) => ! self::pidIsAlive($target))),
                    'removed' => [],
                ];
            }
        }

        self::forceRelease();
        $result = ['recovered' => true, 'holder_pid' => $pid, 'killed_pids' => [], 'removed' => []];
        self::recoverProfile(storage_path('whatnot-scrapling-profile'), $result);

        return ['ok' => true, 'pid' => $pid, 'message' => "Stopped Whatnot process tree for PID {$pid} and released its lock.", 'killed_pids' => $result['killed_pids'], 'removed' => $result['removed']];
    }

    private static function processIdentity(int $pid): ?string
    {
        $stat = @file_get_contents("/proc/{$pid}/stat");
        if (! is_string($stat) || $stat === '') return null;
        $close = strrpos($stat, ') ');
        if ($close === false) return null;
        $fields = preg_split('/\\s+/', substr($stat, $close + 2));
        // /proc/<pid>/stat field 22 is starttime; after stripping pid+comm it
        // lands at zero-based index 19.
        return isset($fields[19]) ? (string) $fields[19] : null;
    }

    private static function sameProcessIdentity(int $pid, ?string $identity): bool
    {
        if ($identity === null) return self::pidIsAlive($pid);
        return self::pidIsAlive($pid) && self::processIdentity($pid) === $identity;
    }

    /** @return array<int,int> */
    private static function relatedWhatnotProcessTree(int $pid): array
    {
        $seen = [];
        $walk = function (int $current) use (&$walk, &$seen): void {
            if ($current <= 1 || isset($seen[$current]) || ! self::pidIsAlive($current)) return;
            $cmd = self::commandLine($current) ?? '';
            if (! self::isWhatnotProcessCommand($cmd)) return;
            $seen[$current] = $current;

            foreach (glob('/proc/[0-9]*/status') ?: [] as $statusFile) {
                $status = @file_get_contents($statusFile);
                if (! is_string($status)
                    || preg_match('/^Pid:\\s+(\\d+)/m', $status, $pidMatch) !== 1
                    || preg_match('/^PPid:\\s+(\\d+)/m', $status, $ppidMatch) !== 1
                    || (int) $ppidMatch[1] !== $current) {
                    continue;
                }
                $child = (int) $pidMatch[1];
                if (self::isWhatnotProcessCommand(self::commandLine($child) ?? '')) {
                    $walk($child);
                }
            }
        };

        $walk($pid);

        // Include related parents (artisan -> shell/flock -> scraper) only while
        // they are unmistakably part of the Whatnot pipeline.
        $current = $pid;
        for ($i = 0; $i < 5; $i++) {
            $status = @file_get_contents("/proc/{$current}/status") ?: '';
            if (preg_match('/^PPid:\\s+(\\d+)/m', $status, $match) !== 1) break;
            $parent = (int) $match[1];
            if ($parent <= 1 || ! self::pidIsAlive($parent)) break;
            if (! self::isWhatnotProcessCommand(self::commandLine($parent) ?? '')) break;
            $seen[$parent] = $parent;
            $current = $parent;
        }

        $targets = array_values($seen ?: [$pid]);
        usort($targets, function (int $a, int $b): int {
            $depth = function (int $target): int {
                $d = 0;
                while ($target > 1 && $d < 20) {
                    $status = @file_get_contents("/proc/{$target}/status") ?: '';
                    if (preg_match('/^PPid:\\s+(\\d+)/m', $status, $m) !== 1) break;
                    $target = (int) $m[1];
                    $d++;
                }
                return $d;
            };
            return $depth($b) <=> $depth($a);
        });

        return $targets;
    }

    private static function isWhatnotProcessCommand(string $command): bool
    {
        return $command !== '' && preg_match(
            '/whatnot:sync-reporting|whatnot-analytics|whatnot-scrapling|whatnot-browser|storage\\/whatnot-browser\\.lock|flock.*whatnot/i',
            $command
        ) === 1;
    }

    public static function processDetails(?int $pid): ?array
    {
        if (! $pid || ! self::pidIsAlive($pid)) return null;
        $status = @file_get_contents("/proc/{$pid}/status") ?: '';
        preg_match('/^PPid:\\s+(\\d+)/m', $status, $ppid);
        $started = @filemtime("/proc/{$pid}") ?: null;

        return [
            'pid' => $pid,
            'ppid' => isset($ppid[1]) ? (int) $ppid[1] : null,
            'command' => self::commandLine($pid),
            'runtime_seconds' => $started ? max(0, time() - $started) : null,
        ];
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

                if (! function_exists('posix_kill')) {
                    continue;
                }
                @posix_kill($pid, defined('SIGTERM') ? SIGTERM : 15);
                usleep(500_000);

                if (self::pidIsAlive($pid)) {
                    @posix_kill($pid, defined('SIGKILL') ? SIGKILL : 9);
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

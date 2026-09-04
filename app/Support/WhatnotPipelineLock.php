<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Coarse-grained lock for an entire Whatnot pipeline run.
 *
 * The browser lock protects one browser process. This lock sits one level above
 * it and prevents separate pipelines (shows, orders, shipments, ledger) from
 * interleaving channel-by-channel and fighting over the same browser profile.
 */
class WhatnotPipelineLock
{
    public const KEY = 'whatnot:pipeline';
    public const HOLDER_KEY = 'whatnot:pipeline:holder';
    public const TTL = 14400; // 4 hours; stale local owners are recovered eagerly.

    /** @return array{pid:int,host:string,label:string,started_at:string,alive:bool}|null */
    public static function holder(): ?array
    {
        $holder = Cache::get(self::HOLDER_KEY);
        if (! is_array($holder) || empty($holder['pid']) || empty($holder['host'])) {
            return null;
        }

        $pid = (int) $holder['pid'];
        $host = (string) $holder['host'];

        return [
            'pid' => $pid,
            'host' => $host,
            'label' => (string) ($holder['label'] ?? 'Whatnot pipeline'),
            'started_at' => (string) ($holder['started_at'] ?? ''),
            'alive' => $host === gethostname() && WhatnotBrowserLock::pidIsAlive($pid),
        ];
    }

    public static function acquire(string $label, int $waitSeconds = 0): ?Lock
    {
        self::recoverIfStale();

        $lock = Cache::lock(self::KEY, self::TTL);

        try {
            if ($waitSeconds > 0) {
                $lock->block($waitSeconds);
            } elseif (! $lock->get()) {
                return null;
            }
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return null;
        }

        Cache::put(self::HOLDER_KEY, [
            'pid' => getmypid(),
            'host' => gethostname(),
            'label' => $label,
            'started_at' => now()->toISOString(),
        ], self::TTL);

        return $lock;
    }

    public static function release(?Lock $lock): void
    {
        if (! $lock) {
            return;
        }

        try {
            $lock->release();
        } finally {
            Cache::forget(self::HOLDER_KEY);
        }
    }

    public static function recoverIfStale(): bool
    {
        $holder = self::holder();
        if (! $holder || $holder['host'] !== gethostname() || $holder['alive']) {
            return false;
        }

        Cache::lock(self::KEY)->forceRelease();
        Cache::forget(self::HOLDER_KEY);

        Log::warning('WhatnotPipelineLock: recovered stale pipeline owner', [
            'pid' => $holder['pid'],
            'label' => $holder['label'],
        ]);

        return true;
    }

    public static function busyMessage(): string
    {
        $holder = self::holder();
        if (! $holder) {
            return 'Another Whatnot pipeline acquired the coordinator lock first.';
        }

        $where = $holder['host'] === gethostname()
            ? "PID {$holder['pid']}"
            : "{$holder['host']} PID {$holder['pid']}";

        return "{$holder['label']} is already running ({$where}).";
    }
}

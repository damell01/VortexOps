<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * The one lock over the one browser, and a straight answer about who holds it.
 *
 * Every Whatnot job drives a single Chromium profile, so they queue behind this.
 * Knowing *who* is holding it is the difference between "wait a minute" and
 * "this is stale, clear it" — and getting that wrong costs twenty minutes or a
 * corrupted profile.
 *
 * That answer used to live in a second cache key written just after the lock was
 * taken, which meant two facts that could disagree. They did, constantly: any
 * job whose `finally` ran without it ever having held the lock deleted the key
 * belonging to the job that did, and the lock became "held by nobody". It was
 * fixed in one command, then found in another that ran every ten minutes and
 * recreated the state on a timer.
 *
 * So the holder is not tracked separately any more. Laravel already stores an
 * owner token with the lock itself, atomically, in the same row — this just
 * makes that token say who we are. A process cannot erase it without releasing
 * the lock, because it *is* the lock.
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
     * @return array{pid:int,host:string,alive:bool}|null  null when it is free,
     *         or held by something that predates this scheme.
     */
    public static function holder(): ?array
    {
        $owner = self::storedOwner();

        if ($owner === null || ! preg_match('/^(\d+)@(.*)$/', $owner, $matches)) {
            // Fall back to the old separate key so a lock taken by code that has
            // not been redeployed yet is still attributable.
            $legacy = Cache::get(self::KEY . ':holder_pid');

            return $legacy
                ? ['pid' => (int) $legacy, 'host' => gethostname(), 'alive' => self::pidIsAlive((int) $legacy)]
                : null;
        }

        $pid = (int) $matches[1];

        return [
            'pid'   => $pid,
            'host'  => $matches[2],
            // Only meaningful for a lock held on this machine.
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
     * A process that has exited but not been reaped keeps its /proc entry, so
     * existence alone counts a dead job as a live one and leaves the lock
     * refused for ever. Zombies hold nothing.
     */
    public static function pidIsAlive(int $pid): bool
    {
        if ($pid <= 0 || ! is_dir("/proc/{$pid}")) {
            return false;
        }

        $status = @file_get_contents("/proc/{$pid}/status");

        return $status === false || preg_match('/^State:\s*Z/m', $status) !== 1;
    }

    /**
     * The owner token currently stored against the lock.
     *
     * Laravel gives a Lock instance its *own* owner, not the stored one, so
     * there is no API for reading another process's token — it has to come from
     * where the driver keeps it.
     */
    protected static function storedOwner(): ?string
    {
        $lock = Cache::lock(self::KEY);

        // Every driver already implements getCurrentOwner() — reading its own
        // table, key or array — and every driver stores it differently:
        // prefixes, lock tables, expiry semantics. Reimplementing that per
        // driver is how this ends up right in production and untestable under
        // the array store, so borrow the framework's accessor instead of
        // guessing at the storage. It is protected rather than private
        // precisely because it is the driver's answer to this question.
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

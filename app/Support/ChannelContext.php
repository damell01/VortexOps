<?php

namespace App\Support;

use App\Models\WhatnotChannel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which channel (e.g. Vortex Breaks, Vortex Collects) an admin is currently
 * scoped to, if any. Session-based, not persisted per-user — pick "All
 * Channels" or a specific one, and resources that opt into scoping (see
 * Concerns\ScopesToChannel) filter their queries accordingly. Streamers are
 * unaffected: their data is naturally scoped by their own channel tie.
 */
class ChannelContext
{
    private const SESSION_KEY = 'active_whatnot_channel_id';

    /**
     * current()/currentId()/isScoped() are called from dozens of resources,
     * widgets, and services — often several times on a single page (once per
     * widget's canView(), once per getEloquentQuery(), once for the brand
     * name, ...). Without memoizing, each of those was a fresh
     * "select * from whatnot_channels where id = ?" round trip; on a
     * dashboard load with 7+ widgets that's 15-30+ identical queries just to
     * answer "which channel is active" over and over. One query per request.
     */
    private static bool $memoized = false;
    private static ?WhatnotChannel $currentMemo = null;

    /** @return Collection<int, WhatnotChannel> */
    public static function available(): Collection
    {
        return WhatnotChannel::where('status', 'active')->orderBy('name')->get();
    }

    public static function current(): ?WhatnotChannel
    {
        if (self::$memoized) {
            return self::$currentMemo;
        }

        $id = session(self::SESSION_KEY);

        self::$currentMemo = $id ? WhatnotChannel::find($id) : null;
        self::$memoized     = true;

        return self::$currentMemo;
    }

    public static function currentId(): ?int
    {
        return self::current()?->id;
    }

    /** True when scoped to a specific channel (false = viewing all channels). */
    public static function isScoped(): bool
    {
        return self::currentId() !== null;
    }

    public static function setActive(?int $channelId): void
    {
        self::flushMemo();

        if ($channelId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session([self::SESSION_KEY => $channelId]);
    }

    public static function flushMemo(): void
    {
        self::$memoized    = false;
        self::$currentMemo = null;
    }
}

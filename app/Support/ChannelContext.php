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

    /** @return Collection<int, WhatnotChannel> */
    public static function available(): Collection
    {
        return WhatnotChannel::where('status', 'active')->orderBy('name')->get();
    }

    public static function current(): ?WhatnotChannel
    {
        $id = session(self::SESSION_KEY);

        if (! $id) {
            return null;
        }

        return WhatnotChannel::find($id);
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
        if ($channelId === null) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session([self::SESSION_KEY => $channelId]);
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lightweight conversation change token. Clients poll this instead of fetching
 * and rendering 50 full messages when nothing has changed.
 *
 * Tokens are best-effort: clients still perform an occasional full refresh to
 * catch changes made outside Eloquent observers or after cache eviction.
 */
final class ChatRevision
{
    private static function key(string $conversationId): string
    {
        return 'chat:revision:'.hash('sha256', $conversationId);
    }

    public static function current(string $conversationId): string
    {
        return (string) Cache::get(self::key($conversationId), '0');
    }

    public static function touch(?string $conversationId): void
    {
        if (! $conversationId) {
            return;
        }

        $write = static function () use ($conversationId): void {
            Cache::put(self::key($conversationId), Str::random(24), now()->addDays(7));
        };

        // Never publish a change that the transaction may still roll back.
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($write);
        } else {
            $write();
        }
    }
}

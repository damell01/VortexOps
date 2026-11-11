<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Seen" and "typing" for one conversation, for its participants only.
 *  GET  → when each other participant last read it, and who is typing now.
 *  POST → "I am typing" (kept for a few seconds; the widget repeats it while typing).
 * Nothing is stored in the database: read times are the participants' own
 * last_read_at, typing lives in the cache for six seconds.
 */
class ChatPresenceController
{
    private const TYPING_SECONDS = 6;

    public function __invoke(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $allowed = config('chatify-preview.available', false)
            && (\App\Services\TeamCommunications::canAccess($user) || config('chatify-preview.team_enabled', false));
        abort_unless($user && $allowed, 403);

        $table = config('chatify.tables.participants', 'ch_conversation_participants');
        abort_unless(DB::table($table)->where('conversation_id', $conversationId)->where('user_id', $user->id)->exists(), 404);

        $key = 'chat:typing:' . $conversationId;
        if ($request->isMethod('post')) {
            $typing = array_filter((array) Cache::get($key, []), fn ($until) => $until > time());
            $typing[$user->id] = $request->boolean('typing', true) ? time() + self::TYPING_SECONDS : 0;
            Cache::put($key, array_filter($typing), self::TYPING_SECONDS + 4);

            return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
        }

        $others = DB::table($table . ' as p')->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.conversation_id', $conversationId)->where('p.user_id', '!=', $user->id)
            ->get(['p.user_id', 'u.name', 'p.last_read_at']);
        $typingIds = array_keys(array_filter((array) Cache::get($key, []), fn ($until) => $until > time()));

        return response()->json([
            'read' => $others->map(fn ($p) => [
                'user_id' => (string) $p->user_id,
                'name' => $p->name,
                'last_read_at' => $p->last_read_at ? \Illuminate\Support\Carbon::parse($p->last_read_at)->toIso8601String() : null,
            ])->values(),
            'typing' => $others->whereIn('user_id', $typingIds)->pluck('name')->map(fn ($n) => explode(' ', trim((string) $n))[0])->values(),
        ])->header('Cache-Control', 'no-store');
    }
}

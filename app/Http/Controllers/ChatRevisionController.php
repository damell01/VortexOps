<?php

namespace App\Http\Controllers;

use App\Services\TeamCommunications;
use App\Support\ChatRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tiny, membership-protected change check for the active chat.
 * Returns no message content, user names, or participant details.
 */
final class ChatRevisionController
{
    public function __invoke(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && config('chatify-preview.available', false)
            && (TeamCommunications::canAccess($user) || config('chatify-preview.team_enabled', false)),
            403
        );

        $table = config('chatify.tables.participants', 'ch_conversation_participants');
        abort_unless(DB::table($table)
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->exists(), 404);

        return response()->json(['revision' => ChatRevision::current($conversationId)])
            ->header('Cache-Control', 'private, no-store');
    }
}

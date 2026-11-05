<?php

namespace App\Http\Controllers;

use Chatify\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatMessageReactionController
{
    public function __invoke(Request $request, string $messageId): JsonResponse
    {
        $message = $messageId;
        abort_unless(config('chatify-preview.enabled', false) || config('chatify-preview.team_enabled', false), 404);
        abort_unless(\App\Services\TeamCommunications::canAccess($request->user()) || config('chatify-preview.team_enabled', false), 403);
        if ($message === 'batch') {
            abort_unless($request->isMethod('get'), 405);
            $ids = $request->validate(['ids' => ['required', 'array', 'max:50'], 'ids.*' => ['required', 'string', 'max:36']])['ids'];
            $allowed = Message::query()->whereIn('id', $ids)->whereHas('conversation.participants', fn ($q) => $q->where('user_id', $request->user()->id))->pluck('id');
            return response()->json(['reactions' => DB::table('chat_message_reactions')->whereIn('message_id', $allowed)->get(['message_id', 'user_id', 'emoji'])->groupBy('message_id')]);
        }
        $chat = Message::query()->findOrFail($message);
        $conversation = $chat->conversation;
        abort_unless($conversation && $conversation->participants()->where('user_id', $request->user()->id)->exists(), 403);

        if ($request->isMethod('get')) {
            return response()->json(['reactions' => DB::table('chat_message_reactions')
                ->where('message_id', $chat->id)->get(['user_id', 'emoji'])]);
        }

        $data = $request->validate(['emoji' => ['required', \Illuminate\Validation\Rule::in(['👍', '❤️', '😂', '🎉', '👀'])]]);
        $query = DB::table('chat_message_reactions')->where('message_id', $chat->id)
            ->where('user_id', $request->user()->id)->where('emoji', $data['emoji']);

        if ($query->exists()) {
            $query->delete();
            $active = false;
        } else {
            $inserted = DB::table('chat_message_reactions')->insertOrIgnore([
                'message_id' => $chat->id, 'user_id' => $request->user()->id,
                'emoji' => $data['emoji'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            $active = $inserted > 0;
        }

        return response()->json(['active' => $active]);
    }
}

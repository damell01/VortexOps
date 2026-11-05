<?php

namespace App\Http\Controllers;

use Chatify\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatMessageReactionController
{
    public function __invoke(Request $request, string $message): JsonResponse
    {
        abort_unless(config('chatify-preview.enabled', false), 404);
        $chat = Message::query()->findOrFail($message);
        $conversation = $chat->conversation;
        abort_unless($conversation && $conversation->participants()->where('user_id', $request->user()->id)->exists(), 403);

        if ($request->isMethod('get')) {
            return response()->json(['reactions' => DB::table('chat_message_reactions')
                ->where('message_id', $chat->id)->get(['user_id', 'emoji'])]);
        }

        $data = $request->validate(['emoji' => ['required', 'in:👍,❤️,😂,🎉,👀']]);
        $query = DB::table('chat_message_reactions')->where('message_id', $chat->id)
            ->where('user_id', $request->user()->id)->where('emoji', $data['emoji']);

        if ($query->exists()) {
            $query->delete();
            $active = false;
        } else {
            DB::table('chat_message_reactions')->insert([
                'message_id' => $chat->id, 'user_id' => $request->user()->id,
                'emoji' => $data['emoji'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            $active = true;
        }

        return response()->json(['active' => $active]);
    }
}

<?php

namespace App\Observers;

use App\Jobs\SendChatMessageAlerts;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Chatify integration; only runs after explicit staging feature enablement. */
class ChatifyMessageObserver
{
    public function deleted($message): void
    {
        // Chatify soft-deletes messages. Remove reactions so deleted content
        // cannot retain stale interaction data indefinitely.
        if (! \Illuminate\Support\Facades\Schema::hasTable('chat_message_reactions')) return;
        \Illuminate\Support\Facades\DB::table('chat_message_reactions')
            ->where('message_id', (string) $message->getKey())
            ->delete();
    }

    public function created($message): void
    {
        if (! config('chatify-preview.message_notifications_enabled', false) || ! (config('chatify-preview.team_enabled', false) || config('chatify-preview.enabled', false))) return;
        $senderId = (int) ($message->user_id ?? 0);
        $sender = User::find($senderId);
        if (! $sender) return;
        $conversation = $message->conversation;
        if (! $conversation || ! method_exists($conversation, 'participants')) return;
        // Chatify participants may be pivot records or User models depending on package version.
        try {
            $participants = $conversation->participants()->get();
            foreach ($participants as $participant) {
                $recipientId = $participant instanceof User
                    ? (int) $participant->getKey()
                    : (int) ($participant->user_id ?? 0);
                if ($recipientId <= 0 || $recipientId === $senderId) continue;
                SendChatMessageAlerts::dispatch(
                    $recipientId,
                    $senderId,
                    (string) $conversation->getKey(),
                    (string) $message->getKey(),
                )->afterCommit();
            }
        } catch (\Throwable $e) {
            Log::warning('Chatify message alert scheduling failed', [
                'conversation_id' => $conversation->getKey(),
                'message_id' => $message->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

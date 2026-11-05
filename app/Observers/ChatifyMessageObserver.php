<?php

namespace App\Observers;

use App\Jobs\SendChatMessageAlerts;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/** Chatify integration; only runs after explicit staging feature enablement. */
class ChatifyMessageObserver
{
    public function created($message): void
    {
        if (! config('chatify-preview.message_notifications_enabled', false)) return;
        $senderId = (int) ($message->user_id ?? 0);
        $sender = User::find($senderId);
        if (! $sender) return;
        $conversation = $message->conversation;
        if (! $conversation || ! method_exists($conversation, 'participants')) return;
        // Chatify participants may be pivot records or User models depending on package version.
        try {
            $participants = $conversation->participants()->get();
            foreach ($participants as $participant) {
                $recipientId = (int) ($participant->user_id ?? $participant->getKey());
                if ($recipientId <= 0 || $recipientId === $senderId) continue;
                SendChatMessageAlerts::dispatch(
                    $recipientId,
                    $senderId,
                    $sender->name,
                    (string) ($message->body ?? ''),
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

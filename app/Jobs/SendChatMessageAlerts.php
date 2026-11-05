<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WebPushSubscription;
use App\Services\NotificationRouter;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Delivery only: callers must derive recipient IDs from authorized conversation membership.
 * No message content is logged. All channels require explicit recipient opt-in.
 */
class SendChatMessageAlerts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $recipientId,
        public int $senderId,
        public string $senderName,
        public string $messagePreview,
        public string $conversationId,
        public string $messageId,
    ) {}

    public function handle(WebPushService $push): void
    {
        if (! config('chatify-preview.message_notifications_enabled', false)) return;
        if ($this->recipientId === $this->senderId) return;
        // Re-check membership when the queued job runs, not just when it was created.
        // A removed participant must never receive message content.
        if (! class_exists(\Chatify\Models\Conversation::class)) return;
        $conversation = \Chatify\Models\Conversation::find($this->conversationId);
        if (! $conversation || ! method_exists($conversation, 'participants')) return;
        $participantRecord = $conversation->participants()->get()->first(function ($participant) {
            $id = $participant instanceof User ? $participant->getKey() : ($participant->user_id ?? null);
            return (int) $id === $this->recipientId;
        });
        if (! $participantRecord) return;
        // Do not email or push a message the recipient has already read.
        $message = \Chatify\Models\Message::find($this->messageId);
        if (! $message || (string) $message->conversation_id !== $this->conversationId) return;
        if (method_exists($message, 'isReadByParticipant') && $message->isReadByParticipant($participantRecord)) return;
        $user = User::find($this->recipientId);
        if (! $user || $user->notifications_enabled === false) return;
        $prefs = (array) (($user->notification_preferences ?? [])['team_message'] ?? []);
        $previewAllowed = (bool) ($prefs['preview'] ?? true);
        $sender = Str::limit(trim(preg_replace('/[\\x00-\\x1F\\x7F]+/', ' ', strip_tags($this->senderName))), 65);
        $preview = Str::limit(trim(preg_replace('/\\s+/', ' ', strip_tags($this->messagePreview))), 180);
        $title = $previewAllowed ? 'New message from '.$sender : 'New team message';
        $body = $previewAllowed ? ($preview !== '' ? $preview : 'Sent an attachment') : 'Open Vortex Ops to read your message.';
        $url = '/admin';
        if (($prefs['push'] ?? false) && $user->notification_in_app_enabled !== false) {
            foreach (WebPushSubscription::where('user_id', $user->id)->limit(8)->get() as $device) {
                $deliveryKey = 'chat-push:'.$user->id.':'.$device->id.':'.$this->messageId;
                if (Cache::has($deliveryKey)) continue;
                try {
                    $delivered = $push->send($device, [
                        'title' => $title, 'body' => $body,
                        'tag' => 'chat-'.$this->conversationId,
                        'url' => $url,
                    ]);
                    if ($delivered) Cache::put($deliveryKey, true, now()->addDay());
                } catch (\Throwable $e) {
                    Log::warning('Chat message push delivery failed', ['user_id' => $user->id, 'device_id' => $device->id, 'error' => $e->getMessage()]);
                }
            }
        }
        if (($prefs['email'] ?? false) && $user->notification_email_enabled !== false && NotificationRouter::emailIsEnabled()) {
            // One email per conversation per recipient in a 15-minute window.
            $key = 'chat-mail:'.$user->id.':'.hash('sha256', $this->conversationId);
            if (Cache::add($key, true, now()->addMinutes(15))) {
                try {
                    Mail::raw($body."\n\nOpen Vortex Ops to reply: ".url('/admin'), function ($mail) use ($user, $title) {
                        $mail->to($user->email)->subject($title);
                    });
                } catch (\Throwable $e) {
                    Cache::forget($key);
                    Log::warning('Chat message email delivery failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                    throw $e;
                }
            }
        }
    }
}

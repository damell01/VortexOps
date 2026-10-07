<?php

namespace App\Listeners;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Log;

/**
 * Records every email the app sends, with exactly what it said.
 *
 * MessageSending writes the row before the mailer is called, so an email
 * that fails on the way out is still on record ("Not confirmed", or "Failed"
 * when Notifier catches the error); MessageSent marks it sent. The event key
 * and recipient come from NotificationSending, which fires just before a
 * notification's mail is built.
 */
class LogOutgoingEmail
{
    /** Set while a notification's email is being sent. */
    public static ?array $context = null;

    /** The row for the most recent message, so a caller can mark it failed. */
    public static ?int $lastId = null;

    /** Set by test sends so they are marked and kept out of hourly limits. */
    public static bool $testing = false;

    public static function noteNotification(NotificationSending $event): void
    {
        if ($event->channel !== 'mail') return;

        static::$context = [
            'event' => method_exists($event->notification, 'notificationEvent') ? $event->notification->notificationEvent() : class_basename($event->notification),
            'user_id' => $event->notifiable instanceof User ? $event->notifiable->id : null,
        ];
    }

    public static function sending(MessageSending $event): void
    {
        try {
            $message = $event->message;
            $to = collect($message->getTo());

            $log = EmailLog::create([
                'event' => static::$context['event'] ?? null,
                'user_id' => static::$context['user_id'] ?? null,
                'to_email' => $to->map(fn ($a) => $a->getAddress())->join(', ') ?: '(none)',
                'to_name' => $to->map(fn ($a) => $a->getName())->filter()->join(', ') ?: null,
                'subject' => $message->getSubject(),
                'html' => is_string($html = $message->getHtmlBody()) ? $html : null,
                'text' => is_string($text = $message->getTextBody()) ? $text : null,
                'status' => 'sending',
                'is_test' => static::$testing,
            ]);

            static::$lastId = $log->id;
            $message->getHeaders()->addTextHeader('X-Vortex-Email-Log', (string) $log->id);
        } catch (\Throwable $e) {
            // The log must never be the reason an email does not go out.
            Log::warning('Could not record outgoing email', ['error' => $e->getMessage()]);
        } finally {
            static::$context = null;
        }
    }

    public static function sent(MessageSent $event): void
    {
        try {
            $id = $event->message->getHeaders()->get('X-Vortex-Email-Log')?->getBodyAsString();
            if (! $id) return;

            EmailLog::whereKey((int) $id)->update([
                'status' => 'sent',
                'sent_at' => now(),
                'message_id' => method_exists($event->sent, 'getMessageId') ? $event->sent->getMessageId() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not mark email as sent', ['error' => $e->getMessage()]);
        }
    }

    /** Mark the last message as failed after the mailer threw. */
    public static function failLast(string $error): void
    {
        if (! static::$lastId) return;

        try {
            EmailLog::whereKey(static::$lastId)->where('status', 'sending')->update(['status' => 'failed', 'error' => mb_substr($error, 0, 2000)]);
        } catch (\Throwable) {
        }
    }
}

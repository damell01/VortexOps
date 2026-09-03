<?php

namespace App\Notifications\Concerns;

use App\Models\Setting;

/**
 * Resolves notification delivery channels from three layers:
 *
 * 1. Environment safety switch (email is opt-in, off by default)
 * 2. App-level Settings toggle
 * 3. The individual user's own notification preferences
 */
trait EmailsWhenEnabled
{
    public function via(object $notifiable): array
    {
        $channels = [];

        $wants = static function (object $notifiable, string $channel): bool {
            return ! method_exists($notifiable, 'wantsNotificationChannel')
                || $notifiable->wantsNotificationChannel($channel);
        };

        if ($wants($notifiable, 'database')) {
            $channels[] = 'database';
        }

        if (static::emailIsEnabled() && $wants($notifiable, 'mail')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public static function emailIsEnabled(): bool
    {
        if (! filter_var(config('mail.notification_emails_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        // Cast rather than trust: the settings table stores '1'/'0' strings,
        // and '0' is truthy.
        return filter_var(Setting::get('notify_email_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}

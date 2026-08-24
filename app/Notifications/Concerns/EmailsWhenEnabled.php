<?php

namespace App\Notifications\Concerns;

use App\Models\Setting;

/**
 * Adds the mail channel to an in-app notification when email is switched on.
 *
 * Seven of the ten notifications were database-only and hardcoded that way, so
 * configuring Resend changed nothing they did — the bell in the panel was the
 * only place anything arrived. The three that did declare 'mail' were the
 * scheduled digests, which is why mail looked wired when it mostly was not.
 *
 * Off by default. Turning a mailer on should not silently start sending a
 * team every operational event that was previously an in-app badge; the
 * Settings toggle is the moment someone decides they want that.
 */
trait EmailsWhenEnabled
{
    public function via(object $notifiable): array
    {
        return static::emailIsEnabled()
            ? ['database', 'mail']
            : ['database'];
    }

    public static function emailIsEnabled(): bool
    {
        // Cast rather than trust: the settings table stores '1'/'0' strings,
        // and '0' is truthy.
        return filter_var(Setting::get('notify_email_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}

<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Owner switch for team messaging, kept in the settings table so it can be
 * turned on — and back off — from Settings without editing the server's .env.
 *
 * Not set (the default): config/chatify-preview.php decides from .env, exactly
 * as before. On: every signed-in teammate gets messaging, and message alerts
 * follow the second switch. Off: back to the admin-only pilot the .env defines.
 *
 * Applied at the very start of AppServiceProvider::boot(), because the message
 * observer that sends alerts is registered there and reads these flags once.
 */
class TeamMessagingSwitch
{
    public const ENABLED = 'team_messaging_enabled';
    public const ALERTS = 'team_message_alerts_enabled';

    /** null when the owner has never set it (the .env decides). */
    public static function enabled(): ?bool
    {
        return static::read(self::ENABLED);
    }

    public static function alerts(): bool
    {
        return (bool) static::read(self::ALERTS);
    }

    public static function apply(): void
    {
        $enabled = static::enabled();

        if ($enabled === null) {
            return;
        }

        if ($enabled) {
            config([
                'chatify-preview.available' => true,
                'chatify-preview.enabled' => true,
                'chatify-preview.privileged_preview_enabled' => false,
                'chatify-preview.team_enabled' => true,
                'chatify-preview.message_notifications_enabled' => static::alerts(),
            ]);

            return;
        }

        config([
            'chatify-preview.team_enabled' => false,
            'chatify-preview.message_notifications_enabled' => false,
        ]);
    }

    private static function read(string $key): ?bool
    {
        try {
            $value = Setting::get($key);
        } catch (\Throwable) {
            // No database yet (fresh install, migrations pending): leave .env in charge.
            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}

<?php

namespace App\Notifications\Concerns;

use App\Services\NotificationRouter;

/**
 * Channels for a catalogued notification come from NotificationRouter: the
 * owner's global email switch and hourly limit, the event's rule, and the
 * person's own preferences (see NotificationCatalog for the events).
 *
 * A class names its event with `protected string $event = 'low_stock';`.
 */
trait EmailsWhenEnabled
{
    public function via(object $notifiable): array
    {
        return app(NotificationRouter::class)->channelsFor($this->notificationEvent(), $notifiable);
    }

    public function notificationEvent(): string
    {
        return property_exists($this, 'event') ? $this->event : 'show_ready';
    }

    public static function emailIsEnabled(): bool
    {
        return NotificationRouter::emailIsEnabled();
    }
}

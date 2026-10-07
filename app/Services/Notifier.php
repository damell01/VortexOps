<?php

namespace App\Services;

use App\Listeners\LogOutgoingEmail;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The one way to send a catalogued notification.
 *
 *   Notifier::send('report_submitted', new VortexAlert(...));
 *   Notifier::send('report_reviewed', $alert, involved: [$streamerUser]);
 *
 * Recipients come from the event's rule (plus the involved people when the
 * rule includes them), each person's channels from their preferences, and
 * any extra addresses on the rule get the email. One person failing never
 * stops the rest.
 */
class Notifier
{
    /**
     * @param  iterable<User|null>  $involved
     * @return int how many people it went to
     */
    public static function send(string $event, Notification $notification, iterable $involved = []): int
    {
        $router = app(NotificationRouter::class);
        $sent = 0;

        foreach ($router->recipientsFor($event, $involved) as $user) {
            try {
                $user->notify($notification);
                $sent++;
            } catch (\Throwable $e) {
                LogOutgoingEmail::failLast($e->getMessage());
                Log::warning('Notification failed', ['event' => $event, 'user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        // Addresses with no login: email only, and only when the rule allows email.
        $rule = $router->rule($event);
        if ($rule['enabled'] && $rule['email'] && NotificationRouter::emailIsEnabled()) {
            foreach ($rule['emails'] as $address) {
                try {
                    NotificationFacade::route('mail', $address)->notify($notification);
                } catch (\Throwable $e) {
                    LogOutgoingEmail::failLast($e->getMessage());
                    Log::warning('Notification email failed', ['event' => $event, 'to' => $address, 'error' => $e->getMessage()]);
                }
            }
        }

        return $sent;
    }
}

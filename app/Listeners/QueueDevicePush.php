<?php

namespace App\Listeners;

use App\Jobs\SendDevicePush;
use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Notifications\Events\NotificationSent;

class QueueDevicePush
{
    public function handle(NotificationSent $event): void
    {
        $user = $event->notifiable;
        // Mirror already-routed in-app alerts, so existing recipients/rules and
        // per-event preferences remain authoritative. Mail does not send a second push.
        if ($event->channel !== 'database' || ! $user instanceof User || $user->notifications_enabled === false || $user->notification_in_app_enabled === false) return;
        if (! WebPushSubscription::where('user_id', $user->id)->exists()) return;
        $id = $event->response instanceof \Illuminate\Notifications\DatabaseNotification ? $event->response->id : null;
        if (is_string($id)) SendDevicePush::dispatch($user->id, $id)->afterCommit();
    }
}

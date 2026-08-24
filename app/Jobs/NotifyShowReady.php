<?php

namespace App\Jobs;

use App\Models\Show;
use App\Services\NotificationRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyShowReady implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 30;

    public function __construct(public readonly int $showId) {}

    public function handle(NotificationRouter $router): void
    {
        try {
            $show = Show::find($this->showId);

            if (! $show) {
                return;
            }

            $notification = new \App\Notifications\ShowReadyNotification($show);

            foreach ($router->getRecipients('show_ready') as $user) {
                $user->notify($notification);
            }

            // Settings has asked for this address since the page was built and
            // nothing ever read it — a field that takes an email and quietly
            // discards it. It is for someone who needs the alert without
            // needing a login, so it is mail only, and only when mail is on.
            $extra = \App\Models\Setting::get('show_ready_notification_email', '');

            if (filled($extra) && \App\Notifications\ShowReadyNotification::emailIsEnabled()) {
                \Illuminate\Support\Facades\Notification::route('mail', $extra)
                    ->notify($notification);
            }
        } catch (\Exception $e) {
            Log::warning('NotifyShowReady failed', ['show_id' => $this->showId, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Show;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\Notification as BaseNotification;

class NotifyShowReady implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $showId) {}

    public function handle(): void
    {
        try {
            $show = Show::find($this->showId);

            if (! $show) {
                return;
            }

            $recipients = User::role(['admin', 'super_admin'])->get();

            foreach ($recipients as $user) {
                $user->notify(new \App\Notifications\ShowReadyNotification($show));
            }
        } catch (\Exception $e) {
            Log::warning('NotifyShowReady failed', ['show_id' => $this->showId, 'error' => $e->getMessage()]);
        }
    }
}

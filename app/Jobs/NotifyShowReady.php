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

    public int $tries = 1;

    public function __construct(public readonly int $showId) {}

    public function handle(NotificationRouter $router): void
    {
        try {
            $show = Show::find($this->showId);

            if (! $show) {
                return;
            }

            foreach ($router->getRecipients('show_ready') as $user) {
                $user->notify(new \App\Notifications\ShowReadyNotification($show));
            }
        } catch (\Exception $e) {
            Log::warning('NotifyShowReady failed', ['show_id' => $this->showId, 'error' => $e->getMessage()]);
        }
    }
}

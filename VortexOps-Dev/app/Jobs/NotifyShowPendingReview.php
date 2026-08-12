<?php

namespace App\Jobs;

use App\Models\Show;
use App\Notifications\ShowPendingReviewNotification;
use App\Services\NotificationRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyShowPendingReview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 30;

    public function __construct(public readonly int $showId) {}

    public function handle(NotificationRouter $router): void
    {
        try {
            $show = Show::with('streamers.user')->find($this->showId);

            if (! $show) {
                return;
            }

            $recipients  = $router->getRecipients('show_pending_approval');
            $notifiedIds = $recipients->pluck('id')->flip();

            foreach ($recipients as $user) {
                $user->notify(new ShowPendingReviewNotification($show));
            }

            foreach ($show->streamers as $streamer) {
                if ($streamer->user && ! isset($notifiedIds[$streamer->user->id])) {
                    $streamer->user->notify(new ShowPendingReviewNotification($show));
                }
            }
        } catch (\Exception $e) {
            Log::warning('NotifyShowPendingReview failed', ['show_id' => $this->showId, 'error' => $e->getMessage()]);
        }
    }
}

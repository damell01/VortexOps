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

class NotifyShowPendingApproval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $showId) {}

    public function handle(NotificationRouter $router): void
    {
        try {
            $show = Show::with(['streamers.user', 'deductionRequests.lines.inventoryItem'])->find($this->showId);

            if (! $show) {
                return;
            }

            $recipients = $router->getRecipients('show_ready');
            $notifiedIds = $recipients->pluck('id')->flip();

            foreach ($recipients as $user) {
                $user->notify(new \App\Notifications\ShowPendingApprovalNotification($show));
            }

            foreach ($show->streamers as $streamer) {
                if ($streamer->user && ! isset($notifiedIds[$streamer->user->id])) {
                    $streamer->user->notify(new \App\Notifications\ShowPendingApprovalNotification($show));
                }
            }
        } catch (\Exception $e) {
            Log::warning('NotifyShowPendingApproval failed', ['show_id' => $this->showId, 'error' => $e->getMessage()]);
        }
    }
}

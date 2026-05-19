<?php

namespace App\Jobs;

use App\Models\Show;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyShowReconciled implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $showId) {}

    public function handle(): void
    {
        try {
            $show     = Show::with('streamers')->find($this->showId);

            if (! $show) {
                return;
            }

            // Notify admins + ops
            $admins = User::role(['admin', 'super_admin'])->get();
            foreach ($admins as $user) {
                $user->notify(new \App\Notifications\ShowReconciledNotification($show));
            }

            // Notify streamer users linked to this show
            foreach ($show->streamers as $streamer) {
                if ($streamer->user) {
                    $streamer->user->notify(new \App\Notifications\ShowReconciledNotification($show));
                }
            }
        } catch (\Exception $e) {
            Log::warning('NotifyShowReconciled failed', ['show_id' => $this->showId, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Notifications\PayoutProcessedNotification;
use App\Services\NotificationRouter;
use App\Services\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyPayoutProcessed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 30;

    public function __construct(public readonly int $payoutId) {}

    public function handle(NotificationRouter $router): void
    {
        try {
            $payout = Payout::with(['streamer.user', 'show'])->find($this->payoutId);

            if (! $payout) {
                return;
            }

            // Its own rule now — it used to borrow show_reconciled's recipients.
            Notifier::send('payout_processed', new PayoutProcessedNotification($payout), [$payout->streamer?->user]);
        } catch (\Exception $e) {
            Log::warning('NotifyPayoutProcessed failed', ['payout_id' => $this->payoutId, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Notifications\PayoutProcessedNotification;
use App\Services\NotificationRouter;
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
            $payout = Payout::with(['streamer', 'show'])->find($this->payoutId);

            if (! $payout) {
                return;
            }

            foreach ($router->getRecipients('show_reconciled') as $user) {
                $user->notify(new PayoutProcessedNotification($payout));
            }
        } catch (\Exception $e) {
            Log::warning('NotifyPayoutProcessed failed', ['payout_id' => $this->payoutId, 'error' => $e->getMessage()]);
        }
    }
}

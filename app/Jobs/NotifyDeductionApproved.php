<?php

namespace App\Jobs;

use App\Models\DeductionRequest;
use App\Notifications\DeductionApprovedNotification;
use App\Services\NotificationRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyDeductionApproved implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 30;

    public function __construct(public readonly int $deductionRequestId) {}

    public function handle(NotificationRouter $router): void
    {
        try {
            $dr = DeductionRequest::with(['show', 'lines'])->find($this->deductionRequestId);

            if (! $dr) {
                return;
            }

            foreach ($router->getRecipients('show_pending_approval') as $user) {
                $user->notify(new DeductionApprovedNotification($dr));
            }
        } catch (\Exception $e) {
            Log::warning('NotifyDeductionApproved failed', ['deduction_request_id' => $this->deductionRequestId, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Observers;

use App\Jobs\NotifyDeductionApproved;
use App\Models\DeductionRequest;

class DeductionRequestObserver
{
    public function updated(DeductionRequest $deductionRequest): void
    {
        if ($deductionRequest->wasChanged('status') && $deductionRequest->status === 'approved') {
            NotifyDeductionApproved::dispatch($deductionRequest->id);
        }
    }
}

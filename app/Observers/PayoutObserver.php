<?php

namespace App\Observers;

use App\Jobs\NotifyPayoutProcessed;
use App\Models\Payout;

class PayoutObserver
{
    public function updated(Payout $payout): void
    {
        if ($payout->wasChanged('status') && $payout->status === 'paid') {
            NotifyPayoutProcessed::dispatch($payout->id);
        }
    }
}

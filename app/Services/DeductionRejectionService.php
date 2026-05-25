<?php

namespace App\Services;

use App\Models\DeductionRequest;
use Illuminate\Support\Facades\Auth;

class DeductionRejectionService
{
    public function reject(DeductionRequest $request, string $reason): void
    {
        $request->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $request->show->update(['status' => 'pending_review']);
    }
}

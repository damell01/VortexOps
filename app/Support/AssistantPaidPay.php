<?php

namespace App\Support;

use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class AssistantPaidPay
{
    /** Resolve ownership from the payee, never from show assignments or the active channel. */
    public static function query(User $user): Builder
    {
        return Payout::query()
            ->whereHas('streamer', fn (Builder $q) => $q->where('user_id', $user->id))
            ->whereHas('batch', fn (Builder $q) => $q->where('status', 'paid'));
    }
}

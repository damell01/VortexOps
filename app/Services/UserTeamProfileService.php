<?php

namespace App\Services;

use App\Models\Streamer;
use App\Models\User;

class UserTeamProfileService
{
    /**
     * Keep the operational team profile in sync with the login account.
     * Users is the source of truth; Streamer remains the existing backing model
     * used by shows, payroll, inventory and fulfillment.
     */
    public function sync(User $user, string $streamerType = 'in_house'): ?Streamer
    {
        $memberType = $user->hasRole('streamer')
            ? 'streamer'
            : ($user->hasRole('fulfillment') ? 'fulfillment' : null);

        if (! $memberType) {
            return $user->streamer;
        }

        $profile = $user->streamer()->firstOrNew();
        $profile->fill([
            'name' => $user->name,
            'legal_name' => $profile->legal_name ?: $user->name,
            'email' => $user->email,
            'status' => $profile->status ?: 'active',
            'member_type' => $memberType,
            'streamer_type' => $memberType === 'streamer' ? $streamerType : ($profile->streamer_type ?: 'in_house'),
        ]);
        $profile->save();

        return $profile;
    }
}

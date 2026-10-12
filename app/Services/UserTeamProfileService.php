<?php

namespace App\Services;

use App\Models\Streamer;
use App\Models\StreamerAlias;
use App\Models\User;

class UserTeamProfileService
{
    /**
     * Keep the operational team profile in sync with the login account.
     * Users is the source of truth; Streamer remains the existing backing model
     * used by shows, payroll, inventory and fulfillment.
     */
    public function sync(User $user, string $streamerType = 'in_house', ?array $aliases = null): ?Streamer
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

        if ($memberType === 'streamer' && $aliases !== null) {
            $normalized = collect($aliases)
                ->map(fn ($alias) => trim((string) $alias))
                ->filter(fn ($alias) => StreamerAlias::normalize($alias) !== '')
                ->unique(fn ($alias) => StreamerAlias::normalize($alias))
                ->values();

            // The form lists every alias, including ones learned from show
            // titles. Keep the ones still listed (whatever their source), drop
            // the ones removed, and add only the new ones. Recreating them all
            // collided with the learned ones and failed after the user saved.
            $wanted = $normalized->keyBy(fn ($alias) => StreamerAlias::normalize($alias));
            $profile->aliases()->whereNotIn('normalized_alias', $wanted->keys()->all())->delete();
            $existing = $profile->aliases()->pluck('normalized_alias')->all();
            foreach ($wanted->except($existing) as $alias) {
                $profile->aliases()->create([
                    'alias' => $alias,
                    'source' => 'manual',
                ]);
            }
        }

        return $profile;
    }
}

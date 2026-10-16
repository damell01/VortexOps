<?php

namespace App\Support;

use App\Models\User;

class VisitorCast
{
    /** A rare roll is injectable for tests; no query parameter changes the odds. */
    public static function for(?User $user, array $cast, ?int $roll = null): array
    {
        $rare = ($roll ?? random_int(1, 500)) === 1;
        if ($rare) {
            $legendary = ['mew', 'lugia', 'rayquaza'][random_int(0, 2)];
            $cast['top'] = [$legendary];
            return ['cast' => $cast, 'rare' => true];
        }
        $buddy = Buddy::chosen($user) ? Buddy::visitor($user) : null;
        if ($buddy) {
            $others = array_values(array_filter($cast['top'], fn ($name) => $name !== $buddy));
            $cast['top'] = $others === [] ? [$buddy] : array_merge(...array_map(fn ($name) => [$buddy, $name], $others));
        }
        return ['cast' => $cast, 'rare' => false];
    }
}

<?php

namespace App\Support;

use App\Models\User;

/** The Pokémon that keeps someone company on their time clock. */
class Buddy
{
    public const DEFAULT = 'eevee';

    /** Every sprite in public/img/pokemon that can be picked, in picker order. */
    public const CHOICES = ['eevee', 'pikachu', 'bulbasaur', 'charmander', 'squirtle', 'charizard', 'mew', 'gengar', 'snorlax', 'psyduck', 'meowth', 'espeon', 'umbreon'];

    public static function for(?User $user): string
    {
        $buddy = $user?->buddy;

        return in_array($buddy, self::CHOICES, true) ? $buddy : self::DEFAULT;
    }

    public static function name(string $buddy): string
    {
        return ucfirst($buddy);
    }
}

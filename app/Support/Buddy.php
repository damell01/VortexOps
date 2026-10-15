<?php

namespace App\Support;

use App\Models\User;

/**
 * The Pokémon that keeps someone company on their dashboard.
 *
 * Stored on users.buddy: a sprite name from CHOICES, 'random' for a different
 * one each day, 'none' to switch it off, or null when they have not chosen
 * yet (they are asked once, on the dashboard, and can skip).
 */
class Buddy
{
    public const DEFAULT = 'pikachu';

    public const RANDOM = 'random';

    public const NONE = 'none';

    /** Every sprite in public/img/pokemon that can be picked, in picker order. */
    public const CHOICES = ['pikachu', 'eevee', 'bulbasaur', 'charmander', 'squirtle', 'charizard', 'mew', 'gengar', 'snorlax', 'psyduck', 'meowth', 'espeon', 'umbreon', 'clefairy'];

    /** The sprite to show, or null when they switched the buddy off. */
    public static function visitor(?User $user, ?\Carbon\CarbonInterface $day = null): ?string
    {
        $buddy = $user?->buddy;

        if ($buddy === self::NONE) {
            return null;
        }
        if ($buddy === self::RANDOM) {
            // The same one all day, a different one tomorrow, not the same for everyone.
            $day ??= BusinessTime::local();
            $seed = crc32(($user?->id ?? 0) . '|' . $day->toDateString());

            return self::CHOICES[$seed % count(self::CHOICES)];
        }

        return in_array($buddy, self::CHOICES, true) ? $buddy : self::DEFAULT;
    }

    /** Kept for older callers: always a sprite name. */
    public static function for(?User $user): string
    {
        return self::visitor($user) ?? self::DEFAULT;
    }

    public static function chosen(?User $user): bool
    {
        return $user?->buddy !== null && $user->buddy !== '';
    }

    public static function name(string $buddy): string
    {
        return ucfirst($buddy);
    }

    /** @return array<string, string> value => label, for pickers */
    public static function options(): array
    {
        return [self::RANDOM => 'Random each day', self::NONE => 'None']
            + collect(self::CHOICES)->mapWithKeys(fn ($b) => [$b => self::name($b)])->all();
    }
}

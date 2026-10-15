<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * A seasonal touch for the signed-in app, switched on and off by the
 * calendar in the team's zone. Single days win over whole months.
 *
 * APP_SEASON=winter (etc.) shows a season early, to preview it; any value
 * that is not a season below is ignored.
 */
class Season
{
    /**
     * mon: who peeks out; from: 'top' hangs upside down under the top bar,
     * 'bottom' rises from the bottom-right corner; icon: the little mark next
     * to Dashboard; effect: an optional layer over the top of the page.
     *
     * @var array<string, array{mon:string, from:string, icon:string, effect:?string, title:string}>
     */
    public const SEASONS = [
        'halloween' => ['mon' => 'gengar', 'from' => 'top', 'icon' => 'pumpkin', 'effect' => null, 'title' => 'Boo!'],
        'winter' => ['mon' => 'delibird', 'from' => 'bottom', 'icon' => 'snowflake', 'effect' => 'snow', 'title' => 'Delibird brought presents!'],
        'new-year' => ['mon' => 'pikachu', 'from' => 'bottom', 'icon' => 'star', 'effect' => 'fireworks', 'title' => 'Happy New Year!'],
        'valentines' => ['mon' => 'luvdisc', 'from' => 'bottom', 'icon' => 'heart', 'effect' => 'hearts', 'title' => 'Happy Valentine\'s Day!'],
        'pokemon-day' => ['mon' => 'clefairy', 'from' => 'bottom', 'icon' => 'star', 'effect' => null, 'title' => 'Happy Pokémon Day!'],
        'st-patricks' => ['mon' => 'bulbasaur', 'from' => 'bottom', 'icon' => 'clover', 'effect' => 'clovers', 'title' => 'Happy St. Patrick\'s Day!'],
        'july-4' => ['mon' => 'charizard', 'from' => 'bottom', 'icon' => 'star', 'effect' => 'fireworks', 'title' => 'Happy Fourth of July!'],
        'thanksgiving' => ['mon' => 'snorlax', 'from' => 'bottom', 'icon' => 'pie', 'effect' => null, 'title' => 'Snorlax saved you a slice.'],
    ];

    /** Firework colours by season; anything else uses the New Year mix. */
    public const FIREWORK_COLOURS = [
        'july-4' => ['#ef4444', '#f8fafc', '#3b82f6', '#ef4444', '#93c5fd', '#f8fafc', '#dc2626', '#2563eb'],
        'default' => ['#f59e0b', '#a78bfa', '#f472b6', '#22d3ee', '#ef4444', '#4ade80', '#facc15', '#c084fc'],
    ];

    public static function current(?CarbonInterface $now = null): ?string
    {
        $preview = (string) config('app.season', '');
        if ($preview !== '' && isset(self::SEASONS[$preview])) {
            return $preview;
        }

        $now ??= now(BusinessTime::zone());

        return match (true) {
            $now->month === 1 && $now->day === 1 => 'new-year',
            $now->month === 2 && $now->day === 14 => 'valentines',
            $now->month === 2 && $now->day === 27 => 'pokemon-day', // the anniversary of Red & Green
            $now->month === 3 && $now->day === 17 => 'st-patricks',
            $now->month === 7 && $now->day === 4 => 'july-4',
            static::isThanksgivingWeek($now) => 'thanksgiving',
            $now->month === 10 => 'halloween',
            $now->month === 12 => 'winter',
            default => null,
        };
    }

    /** Monday to Sunday of the week holding the fourth Thursday of November. */
    public static function isThanksgivingWeek(CarbonInterface $now): bool
    {
        if ($now->month !== 11) {
            return false;
        }
        $thursday = \Carbon\CarbonImmutable::create($now->year, 11, 1)->nthOfMonth(4, \Carbon\CarbonInterface::THURSDAY);

        return $now->toDateString() >= $thursday->startOfWeek(\Carbon\CarbonInterface::MONDAY)->toDateString()
            && $now->toDateString() <= $thursday->endOfWeek(\Carbon\CarbonInterface::SUNDAY)->toDateString();
    }

    /** @return array{key:string, mon:string, from:string, icon:string, effect:?string, title:string}|null */
    public static function details(?CarbonInterface $now = null): ?array
    {
        $key = static::current($now);

        return $key ? ['key' => $key] + self::SEASONS[$key] : null;
    }
}

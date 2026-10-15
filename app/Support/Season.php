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
        'pokemon-day' => ['mon' => 'clefairy', 'from' => 'bottom', 'icon' => 'star', 'effect' => null, 'title' => 'Happy Pokémon Day!'],
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
            $now->month === 2 && $now->day === 27 => 'pokemon-day', // the anniversary of Red & Green
            $now->month === 10 => 'halloween',
            $now->month === 12 => 'winter',
            default => null,
        };
    }

    /** @return array{key:string, mon:string, from:string, icon:string, effect:?string, title:string}|null */
    public static function details(?CarbonInterface $now = null): ?array
    {
        $key = static::current($now);

        return $key ? ['key' => $key] + self::SEASONS[$key] : null;
    }
}

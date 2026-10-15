<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * A seasonal touch for the signed-in app, switched on and off by the
 * calendar in the team's zone. Only Halloween so far; another season is a
 * new match arm plus its markup in filament.components.season.
 */
class Season
{
    public static function current(?CarbonInterface $now = null): ?string
    {
        $now ??= now(BusinessTime::zone());

        return match ((int) $now->month) {
            10 => 'halloween',
            default => null,
        };
    }
}

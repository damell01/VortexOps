<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Which Pokémon greets you on the sign-in page, and what it says.
 *
 * The time of day (in the team's zone) picks the mood — a bright morning
 * crew, an evening "show time" crew, and a sleepy late-night crew — and the
 * day of the year rotates who from that crew is on duty. A late-night
 * Snorlax dozes on the job.
 */
class LoginGreeting
{
    /** @var array<string, array{bubble:string, caption:string, mons:list<string>}> */
    private const PERIODS = [
        'morning' => ['bubble' => 'Good morning, Trainer!', 'caption' => ':mon is on duty this morning', 'mons' => ['Pikachu', 'Bulbasaur', 'Squirtle', 'Charmander', 'Eevee']],
        'afternoon' => ['bubble' => 'Good afternoon, Trainer!', 'caption' => ':mon is on duty this afternoon', 'mons' => ['Charizard', 'Meowth', 'Psyduck', 'Eevee', 'Pikachu']],
        'evening' => ['bubble' => 'Ready for tonight\'s show?', 'caption' => ':mon is on duty tonight', 'mons' => ['Charizard', 'Mew', 'Meowth', 'Gengar']],
        'late' => ['bubble' => 'Late show tonight?', 'caption' => ':mon is pulling the late shift', 'mons' => ['Gengar', 'Snorlax']],
    ];

    /** @return array{period:string, mon:string, sprite:string, bubble:string, caption:string, sleepy:bool} */
    public static function for(?CarbonInterface $now = null): array
    {
        $now ??= now(BusinessTime::zone());
        $period = static::period((int) $now->hour);
        $crew = self::PERIODS[$period];
        $mon = $crew['mons'][(int) $now->dayOfYear % count($crew['mons'])];

        return [
            'period' => $period,
            'mon' => $mon,
            'sprite' => '/img/pokemon/'.strtolower($mon).'.gif',
            'bubble' => $crew['bubble'],
            'caption' => str_replace(':mon', $mon, $crew['caption']),
            'sleepy' => $period === 'late' && $mon === 'Snorlax',
        ];
    }

    public static function period(int $hour): string
    {
        return match (true) {
            $hour >= 22 || $hour < 5 => 'late',
            $hour >= 17 => 'evening',
            $hour >= 12 => 'afternoon',
            default => 'morning',
        };
    }
}

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

    /**
     * The corner peekers change with the hour and the week: owls and ghosts
     * at night, early birds in the morning, Fire types when sales are up on
     * last week and Water types when they're down. One of each, the same all
     * day; the season's own visitors stay in the rotation.
     */
    public static function ambient(array $cast, ?\Carbon\CarbonInterface $now = null, ?string $weather = null): array
    {
        $now ??= now(BusinessTime::zone());
        $day = crc32($now->toDateString());
        $hour = (int) $now->format('G');
        $extra = [];
        if ($hour >= 21 || $hour < 5) {
            $extra[] = PokemonData::NIGHT[$day % count(PokemonData::NIGHT)];
        } elseif ($hour < 10) {
            $extra[] = PokemonData::MORNING[$day % count(PokemonData::MORNING)];
        }
        $weather ??= self::weather();
        if ($weather === 'sun') {
            $extra[] = PokemonData::SUNNY[$day % count(PokemonData::SUNNY)];
        } elseif ($weather === 'rain') {
            $extra[] = PokemonData::RAINY[$day % count(PokemonData::RAINY)];
        }
        if ($extra) {
            $cast['bottom'] = array_values(array_unique(array_merge($extra, $cast['bottom'] ?? [])));
        }

        return $cast;
    }

    /** 'sun' when the last seven days' Whatnot net beat the seven before, 'rain' when it fell short. Checked hourly. */
    public static function weather(): ?string
    {
        return rescue(fn () => \Illuminate\Support\Facades\Cache::remember('vx-visitor-weather', 3600, function () {
            $today = now(BusinessTime::zone())->startOfDay();
            $sum = fn ($from, $to) => (float) \App\Models\Show::whereBetween('show_date', [$from->toDateString(), $to->toDateString()])->where('status', '!=', 'cancelled')->sum('whatnot_net');
            $recent = $sum($today->copy()->subDays(7), $today->copy()->subDay());
            $before = $sum($today->copy()->subDays(14), $today->copy()->subDays(8));
            if ($recent <= 0 && $before <= 0) {
                return '';
            }

            return $recent >= $before ? 'sun' : 'rain';
        }) ?: null, null, false);
    }
}

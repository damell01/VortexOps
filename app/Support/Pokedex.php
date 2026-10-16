<?php

namespace App\Support;

use App\Models\PokemonCatch;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use Illuminate\Support\Collection;

/**
 * Every approved show report catches its streamer a Pokémon. Commons turn up
 * often, Mew and Charizard rarely, and about one catch in fifty is shiny.
 *
 * The roll is worked out from the report's id, so the same report always
 * catches the same Pokémon: editing and re-approving it never adds another.
 */
class Pokedex
{
    /** species => rarity, in Pokédex order. Every one has a sprite (and a shiny) in public/img/pokemon. */
    public const SPECIES = [
        'bulbasaur' => 'common', 'charmander' => 'common', 'charizard' => 'rare', 'squirtle' => 'common',
        'pikachu' => 'uncommon', 'clefairy' => 'common', 'meowth' => 'common', 'psyduck' => 'common',
        'gengar' => 'uncommon', 'snorlax' => 'uncommon', 'mew' => 'rare', 'eevee' => 'common',
        'espeon' => 'uncommon', 'umbreon' => 'uncommon', 'delibird' => 'common', 'luvdisc' => 'common',
        'rotom' => 'uncommon',
    ];

    public const WEIGHTS = ['common' => 12, 'uncommon' => 5, 'rare' => 1];

    public const SHINY_ODDS = 50;

    /** @return array{0:string,1:bool} the species and whether it's shiny, for a report id */
    public static function roll(int $reportId): array
    {
        $total = array_sum(array_map(fn ($r) => self::WEIGHTS[$r], self::SPECIES));
        $pick = crc32('pokedex-species|' . $reportId) % $total;
        foreach (self::SPECIES as $species => $rarity) {
            $pick -= self::WEIGHTS[$rarity];
            if ($pick < 0) {
                break;
            }
        }

        return [$species, crc32('pokedex-shiny|' . $reportId) % self::SHINY_ODDS === 0];
    }

    /** Catch the report's Pokémon if it hasn't been caught yet. Null when the report has no streamer. */
    public static function catchFor(StreamerLogEntry $report): ?PokemonCatch
    {
        if (! $report->streamer_id) {
            return null;
        }
        [$species, $shiny] = self::roll((int) $report->id);

        return PokemonCatch::firstOrCreate(
            ['streamer_log_entry_id' => $report->id],
            ['streamer_id' => $report->streamer_id, 'show_id' => $report->show_id, 'species' => $species, 'shiny' => $shiny, 'caught_at' => now()],
        );
    }

    public static function name(string $species): string
    {
        return ucfirst($species);
    }

    public static function rarity(string $species): string
    {
        return self::SPECIES[$species] ?? 'common';
    }

    /**
     * A streamer's Pokédex: every species in order, with how many they've caught.
     *
     * @return array{entries: Collection<int, array{species:string,rarity:string,count:int,shiny:int}>, caught:int, total:int, catches:int, latest:?PokemonCatch}
     */
    public static function forStreamer(?Streamer $streamer): array
    {
        $rows = $streamer
            ? PokemonCatch::where('streamer_id', $streamer->id)->selectRaw('species, count(*) as n, sum(case when shiny then 1 else 0 end) as shinies')->groupBy('species')->get()->keyBy('species')
            : collect();
        $entries = collect(self::SPECIES)->map(fn ($rarity, $species) => [
            'species' => $species, 'rarity' => $rarity,
            'count' => (int) ($rows[$species]->n ?? 0), 'shiny' => (int) ($rows[$species]->shinies ?? 0),
        ])->values();

        return [
            'entries' => $entries,
            'caught' => $entries->where('count', '>', 0)->count(),
            'total' => count(self::SPECIES),
            'catches' => (int) $entries->sum('count'),
            'latest' => $streamer ? PokemonCatch::where('streamer_id', $streamer->id)->latest('caught_at')->latest('id')->first() : null,
        ];
    }
}

<?php

namespace App\Support;

use App\Models\PokemonCatch;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use Illuminate\Support\Collection;

/**
 * Every approved show report catches its streamer a Pokémon, from 100 across
 * all nine generations. Commons turn up often, rares seldom, Legendaries and
 * Mythicals about once in 70 reports, and about one catch in fifty is shiny.
 *
 * The roll is worked out from the report's id, so the same report always
 * catches the same Pokémon: editing and re-approving it never adds another.
 */
class Pokedex
{
    /**
     * species => [National Pokédex number, generation, rarity], in Pokédex order.
     * Every one has an animated sprite and a shiny in public/img/pokemon: Gen 1–5
     * are the Black & White pixel sprites, Gen 6–9 the newer animated ones.
     */
    public const DEX = [
        'bulbasaur' => [1, 1, 'common'],
        'ivysaur' => [2, 1, 'uncommon'],
        'charmander' => [4, 1, 'common'],
        'charmeleon' => [5, 1, 'uncommon'],
        'charizard' => [6, 1, 'rare'],
        'squirtle' => [7, 1, 'common'],
        'blastoise' => [9, 1, 'rare'],
        'butterfree' => [12, 1, 'common'],
        'pidgey' => [16, 1, 'common'],
        'pikachu' => [25, 1, 'uncommon'],
        'clefairy' => [35, 1, 'common'],
        'jigglypuff' => [39, 1, 'common'],
        'oddish' => [43, 1, 'common'],
        'meowth' => [52, 1, 'common'],
        'psyduck' => [54, 1, 'common'],
        'growlithe' => [58, 1, 'common'],
        'arcanine' => [59, 1, 'rare'],
        'poliwag' => [60, 1, 'common'],
        'abra' => [63, 1, 'common'],
        'machop' => [66, 1, 'common'],
        'geodude' => [74, 1, 'common'],
        'ponyta' => [77, 1, 'common'],
        'slowpoke' => [79, 1, 'common'],
        'magnemite' => [81, 1, 'common'],
        'gengar' => [94, 1, 'uncommon'],
        'onix' => [95, 1, 'uncommon'],
        'cubone' => [104, 1, 'common'],
        'scyther' => [123, 1, 'uncommon'],
        'magikarp' => [129, 1, 'common'],
        'gyarados' => [130, 1, 'rare'],
        'lapras' => [131, 1, 'rare'],
        'ditto' => [132, 1, 'uncommon'],
        'eevee' => [133, 1, 'common'],
        'vaporeon' => [134, 1, 'uncommon'],
        'jolteon' => [135, 1, 'uncommon'],
        'flareon' => [136, 1, 'uncommon'],
        'snorlax' => [143, 1, 'uncommon'],
        'articuno' => [144, 1, 'legendary'],
        'zapdos' => [145, 1, 'legendary'],
        'moltres' => [146, 1, 'legendary'],
        'dratini' => [147, 1, 'uncommon'],
        'dragonite' => [149, 1, 'rare'],
        'mewtwo' => [150, 1, 'legendary'],
        'mew' => [151, 1, 'legendary'],
        'chikorita' => [152, 2, 'common'],
        'cyndaquil' => [155, 2, 'common'],
        'totodile' => [158, 2, 'common'],
        'togepi' => [175, 2, 'uncommon'],
        'mareep' => [179, 2, 'common'],
        'marill' => [183, 2, 'common'],
        'wooper' => [194, 2, 'common'],
        'espeon' => [196, 2, 'uncommon'],
        'umbreon' => [197, 2, 'uncommon'],
        'murkrow' => [198, 2, 'common'],
        'misdreavus' => [200, 2, 'uncommon'],
        'wobbuffet' => [202, 2, 'common'],
        'heracross' => [214, 2, 'uncommon'],
        'delibird' => [225, 2, 'common'],
        'larvitar' => [246, 2, 'uncommon'],
        'tyranitar' => [248, 2, 'rare'],
        'lugia' => [249, 2, 'legendary'],
        'ho-oh' => [250, 2, 'legendary'],
        'celebi' => [251, 2, 'legendary'],
        'treecko' => [252, 3, 'common'],
        'torchic' => [255, 3, 'common'],
        'mudkip' => [258, 3, 'common'],
        'ralts' => [280, 3, 'uncommon'],
        'gardevoir' => [282, 3, 'rare'],
        'absol' => [359, 3, 'uncommon'],
        'luvdisc' => [370, 3, 'common'],
        'bagon' => [371, 3, 'uncommon'],
        'salamence' => [373, 3, 'rare'],
        'metagross' => [376, 3, 'rare'],
        'rayquaza' => [384, 3, 'legendary'],
        'jirachi' => [385, 3, 'legendary'],
        'turtwig' => [387, 4, 'common'],
        'chimchar' => [390, 4, 'common'],
        'piplup' => [393, 4, 'common'],
        'shinx' => [403, 4, 'common'],
        'pachirisu' => [417, 4, 'common'],
        'gible' => [443, 4, 'uncommon'],
        'garchomp' => [445, 4, 'rare'],
        'riolu' => [447, 4, 'uncommon'],
        'lucario' => [448, 4, 'rare'],
        'rotom' => [479, 4, 'uncommon'],
        'darkrai' => [491, 4, 'legendary'],
        'snivy' => [495, 5, 'common'],
        'tepig' => [498, 5, 'common'],
        'oshawott' => [501, 5, 'common'],
        'zorua' => [570, 5, 'uncommon'],
        'litwick' => [607, 5, 'common'],
        'froakie' => [656, 6, 'common'],
        'greninja' => [658, 6, 'rare'],
        'sylveon' => [700, 6, 'uncommon'],
        'rowlet' => [722, 7, 'common'],
        'mimikyu' => [778, 7, 'uncommon'],
        'scorbunny' => [813, 8, 'common'],
        'wooloo' => [831, 8, 'common'],
        'sprigatito' => [906, 9, 'common'],
        'pawmi' => [921, 9, 'common'],
    ];

    /** How often each rarity turns up: Legendaries and Mythicals are about 1 report in 70 between them. */
    public const WEIGHTS = ['common' => 24, 'uncommon' => 10, 'rare' => 4, 'legendary' => 2];

    public const SHINY_ODDS = 50;

    /** @return array{0:string,1:bool} the species and whether it's shiny, for a report id */
    public static function roll(int $reportId): array
    {
        $total = array_sum(array_map(fn ($e) => self::WEIGHTS[$e[2]], self::DEX));
        $pick = crc32('pokedex-species|' . $reportId) % $total;
        foreach (self::DEX as $species => [, , $rarity]) {
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

    public static function rarity(string $species): string
    {
        return self::DEX[$species][2] ?? 'common';
    }

    public static function number(string $species): int
    {
        return self::DEX[$species][0] ?? 0;
    }

    public static function exists(?string $species): bool
    {
        return $species !== null && isset(self::DEX[$species]);
    }

    public static function name(string $species): string
    {
        return $species === 'ho-oh' ? 'Ho-Oh' : ucfirst($species);
    }

    /**
     * A streamer's Pokédex: every species in order, with how many they've caught.
     *
     * @return array{entries: Collection<int, array{species:string,rarity:string,number:int,generation:int,count:int,shiny:int}>, caught:int, total:int, catches:int, latest:?PokemonCatch, recent: Collection<int, PokemonCatch>}
     */
    public static function forStreamer(?Streamer $streamer): array
    {
        $rows = $streamer
            ? PokemonCatch::where('streamer_id', $streamer->id)->selectRaw('species, count(*) as n, sum(case when shiny then 1 else 0 end) as shinies')->groupBy('species')->get()->keyBy('species')
            : collect();
        $entries = collect(self::DEX)->map(fn ($e, $species) => [
            'species' => $species, 'rarity' => $e[2], 'number' => $e[0], 'generation' => $e[1],
            'count' => (int) ($rows[$species]->n ?? 0), 'shiny' => (int) ($rows[$species]->shinies ?? 0),
        ])->values();

        return [
            'entries' => $entries,
            'caught' => $entries->where('count', '>', 0)->count(),
            'total' => count(self::DEX),
            'catches' => (int) $entries->sum('count'),
            'latest' => ($recent = $streamer ? PokemonCatch::where('streamer_id', $streamer->id)->latest('caught_at')->latest('id')->limit(12)->get() : collect())->first(),
            'recent' => $recent,
        ];
    }
}

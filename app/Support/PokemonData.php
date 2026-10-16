<?php

namespace App\Support;

/**
 * The reference data the Pokémon games run on (App\Support\PokemonGames):
 * types, regions, evolution lines, the species you can only earn (never
 * roll from a report), and the little pools visitors are drawn from.
 *
 * Every species named here has a sprite and a shiny in public/img/pokemon.
 */
class PokemonData
{
    /**
     * Species you earn rather than roll: evolutions the 100-species Pokédex
     * skips, and the birds packers catch. species => [number, generation, rarity].
     */
    public const EXTRA = [
        'kadabra' => [64, 1, 'uncommon'],
        'alakazam' => [65, 1, 'rare'],
        'machoke' => [67, 1, 'uncommon'],
        'machamp' => [68, 1, 'rare'],
        'slowbro' => [80, 1, 'uncommon'],
        'voltorb' => [100, 1, 'common'],
        'chansey' => [113, 1, 'uncommon'],
        'kangaskhan' => [115, 1, 'uncommon'],
        'porygon' => [137, 1, 'uncommon'],
        'hoothoot' => [163, 2, 'common'],
        'noctowl' => [164, 2, 'uncommon'],
        'sudowoodo' => [185, 2, 'uncommon'],
        'slowking' => [199, 2, 'rare'],
        'unown' => [201, 2, 'uncommon'],
        'porygon2' => [233, 2, 'rare'],
        'smeargle' => [235, 2, 'uncommon'],
        'wingull' => [278, 3, 'common'],
        'pelipper' => [279, 3, 'uncommon'],
        'sableye' => [302, 3, 'uncommon'],
        'swablu' => [333, 3, 'common'],
        'bidoof' => [399, 4, 'common'],
        'bronzong' => [437, 4, 'rare'],
        'chatot' => [441, 4, 'common'],
    ];

    /** Generation => region name. A trainer starts in Kanto and opens the next region by catching most of the last. */
    public const REGIONS = [1 => 'Kanto', 2 => 'Johto', 3 => 'Hoenn', 4 => 'Sinnoh', 5 => 'Unova', 6 => 'Kalos', 7 => 'Alola', 8 => 'Galar', 9 => 'Paldea'];

    /** Share of a region's Pokémon you need caught to open the next one. */
    public const REGION_UNLOCK_SHARE = 0.6;

    /** species => what it can evolve into (one is picked when there are several, as with Eevee). */
    public const EVOLUTIONS = [
        'bulbasaur' => ['ivysaur'],
        'charmander' => ['charmeleon'], 'charmeleon' => ['charizard'],
        'squirtle' => ['blastoise'],
        'abra' => ['kadabra'], 'kadabra' => ['alakazam'],
        'machop' => ['machoke'], 'machoke' => ['machamp'],
        'growlithe' => ['arcanine'],
        'slowpoke' => ['slowbro', 'slowking'],
        'gastly' => ['haunter'], 'haunter' => ['gengar'],
        'magikarp' => ['gyarados'],
        'eevee' => ['vaporeon', 'jolteon', 'flareon', 'espeon', 'umbreon', 'sylveon'],
        'porygon' => ['porygon2'],
        'dratini' => ['dragonite'],
        'hoothoot' => ['noctowl'],
        'larvitar' => ['tyranitar'],
        'ralts' => ['gardevoir'],
        'wingull' => ['pelipper'],
        'shuppet' => ['banette'],
        'snorunt' => ['froslass'],
        'sunkern' => ['sunflora'],
        'bagon' => ['salamence'],
        'gible' => ['garchomp'],
        'riolu' => ['lucario'],
        'litwick' => ['chandelure'],
        'froakie' => ['greninja'],
    ];

    /** Every evolution waits for a run of on-time reports this long; the second stage of a line waits for STREAK_FINAL. */
    public const STREAK_STEP = 5;
    public const STREAK_FINAL = 15;

    /** What packers catch, one for every DELIVERY_EVERY items they pack. */
    public const DELIVERY = ['pidgey', 'wingull', 'swablu', 'chatot', 'hoothoot', 'delibird', 'farfetchd', 'drifloon', 'pelipper', 'noctowl'];
    public const DELIVERY_EVERY = 50;

    /** Admins catch one for every this many reports they approve, and every this many hours issues they settle. */
    public const APPROVALS_EVERY = 10;
    public const HOURS_EVERY = 10;

    /** Visitors that turn up by time of day and by how the week is going. */
    public const NIGHT = ['hoothoot', 'noctowl', 'gastly', 'murkrow', 'misdreavus', 'umbreon', 'gengar'];
    public const MORNING = ['pidgey', 'sunkern', 'hoppip', 'wingull', 'chikorita', 'ponyta'];
    public const SUNNY = ['charmander', 'growlithe', 'ponyta', 'flareon', 'torchic', 'sunkern'];
    public const RAINY = ['squirtle', 'psyduck', 'poliwag', 'marill', 'wooper', 'lotad', 'mudkip', 'piplup'];

    /** Every starter in the Pokédex: catching them all earns the Starter badge. */
    public const STARTERS = ['bulbasaur', 'charmander', 'squirtle', 'chikorita', 'cyndaquil', 'totodile', 'treecko', 'torchic', 'mudkip',
        'turtwig', 'chimchar', 'piplup', 'snivy', 'tepig', 'oshawott', 'froakie', 'rowlet', 'scorbunny', 'sprigatito'];

    /** A type earns a badge once every Pokédex species of it is caught; only types with at least this many qualify. */
    public const BADGE_MIN_SPECIES = 3;

    public const TYPES = [
        // The Pokédex
        'bulbasaur' => ['grass', 'poison'], 'ivysaur' => ['grass', 'poison'], 'charmander' => ['fire'], 'charmeleon' => ['fire'],
        'charizard' => ['fire', 'flying'], 'squirtle' => ['water'], 'blastoise' => ['water'], 'butterfree' => ['bug', 'flying'],
        'pidgey' => ['normal', 'flying'], 'pikachu' => ['electric'], 'clefairy' => ['fairy'], 'jigglypuff' => ['normal', 'fairy'],
        'oddish' => ['grass', 'poison'], 'meowth' => ['normal'], 'psyduck' => ['water'], 'growlithe' => ['fire'], 'arcanine' => ['fire'],
        'poliwag' => ['water'], 'abra' => ['psychic'], 'machop' => ['fighting'], 'geodude' => ['rock', 'ground'], 'ponyta' => ['fire'],
        'slowpoke' => ['water', 'psychic'], 'magnemite' => ['electric', 'steel'], 'gengar' => ['ghost', 'poison'], 'onix' => ['rock', 'ground'],
        'cubone' => ['ground'], 'scyther' => ['bug', 'flying'], 'magikarp' => ['water'], 'gyarados' => ['water', 'flying'],
        'lapras' => ['water', 'ice'], 'ditto' => ['normal'], 'eevee' => ['normal'], 'vaporeon' => ['water'], 'jolteon' => ['electric'],
        'flareon' => ['fire'], 'snorlax' => ['normal'], 'articuno' => ['ice', 'flying'], 'zapdos' => ['electric', 'flying'],
        'moltres' => ['fire', 'flying'], 'dratini' => ['dragon'], 'dragonite' => ['dragon', 'flying'], 'mewtwo' => ['psychic'],
        'mew' => ['psychic'], 'chikorita' => ['grass'], 'cyndaquil' => ['fire'], 'totodile' => ['water'], 'togepi' => ['fairy'],
        'mareep' => ['electric'], 'marill' => ['water', 'fairy'], 'wooper' => ['water', 'ground'], 'espeon' => ['psychic'],
        'umbreon' => ['dark'], 'murkrow' => ['dark', 'flying'], 'misdreavus' => ['ghost'], 'wobbuffet' => ['psychic'],
        'heracross' => ['bug', 'fighting'], 'delibird' => ['ice', 'flying'], 'larvitar' => ['rock', 'ground'], 'tyranitar' => ['rock', 'dark'],
        'lugia' => ['psychic', 'flying'], 'ho-oh' => ['fire', 'flying'], 'celebi' => ['psychic', 'grass'], 'treecko' => ['grass'],
        'torchic' => ['fire'], 'mudkip' => ['water'], 'ralts' => ['psychic', 'fairy'], 'gardevoir' => ['psychic', 'fairy'],
        'absol' => ['dark'], 'luvdisc' => ['water'], 'bagon' => ['dragon'], 'salamence' => ['dragon', 'flying'],
        'metagross' => ['steel', 'psychic'], 'rayquaza' => ['dragon', 'flying'], 'jirachi' => ['steel', 'psychic'], 'turtwig' => ['grass'],
        'chimchar' => ['fire'], 'piplup' => ['water'], 'shinx' => ['electric'], 'pachirisu' => ['electric'], 'gible' => ['dragon', 'ground'],
        'garchomp' => ['dragon', 'ground'], 'riolu' => ['fighting'], 'lucario' => ['fighting', 'steel'], 'rotom' => ['electric', 'ghost'],
        'darkrai' => ['dark'], 'snivy' => ['grass'], 'tepig' => ['fire'], 'oshawott' => ['water'], 'zorua' => ['dark'],
        'litwick' => ['ghost', 'fire'], 'froakie' => ['water'], 'greninja' => ['water', 'dark'], 'sylveon' => ['fairy'],
        'rowlet' => ['grass', 'flying'], 'mimikyu' => ['ghost', 'fairy'], 'scorbunny' => ['fire'], 'wooloo' => ['normal'],
        'sprigatito' => ['grass'], 'pawmi' => ['electric'],
        // Event-only
        'gastly' => ['ghost', 'poison'], 'haunter' => ['ghost', 'poison'], 'shuppet' => ['ghost'], 'banette' => ['ghost'],
        'duskull' => ['ghost'], 'drifloon' => ['ghost', 'flying'], 'yamask' => ['ghost'], 'chandelure' => ['ghost', 'fire'],
        'pumpkaboo' => ['ghost', 'grass'], 'farfetchd' => ['normal', 'flying'], 'sunkern' => ['grass'], 'sunflora' => ['grass'],
        'teddiursa' => ['normal'], 'seedot' => ['grass'], 'deerling' => ['normal', 'grass'], 'skiddo' => ['grass'],
        'applin' => ['grass', 'dragon'], 'swinub' => ['ice', 'ground'], 'stantler' => ['normal'], 'snorunt' => ['ice'],
        'spheal' => ['ice', 'water'], 'regice' => ['ice'], 'snover' => ['grass', 'ice'], 'froslass' => ['ice', 'ghost'],
        'vanillite' => ['ice'], 'cubchoo' => ['ice'], 'smoochum' => ['ice', 'psychic'], 'skitty' => ['normal'],
        'woobat' => ['psychic', 'flying'], 'audino' => ['normal'], 'cottonee' => ['grass', 'fairy'], 'alomomola' => ['water'],
        'spritzee' => ['fairy'], 'hoppip' => ['grass', 'flying'], 'budew' => ['grass', 'poison'], 'burmy' => ['bug'],
        'combee' => ['bug', 'flying'], 'cherubi' => ['grass'], 'sewaddle' => ['bug', 'grass'], 'petilil' => ['grass'],
        'flabebe' => ['fairy'], 'krabby' => ['water'], 'horsea' => ['water'], 'staryu' => ['water'], 'lotad' => ['water', 'grass'],
        'wailmer' => ['water'], 'corphish' => ['water'], 'popplio' => ['water'],
        // Earned only
        'kadabra' => ['psychic'], 'alakazam' => ['psychic'], 'machoke' => ['fighting'], 'machamp' => ['fighting'],
        'slowbro' => ['water', 'psychic'], 'slowking' => ['water', 'psychic'], 'voltorb' => ['electric'], 'chansey' => ['normal'],
        'kangaskhan' => ['normal'], 'porygon' => ['normal'], 'porygon2' => ['normal'], 'hoothoot' => ['normal', 'flying'],
        'noctowl' => ['normal', 'flying'], 'sudowoodo' => ['rock'], 'unown' => ['psychic'], 'smeargle' => ['normal'],
        'wingull' => ['water', 'flying'], 'pelipper' => ['water', 'flying'], 'sableye' => ['dark', 'ghost'], 'swablu' => ['normal', 'flying'],
        'bidoof' => ['normal'], 'bronzong' => ['steel', 'psychic'], 'chatot' => ['normal', 'flying'],
    ];

    /** @return list<string> */
    public static function types(string $species): array
    {
        return self::TYPES[$species] ?? ['normal'];
    }

    /** The species it evolved from, if any. */
    public static function preEvolution(string $species): ?string
    {
        foreach (self::EVOLUTIONS as $from => $to) {
            if (in_array($species, $to, true)) {
                return $from;
            }
        }

        return null;
    }

    /** 1 for the first form of a line, 2 for the middle one, 3 for the last. */
    public static function stage(string $species): int
    {
        $stage = 1;
        for ($s = $species; ($s = self::preEvolution($s)) !== null;) {
            $stage++;
        }

        return $stage;
    }
}

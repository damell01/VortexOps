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
     * 'bottom' rises from the bottom-right corner; effect: an optional layer
     * over the top of the page.
     *
     * @var array<string, array{mon:string, from:string, effect:?string, title:string}>
     */
    public const SEASONS = [
        'halloween' => ['mon' => 'gengar', 'from' => 'top', 'effect' => null, 'title' => 'Boo!'],
        'winter' => ['mon' => 'delibird', 'from' => 'bottom', 'effect' => 'snow', 'title' => 'Delibird brought presents!'],
        'new-year' => ['mon' => 'pikachu', 'from' => 'bottom', 'effect' => 'fireworks', 'title' => 'Happy New Year!'],
        'valentines' => ['mon' => 'luvdisc', 'from' => 'bottom', 'effect' => 'hearts', 'title' => 'Happy Valentine\'s Day!'],
        'pokemon-day' => ['mon' => 'clefairy', 'from' => 'bottom', 'effect' => null, 'title' => 'Happy Pokémon Day!'],
        'st-patricks' => ['mon' => 'bulbasaur', 'from' => 'bottom', 'effect' => 'clovers', 'title' => 'Happy St. Patrick\'s Day!'],
        'july-4' => ['mon' => 'charizard', 'from' => 'bottom', 'effect' => 'fireworks', 'title' => 'Happy Fourth of July!'],
        'thanksgiving' => ['mon' => 'snorlax', 'from' => 'bottom', 'effect' => null, 'title' => 'Snorlax saved you a slice.'],
    ];

    /** Firework colours by season; anything else uses the New Year mix. */
    public const FIREWORK_COLOURS = [
        'july-4' => ['#ef4444', '#f8fafc', '#3b82f6', '#ef4444', '#93c5fd', '#f8fafc', '#dc2626', '#2563eb'],
        'default' => ['#f59e0b', '#a78bfa', '#f472b6', '#22d3ee', '#ef4444', '#4ade80', '#facc15', '#c084fc'],
    ];

    /**
     * Longer catch events, separate from the visitors above: while one runs,
     * its themed Pokémon turn up three times as often in report catches, a few
     * event-only species can be caught (and only then), celebrations borrow
     * its Pokémon, and a thin banner says so. APP_SEASON=harvest (etc.)
     * previews one.
     *
     * boost: species in the main Pokédex, three times as likely.
     * species: event-only catches, species => [National Pokédex no., rarity].
     * swap: celebration sprite => this season's stand-in.
     * pool: who turns up in the small "it worked" pops.
     *
     * @var array<string, array{label:string, emoji:string, banner:string, boost:list<string>, species:array<string, array{0:int,1:string}>, swap:array<string,string>, pool:list<string>}>
     */
    public const EVENTS = [
        'halloween' => [
            'label' => 'Spooky season', 'emoji' => '🎃', 'banner' => 'Ghost types are out! Ghosts turn up 3× as often, and a few only appear this month.',
            'boost' => ['gengar', 'misdreavus', 'mimikyu', 'litwick', 'murkrow', 'zorua', 'darkrai', 'absol', 'umbreon', 'cubone'],
            'species' => ['gastly' => [92, 'common'], 'haunter' => [93, 'uncommon'], 'shuppet' => [353, 'common'], 'banette' => [354, 'uncommon'], 'duskull' => [355, 'common'], 'drifloon' => [425, 'common'], 'yamask' => [562, 'common'], 'chandelure' => [609, 'rare'], 'pumpkaboo' => [710, 'common']],
            'swap' => ['machamp' => 'gengar', 'chansey' => 'mimikyu', 'pidgey' => 'murkrow', 'porygon' => 'rotom', 'bidoof' => 'pumpkaboo', 'pelipper' => 'drifloon', 'alakazam' => 'chandelure', 'kangaskhan' => 'banette', 'meowth' => 'sableye', 'snorlax' => 'duskull'],
            'pool' => ['gastly', 'haunter', 'gengar', 'pumpkaboo', 'drifloon', 'shuppet', 'duskull', 'litwick', 'misdreavus', 'mimikyu', 'yamask'],
        ],
        'harvest' => [
            'label' => 'Harvest season', 'emoji' => '🍂', 'banner' => 'Grass and Ground types are out, with harvest Pokémon you can only catch in November.',
            'boost' => ['bulbasaur', 'oddish', 'chikorita', 'treecko', 'turtwig', 'snivy', 'sprigatito', 'rowlet', 'cubone', 'geodude', 'wooper', 'snorlax'],
            'species' => ['farfetchd' => [83, 'uncommon'], 'sunkern' => [191, 'common'], 'sunflora' => [192, 'uncommon'], 'teddiursa' => [216, 'common'], 'seedot' => [273, 'common'], 'deerling' => [585, 'common'], 'skiddo' => [672, 'common'], 'applin' => [840, 'rare']],
            'swap' => ['bidoof' => 'teddiursa', 'pidgey' => 'farfetchd', 'chansey' => 'sunflora', 'snorlax' => 'snorlax'],
            'pool' => ['sunkern', 'teddiursa', 'seedot', 'deerling', 'skiddo', 'applin', 'oddish', 'bulbasaur', 'turtwig'],
        ],
        'winter' => [
            'label' => 'Winter', 'emoji' => '❄️', 'banner' => 'Ice types are out until January 5th, and Regice might just show up.',
            'boost' => ['delibird', 'lapras', 'articuno', 'piplup', 'snorlax', 'wooloo', 'vaporeon'],
            'species' => ['swinub' => [220, 'common'], 'stantler' => [234, 'uncommon'], 'snorunt' => [361, 'common'], 'spheal' => [363, 'common'], 'regice' => [378, 'legendary'], 'snover' => [459, 'common'], 'froslass' => [478, 'rare'], 'vanillite' => [582, 'common'], 'cubchoo' => [613, 'common']],
            'swap' => ['pelipper' => 'delibird', 'meowth' => 'delibird', 'machamp' => 'stantler', 'bidoof' => 'cubchoo', 'pidgey' => 'spheal', 'snorlax' => 'snorlax'],
            'pool' => ['delibird', 'snorunt', 'spheal', 'swinub', 'cubchoo', 'vanillite', 'snover', 'piplup'],
        ],
        'valentines' => [
            'label' => "Valentine's", 'emoji' => '💘', 'banner' => 'Love is in the air until the 14th: lovely Pokémon are out, with a few you can only catch now.',
            'boost' => ['luvdisc', 'sylveon', 'clefairy', 'jigglypuff', 'togepi', 'gardevoir', 'ralts'],
            'species' => ['smoochum' => [238, 'common'], 'skitty' => [300, 'common'], 'woobat' => [527, 'common'], 'audino' => [531, 'uncommon'], 'cottonee' => [546, 'common'], 'alomomola' => [594, 'rare'], 'spritzee' => [682, 'common']],
            'swap' => ['chansey' => 'audino', 'pidgey' => 'woobat', 'pelipper' => 'luvdisc', 'meowth' => 'skitty'],
            'pool' => ['luvdisc', 'smoochum', 'skitty', 'cottonee', 'spritzee', 'clefairy', 'sylveon', 'woobat'],
        ],
        'spring' => [
            'label' => 'Spring', 'emoji' => '🌸', 'banner' => 'Bug and Fairy types are blooming, and shiny Eevee are about.',
            'boost' => ['butterfree', 'scyther', 'heracross', 'clefairy', 'togepi', 'sylveon', 'oddish', 'chikorita', 'eevee'],
            'species' => ['hoppip' => [187, 'common'], 'budew' => [406, 'common'], 'burmy' => [412, 'common'], 'combee' => [415, 'common'], 'cherubi' => [420, 'common'], 'sewaddle' => [540, 'common'], 'petilil' => [548, 'uncommon'], 'flabebe' => [669, 'uncommon']],
            'swap' => ['pidgey' => 'hoppip', 'bidoof' => 'combee', 'chansey' => 'petilil'],
            'pool' => ['hoppip', 'budew', 'cherubi', 'combee', 'sewaddle', 'petilil', 'flabebe', 'butterfree'],
        ],
        'summer' => [
            'label' => 'Summer', 'emoji' => '☀️', 'banner' => 'Water and Fire types are out for the summer, beach Pokémon included.',
            'boost' => ['squirtle', 'psyduck', 'poliwag', 'magikarp', 'lapras', 'marill', 'mudkip', 'piplup', 'oshawott', 'froakie', 'charmander', 'growlithe', 'ponyta', 'torchic', 'tepig', 'chimchar', 'cyndaquil', 'scorbunny'],
            'species' => ['krabby' => [98, 'common'], 'horsea' => [116, 'common'], 'staryu' => [120, 'common'], 'lotad' => [270, 'common'], 'wailmer' => [320, 'uncommon'], 'corphish' => [341, 'common'], 'popplio' => [728, 'uncommon']],
            'swap' => ['pidgey' => 'wingull', 'bidoof' => 'krabby', 'machamp' => 'wailmer'],
            'pool' => ['krabby', 'staryu', 'lotad', 'corphish', 'popplio', 'wingull', 'squirtle', 'psyduck'],
        ],
    ];

    /** The catch event running now, if any (team's time zone). */
    public static function event(?CarbonInterface $now = null): ?string
    {
        $preview = (string) config('app.season', '');
        if ($preview !== '') {
            return isset(self::EVENTS[$preview]) ? $preview : null;
        }

        $now ??= now(BusinessTime::zone());
        $md = $now->format('m-d');

        return match (true) {
            $now->month === 10 => 'halloween',
            $now->month === 11 => 'harvest',
            $now->month === 12 || $md <= '01-05' => 'winter',
            $now->month === 2 && $now->day <= 14 => 'valentines',
            in_array($now->month, [3, 4], true) => 'spring',
            in_array($now->month, [6, 7, 8], true) => 'summer',
            default => null,
        };
    }

    /** @return array{key:string, label:string, emoji:string, banner:string, boost:list<string>, species:array<string, array{0:int,1:string}>, swap:array<string,string>, pool:list<string>}|null */
    public static function eventDetails(?CarbonInterface $now = null): ?array
    {
        $key = static::event($now);

        return $key ? ['key' => $key] + self::EVENTS[$key] : null;
    }

    /** Which event an event-only species belongs to. */
    public static function eventOf(string $species): ?string
    {
        foreach (self::EVENTS as $key => $event) {
            if (isset($event['species'][$species])) {
                return $key;
            }
        }

        return null;
    }

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

    /** Who visits when no season or catch event is running. */
    public const EVERYDAY_VISITORS = [
        'top' => ['pikachu', 'mew', 'eevee', 'gengar', 'jigglypuff', 'togepi', 'psyduck', 'mimikyu'],
        'bottom' => ['pikachu', 'eevee', 'bulbasaur', 'squirtle', 'charmander', 'snorlax', 'meowth', 'piplup'],
    ];

    /** Seasonal casts for the visitors: who hangs from the top bar and who peeks up from the corner. */
    private const VISITOR_CASTS = [
        'halloween' => ['top' => ['gengar', 'haunter', 'mimikyu', 'pumpkaboo', 'drifloon', 'misdreavus'], 'bottom' => ['gastly', 'duskull', 'litwick', 'shuppet', 'yamask']],
        'harvest' => ['top' => ['teddiursa', 'farfetchd', 'oddish'], 'bottom' => ['sunkern', 'seedot', 'skiddo', 'deerling', 'snorlax']],
        'winter' => ['top' => ['delibird', 'snorunt', 'froslass'], 'bottom' => ['spheal', 'cubchoo', 'swinub', 'vanillite', 'piplup']],
        'valentines' => ['top' => ['luvdisc', 'woobat', 'sylveon'], 'bottom' => ['smoochum', 'skitty', 'audino', 'clefairy']],
        'spring' => ['top' => ['hoppip', 'butterfree', 'combee'], 'bottom' => ['budew', 'cherubi', 'petilil', 'eevee']],
        'summer' => ['top' => ['wingull', 'staryu', 'psyduck'], 'bottom' => ['krabby', 'lotad', 'corphish', 'squirtle', 'popplio']],
    ];

    /**
     * The Pokémon that drop in on every page: some hang upside down from
     * under the top bar, others peek up from the bottom corner. A holiday's
     * own visitor leads; otherwise the running catch event's cast, or the
     * everyday regulars.
     *
     * @return array{top: list<string>, bottom: list<string>}
     */
    public static function visitors(?CarbonInterface $now = null): array
    {
        $cast = self::VISITOR_CASTS[static::event($now) ?? ''] ?? self::EVERYDAY_VISITORS;
        $day = static::details($now);
        if ($day) {
            $side = $day['from'] === 'top' ? 'top' : 'bottom';
            $cast[$side] = array_values(array_unique([$day['mon'], ...$cast[$side]]));
        }

        return $cast;
    }

    /** @return array{key:string, mon:string, from:string, effect:?string, title:string}|null */
    public static function details(?CarbonInterface $now = null): ?array
    {
        $key = static::current($now);

        return $key ? ['key' => $key] + self::SEASONS[$key] : null;
    }
}

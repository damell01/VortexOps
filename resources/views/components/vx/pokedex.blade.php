@props(['streamer' => null, 'trainer' => null, 'title' => 'Your Pokédex', 'open' => false, 'interactive' => false])
{{-- A trainer's Pokédex (App\Support\Pokedex::forTrainer): regions open as you fill the last one, badges for whole types,
     a gold frame at ten of a kind. Uncaught ones show as silhouettes. $interactive adds Release buttons (MyPokedex). --}}
@php
    $trainer ??= \App\Support\Trainer::forStreamer($streamer);
    $dex = \App\Support\Pokedex::forTrainer($trainer);
    $stats = \App\Support\PokemonGames::rarityStats();
    $uid = $trainer?->key() ?? 'none';
    $byRegion = $dex['entries']->groupBy('generation');
    $tip = function (array $e) use ($stats) {
        $s = $stats[$e['species']] ?? null;
        $rate = $s && $s['pct'] !== null ? $s['pct'].'% of catches' : 'Earned, not rolled';
        $who = $s ? ($s['owners'] === 1 ? 'only 1 trainer has it' : $s['owners'].' trainers have it') : '';
        return ($e['count'] > 0 ? \App\Support\Pokedex::name($e['species']).' · caught '.$e['count'].'×'.($e['shiny'] ? ' · '.$e['shiny'].' shiny' : '') : 'Not caught yet').' · '.$rate.($who ? ' · '.$who : '');
    };
    $stars = ['uncommon' => '★', 'rare' => '★★', 'legendary' => '★★★'];
@endphp
<section class="vx-dex" aria-labelledby="vx-dex-title-{{ $uid }}">
    <div class="vx-dex-head">
        <span class="vx-dex-ball" aria-hidden="true"></span>
        <div class="min-w-0">
            <h2 id="vx-dex-title-{{ $uid }}">{{ $title }}</h2>
            <p>{{ $dex['caught'] }} of {{ $dex['total'] }} caught · {{ $dex['catches'] }} {{ \Illuminate\Support\Str::plural('catch', $dex['catches']) }} · {{ count($dex['regions']) }} of {{ count(\App\Support\PokemonData::REGIONS) }} regions open</p>
        </div>
        @if ($dex['latest'])
            <div class="vx-dex-latest" title="Caught {{ $dex['latest']->caught_at?->diffForHumans() }} · {{ \App\Support\PokemonGames::sourceLabel($dex['latest']->source ?? 'report') }}">
                <img src="/img/pokemon3d/{{ $dex['latest']->shiny ? 'shiny/' : '' }}{{ $dex['latest']->species }}.gif" alt="">
                <span>Latest<br><b>{{ $dex['latest']->shiny ? '✨ ' : '' }}{{ \App\Support\Pokedex::name($dex['latest']->species) }}</b></span>
            </div>
        @endif
    </div>
    <div class="vx-dex-meter" role="img" aria-label="{{ $dex['caught'] }} of {{ $dex['total'] }} caught"><i style="width: {{ round($dex['caught'] * 100 / max(1, $dex['total'])) }}%"></i></div>

    {{-- Badges: every Pokédex species of a type caught, like gym badges. --}}
    @php $earnedBadges = collect($dex['badges'])->where('earned', true)->count(); @endphp
    <details class="vx-dex-badges-wrap">
    <summary>Badges <span>{{ $earnedBadges }} of {{ count($dex['badges']) }} earned · catch every Pokémon of a type to earn its badge</span></summary>
    <div class="vx-dex-badges" aria-label="Badges">
        @foreach ($dex['badges'] as $b)
            <span @class(['vx-dex-badge', 'is-earned' => $b['earned'], 'type-'.$b['key']]) title="{{ $b['label'] }} badge: {{ $b['have'] }} of {{ $b['need'] }} caught{{ $b['earned'] ? ' — earned!' : '' }}">
                <img src="/img/pokemon3d/{{ $b['sprite'] }}.gif" alt="" loading="lazy"><b>{{ $b['label'] }}</b><small>{{ $b['earned'] ? '✓' : $b['have'].'/'.$b['need'] }}</small>
            </span>
        @endforeach
    </div>
    </details>

    @if ($dex['recent']->isNotEmpty())
        <div class="vx-dex-recent" aria-label="Recent catches">
            @foreach ($dex['recent'] as $c)
                <span @class(['vx-dex-chip', 'is-shiny' => $c->shiny, 'is-'.\App\Support\Pokedex::rarity($c->species)]) title="{{ ($c->shiny ? 'Shiny ' : '').\App\Support\Pokedex::name($c->species) }} · {{ $c->caught_at?->format('M j') }} · {{ \App\Support\PokemonGames::sourceLabel($c->source ?? 'report') }}{{ $c->evolved_from ? ' · evolved from '.\App\Support\Pokedex::name($c->evolved_from) : '' }}">
                    <img src="/img/pokemon3d/{{ $c->shiny ? 'shiny/' : '' }}{{ $c->species }}.gif" alt="" loading="lazy"><b>{{ \App\Support\Pokedex::name($c->species) }}</b>
                </span>
            @endforeach
        </div>
    @else
        <p class="vx-dex-empty">No catches yet. File a show report, or get some work done, and see what you catch!</p>
    @endif
    @foreach ($dex['events'] as $ev)
        {{-- Event-only Pokémon (App\Support\Season::EVENTS): only catchable while their event runs. --}}
        <div @class(['vx-dex-event', 'is-running' => $ev['running']]) data-vx-dex-event="{{ $ev['key'] }}">
            <p class="vx-dex-event-head"><span aria-hidden="true">{{ $ev['emoji'] }}</span> <b>{{ $ev['label'] }} event</b>
                <span>{{ $ev['entries']->where('count', '>', 0)->count() }} of {{ $ev['entries']->count() }} caught{{ $ev['running'] ? ' · only catchable now' : '' }}</span></p>
            <ul class="vx-dex-grid vx-dex-grid--event">
                @foreach ($ev['entries'] as $e)
                    <li @class(['vx-dex-cell', 'is-caught' => $e['count'] > 0, 'is-gold' => $e['count'] >= 10, 'is-'.$e['rarity']]) title="{{ $tip($e + ['shiny' => $e['shiny']]) }}">
                        <span class="vx-dex-no">#{{ str_pad((string) $e['number'], 3, '0', STR_PAD_LEFT) }}</span>
                        <img src="/img/pokemon3d/{{ $e['shiny'] ? 'shiny/' : '' }}{{ $e['species'] }}.gif" alt="" loading="lazy">
                        <span class="vx-dex-name">{{ $e['count'] > 0 ? \App\Support\Pokedex::name($e['species']) : '???' }}</span>
                        @if ($e['count'] > 0)<span class="vx-dex-n">×{{ $e['count'] }}@if ($e['shiny']) <b>✨</b>@endif</span>@endif
                        @if ($e['rarity'] !== 'common')<span class="vx-dex-rarity" aria-label="{{ ucfirst($e['rarity']) }}">{{ $stars[$e['rarity']] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
    <details class="vx-dex-all" @if ($open) open @endif>
        <summary>See all {{ $dex['total'] }} Pokémon <span>{{ $dex['caught'] }} caught</span></summary>
        @foreach (\App\Support\PokemonData::REGIONS as $gen => $region)
            @php $list = $byRegion->get($gen, collect()); $isOpen = in_array($gen, $dex['regions'], true); @endphp
            @continue($list->isEmpty())
            <div @class(['vx-dex-region', 'is-locked' => ! $isOpen])>
                <p class="vx-dex-region-h"><b>{{ $region }}</b> <span>{{ $list->where('count', '>', 0)->count() }} of {{ $list->count() }}</span>
                    @unless ($isOpen)<span class="vx-dex-lock">🔒 Opens when you’ve caught {{ (int) round(\App\Support\PokemonData::REGION_UNLOCK_SHARE * 100) }}% of {{ \App\Support\PokemonData::REGIONS[$gen - 1] ?? 'the last region' }}</span>@endunless</p>
                <ul class="vx-dex-grid">
                    @foreach ($list as $e)
                        <li @class(['vx-dex-cell', 'is-caught' => $e['count'] > 0, 'is-gold' => $e['count'] >= 10, 'is-'.$e['rarity']]) title="{{ $tip($e) }}">
                            <span class="vx-dex-no">#{{ str_pad((string) $e['number'], 3, '0', STR_PAD_LEFT) }}</span>
                            <img src="/img/pokemon3d/{{ $e['shiny'] ? 'shiny/' : '' }}{{ $e['species'] }}.gif" alt="" loading="lazy">
                            <span class="vx-dex-name">{{ $e['count'] > 0 ? \App\Support\Pokedex::name($e['species']) : '???' }}</span>
                            @if ($e['count'] > 0)<span class="vx-dex-n">×{{ $e['count'] }}@if ($e['shiny']) <b>✨</b>@endif</span>@endif
                            @if ($e['rarity'] !== 'common')<span class="vx-dex-rarity" title="{{ ucfirst($e['rarity']) }}" aria-label="{{ ucfirst($e['rarity']) }}">{{ $stars[$e['rarity']] }}</span>@endif
                            @if ($interactive && $e['count'] >= 2)<button type="button" class="vx-dex-release" wire:click="release('{{ $e['species'] }}')" wire:confirm="Release one {{ \App\Support\Pokedex::name($e['species']) }}? You keep the others.">Release one</button>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        {{-- Earned by working (evolutions the Pokédex skips, packing birds, eggs): never rolled from a report. --}}
        <div class="vx-dex-region">
            <p class="vx-dex-region-h"><b>Earned</b> <span>{{ $dex['earned']->where('count', '>', 0)->count() }} of {{ $dex['earned']->count() }} · evolutions, packing, eggs and work</span></p>
            <ul class="vx-dex-grid">
                @foreach ($dex['earned'] as $e)
                    <li @class(['vx-dex-cell', 'is-caught' => $e['count'] > 0, 'is-gold' => $e['count'] >= 10, 'is-'.$e['rarity']]) title="{{ $tip($e) }}">
                        <span class="vx-dex-no">#{{ str_pad((string) $e['number'], 3, '0', STR_PAD_LEFT) }}</span>
                        <img src="/img/pokemon3d/{{ $e['shiny'] ? 'shiny/' : '' }}{{ $e['species'] }}.gif" alt="" loading="lazy">
                        <span class="vx-dex-name">{{ $e['count'] > 0 ? \App\Support\Pokedex::name($e['species']) : '???' }}</span>
                        @if ($e['count'] > 0)<span class="vx-dex-n">×{{ $e['count'] }}@if ($e['shiny']) <b>✨</b>@endif</span>@endif
                        @if ($e['rarity'] !== 'common')<span class="vx-dex-rarity" aria-label="{{ ucfirst($e['rarity']) }}">{{ $stars[$e['rarity']] }}</span>@endif
                        @if ($interactive && $e['count'] >= 2)<button type="button" class="vx-dex-release" wire:click="release('{{ $e['species'] }}')" wire:confirm="Release one {{ \App\Support\Pokedex::name($e['species']) }}? You keep the others.">Release one</button>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    </details>
</section>
@once
<style>
.vx-dex { display: grid; gap: .8rem; padding: 1.1rem 1.2rem; border: 1px solid rgb(229 231 235); border-radius: 1rem; background: #fff; }
.dark .vx-dex { border-color: rgb(55 65 81); background: rgb(17 24 39); }
.vx-dex-head { display: flex; align-items: center; gap: .8rem; }
.vx-dex-ball { width: 2.4rem; height: 2.4rem; flex: none; background: url('/img/pokeball.svg') center / contain no-repeat; }
.vx-dex-head h2 { font-size: 1.05rem; font-weight: 800; }
.vx-dex-head p { font-size: .82rem; color: rgb(107 114 128); }
.vx-dex-latest { margin-left: auto; display: flex; align-items: center; gap: .45rem; padding: .3rem .7rem .3rem .3rem; border-radius: .8rem; background: #f5f3ff; font-size: .72rem; color: rgb(107 114 128); line-height: 1.15; }
.dark .vx-dex-latest { background: rgb(76 29 149 / .3); }
.vx-dex-latest img { width: 2.4rem; height: 2.4rem; object-fit: contain; image-rendering: pixelated; }
.vx-dex-latest b { color: #6d28d9; font-size: .82rem; }
.dark .vx-dex-latest b { color: #c4b5fd; }
.vx-dex-meter { height: .45rem; border-radius: 999px; background: rgb(237 233 254); overflow: hidden; }
.dark .vx-dex-meter { background: rgb(46 16 101 / .6); }
.vx-dex-meter i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #ef4444, #f97316, #7c3aed); }
.vx-dex-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(5.6rem, 1fr)); gap: .5rem; }
.vx-dex-cell { position: relative; display: grid; justify-items: center; gap: .1rem; padding: .45rem .3rem .5rem; border-radius: .8rem; border: 1px solid rgb(229 231 235); background: rgb(249 250 251); text-align: center; }
.dark .vx-dex-cell { border-color: rgb(55 65 81); background: rgb(31 41 55 / .6); }
.vx-dex-cell img { width: 3.2rem; height: 3.2rem; object-fit: contain; image-rendering: pixelated; filter: brightness(0) opacity(.18); }
.dark .vx-dex-cell img { filter: brightness(0) invert(1) opacity(.14); }
.vx-dex-cell.is-caught { background: #fff; border-color: rgb(196 181 253); }
.dark .vx-dex-cell.is-caught { background: rgb(46 16 101 / .35); border-color: rgb(124 58 237 / .6); }
.vx-dex-cell.is-caught img { filter: none; }
.vx-dex-cell.is-caught.is-rare { box-shadow: 0 0 0 1px #f5d0fe, 0 6px 16px -8px rgb(192 38 211 / .6); }
.vx-dex-cell.is-caught.is-legendary { box-shadow: 0 0 0 1.5px #fbbf24, 0 6px 18px -6px rgb(245 158 11 / .65); }
.vx-dex-cell.is-legendary .vx-dex-rarity { color: #d97706; }
.vx-dex-no { position: absolute; top: .3rem; left: .45rem; font-size: .6rem; font-weight: 700; color: rgb(156 163 175); }
.vx-dex-name { font-size: .74rem; font-weight: 700; }
.vx-dex-cell:not(.is-caught) .vx-dex-name { color: rgb(156 163 175); }
.vx-dex-n { font-size: .68rem; color: rgb(107 114 128); }
.vx-dex-n b { font-weight: 400; }
.vx-dex-rarity { position: absolute; top: .25rem; right: .4rem; font-size: .62rem; line-height: 1; color: #a855f7; letter-spacing: -.05em; }
.vx-dex-cell.is-rare .vx-dex-rarity { color: #c026d3; }
.vx-dex-recent { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .2rem; scrollbar-width: thin; }
.vx-dex-chip { display: flex; flex-direction: column; align-items: center; gap: .1rem; flex: none; width: 4.8rem; padding: .35rem .25rem .4rem; border-radius: .75rem; background: #f5f3ff; font-size: .68rem; }
.dark .vx-dex-chip { background: rgb(76 29 149 / .3); }
.vx-dex-chip img { width: 2.8rem; height: 2.8rem; object-fit: contain; image-rendering: pixelated; }
.vx-dex-chip b { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.vx-dex-chip.is-shiny { box-shadow: inset 0 0 0 1.5px #fbbf24; }
.vx-dex-chip.is-legendary { background: linear-gradient(160deg, #fef3c7, #f5f3ff); }
.dark .vx-dex-chip.is-legendary { background: linear-gradient(160deg, rgb(120 53 15 / .45), rgb(76 29 149 / .3)); }
.vx-dex-empty { font-size: .85rem; color: rgb(107 114 128); }
.vx-dex-all summary { cursor: pointer; display: flex; align-items: center; gap: .6rem; font-size: .85rem; font-weight: 700; color: #6d28d9; list-style: none; }
.dark .vx-dex-all summary { color: #c4b5fd; }
.vx-dex-all summary::-webkit-details-marker { display: none; }
.vx-dex-all summary::before { content: '▸'; transition: transform .15s; }
.vx-dex-all[open] summary::before { transform: rotate(90deg); }
.vx-dex-all summary span { font-weight: 600; color: rgb(107 114 128); }
.vx-dex-all .vx-dex-grid { margin-top: .7rem; }
.vx-dex-event { display: grid; gap: .5rem; padding: .7rem .8rem; border-radius: .9rem; border: 1px dashed rgb(196 181 253); background: rgb(250 245 255); }
.dark .vx-dex-event { border-color: rgb(124 58 237 / .5); background: rgb(46 16 101 / .25); }
.vx-dex-event.is-running { border-style: solid; }
.vx-dex-event[data-vx-dex-event="halloween"].is-running { border-color: #fb923c; background: linear-gradient(135deg, #fff7ed, #f5f3ff); }
.dark .vx-dex-event[data-vx-dex-event="halloween"].is-running { border-color: rgb(251 146 60 / .6); background: linear-gradient(135deg, rgb(124 45 18 / .35), rgb(46 16 101 / .35)); }
.vx-dex-event-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .35rem; font-size: .85rem; }
.vx-dex-event-head span:last-child { font-size: .78rem; color: rgb(107 114 128); }
.vx-dex-grid--event { grid-template-columns: repeat(auto-fill, minmax(4.8rem, 1fr)); }
.vx-dex-cell.is-caught.is-gold { border-color: #f59e0b; background: linear-gradient(160deg, #fffbeb, #fff 60%); box-shadow: 0 0 0 1.5px #fbbf24, 0 6px 16px -8px rgb(217 119 6 / .55); }
.dark .vx-dex-cell.is-caught.is-gold { background: linear-gradient(160deg, rgb(120 53 15 / .45), rgb(17 24 39) 60%); }
.vx-dex-cell.is-gold .vx-dex-n { color: #b45309; font-weight: 800; }
.vx-dex-region { display: grid; gap: .5rem; margin-top: .9rem; }
.vx-dex-region-h { display: flex; flex-wrap: wrap; align-items: baseline; gap: .4rem .7rem; font-size: .88rem; }
.vx-dex-region-h > span { font-size: .76rem; color: rgb(107 114 128); }
.vx-dex-lock { font-weight: 600; }
.vx-dex-region.is-locked .vx-dex-grid { opacity: .55; }
.vx-dex-release { margin-top: .2rem; font-size: .62rem; font-weight: 700; color: #6d28d9; padding: .1rem .4rem; border-radius: .4rem; }
.vx-dex-release:hover { background: #ede9fe; }
.vx-dex-badges { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .55rem; }
.vx-dex-badges-wrap summary { cursor: pointer; display: flex; flex-wrap: wrap; align-items: baseline; gap: .3rem .6rem; font-size: .85rem; font-weight: 700; color: #6d28d9; list-style: none; }
.dark .vx-dex-badges-wrap summary { color: #c4b5fd; }
.vx-dex-badges-wrap summary::-webkit-details-marker { display: none; }
.vx-dex-badges-wrap summary::before { content: '▸'; transition: transform .15s; }
.vx-dex-badges-wrap[open] summary::before { transform: rotate(90deg); }
.vx-dex-badges-wrap summary span { font-weight: 600; font-size: .78rem; color: rgb(107 114 128); }
.vx-dex-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem .15rem .2rem; border-radius: 999px; border: 1px solid rgb(229 231 235); background: rgb(249 250 251); font-size: .7rem; color: rgb(107 114 128); }
.dark .vx-dex-badge { border-color: rgb(55 65 81); background: rgb(31 41 55 / .6); }
.vx-dex-badge img { width: 1.4rem; height: 1.4rem; object-fit: contain; image-rendering: pixelated; filter: grayscale(1) opacity(.55); }
.vx-dex-badge b { font-weight: 700; }
.vx-dex-badge.is-earned { border-color: #fbbf24; background: linear-gradient(135deg, #fef3c7, #fff); color: #92400e; }
.dark .vx-dex-badge.is-earned { background: linear-gradient(135deg, rgb(120 53 15 / .5), rgb(17 24 39)); color: #fcd34d; }
.vx-dex-badge.is-earned img { filter: none; }
@media (max-width: 640px) { .vx-dex-latest { display: none; } .vx-dex-grid { grid-template-columns: repeat(auto-fill, minmax(4.8rem, 1fr)); } }
</style>
@endonce

@props(['streamer', 'title' => 'Your Pokédex', 'open' => false])
{{-- Every approved show report catches a Pokémon (App\Support\Pokedex); uncaught ones show as silhouettes. --}}
@php $dex = \App\Support\Pokedex::forStreamer($streamer); @endphp
<section class="vx-dex" aria-labelledby="vx-dex-title-{{ $streamer?->id ?? 0 }}">
    <div class="vx-dex-head">
        <span class="vx-dex-ball" aria-hidden="true"></span>
        <div class="min-w-0">
            <h2 id="vx-dex-title-{{ $streamer?->id ?? 0 }}">{{ $title }}</h2>
            <p>{{ $dex['caught'] }} of {{ $dex['total'] }} caught · {{ $dex['catches'] }} {{ \Illuminate\Support\Str::plural('catch', $dex['catches']) }}. Every approved show report catches one.</p>
        </div>
        @if ($dex['latest'])
            <div class="vx-dex-latest" title="Caught {{ $dex['latest']->caught_at?->diffForHumans() }}">
                <img src="/img/pokemon/{{ $dex['latest']->shiny ? 'shiny/' : '' }}{{ $dex['latest']->species }}.gif" alt="">
                <span>Latest<br><b>{{ $dex['latest']->shiny ? '✨ ' : '' }}{{ \App\Support\Pokedex::name($dex['latest']->species) }}</b></span>
            </div>
        @endif
    </div>
    <div class="vx-dex-meter" role="img" aria-label="{{ $dex['caught'] }} of {{ $dex['total'] }} caught"><i style="width: {{ round($dex['caught'] * 100 / max(1, $dex['total'])) }}%"></i></div>
    @if ($dex['recent']->isNotEmpty())
        <div class="vx-dex-recent" aria-label="Recent catches">
            @foreach ($dex['recent'] as $c)
                <span @class(['vx-dex-chip', 'is-shiny' => $c->shiny, 'is-'.\App\Support\Pokedex::rarity($c->species)]) title="{{ ($c->shiny ? 'Shiny ' : '').\App\Support\Pokedex::name($c->species) }} · {{ $c->caught_at?->format('M j') }}">
                    <img src="/img/pokemon/{{ $c->shiny ? 'shiny/' : '' }}{{ $c->species }}.gif" alt="" loading="lazy"><b>{{ \App\Support\Pokedex::name($c->species) }}</b>
                </span>
            @endforeach
        </div>
    @else
        <p class="vx-dex-empty">No catches yet. File a show report and see what you catch!</p>
    @endif
    @foreach ($dex['events'] as $ev)
        {{-- Event-only Pokémon (App\Support\Season::EVENTS): only catchable while their event runs. --}}
        <div @class(['vx-dex-event', 'is-running' => $ev['running']]) data-vx-dex-event="{{ $ev['key'] }}">
            <p class="vx-dex-event-head"><span aria-hidden="true">{{ $ev['emoji'] }}</span> <b>{{ $ev['label'] }} event</b>
                <span>{{ $ev['entries']->where('count', '>', 0)->count() }} of {{ $ev['entries']->count() }} caught{{ $ev['running'] ? ' · only catchable now' : '' }}</span></p>
            <ul class="vx-dex-grid vx-dex-grid--event">
                @foreach ($ev['entries'] as $e)
                    <li @class(['vx-dex-cell', 'is-caught' => $e['count'] > 0, 'is-'.$e['rarity']]) title="{{ $e['count'] > 0 ? \App\Support\Pokedex::name($e['species']).' · caught '.$e['count'].'×' : 'Not caught yet' }}">
                        <span class="vx-dex-no">#{{ str_pad((string) $e['number'], 3, '0', STR_PAD_LEFT) }}</span>
                        <img src="/img/pokemon/{{ $e['shiny'] ? 'shiny/' : '' }}{{ $e['species'] }}.gif" alt="" loading="lazy">
                        <span class="vx-dex-name">{{ $e['count'] > 0 ? \App\Support\Pokedex::name($e['species']) : '???' }}</span>
                        @if ($e['count'] > 0)<span class="vx-dex-n">×{{ $e['count'] }}@if ($e['shiny']) <b>✨</b>@endif</span>@endif
                        @if ($e['rarity'] !== 'common')<span class="vx-dex-rarity" aria-label="{{ ucfirst($e['rarity']) }}">{{ ['uncommon' => '★', 'rare' => '★★', 'legendary' => '★★★'][$e['rarity']] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
    <details class="vx-dex-all" @if ($open) open @endif>
        <summary>See all {{ $dex['total'] }} Pokémon <span>{{ $dex['caught'] }} caught</span></summary>
    <ul class="vx-dex-grid">
        @foreach ($dex['entries'] as $i => $e)
            <li @class(['vx-dex-cell', 'is-caught' => $e['count'] > 0, 'is-'.$e['rarity']]) title="{{ $e['count'] > 0 ? \App\Support\Pokedex::name($e['species']).' · caught '.$e['count'].'×'.($e['shiny'] ? ' · '.$e['shiny'].' shiny' : '') : 'Not caught yet' }}">
                <span class="vx-dex-no">#{{ str_pad((string) $e['number'], 3, '0', STR_PAD_LEFT) }}</span>
                <img src="/img/pokemon/{{ $e['shiny'] ? 'shiny/' : '' }}{{ $e['species'] }}.gif" alt="" loading="lazy">
                <span class="vx-dex-name">{{ $e['count'] > 0 ? \App\Support\Pokedex::name($e['species']) : '???' }}</span>
                @if ($e['count'] > 0)<span class="vx-dex-n">×{{ $e['count'] }}@if ($e['shiny']) <b>✨</b>@endif</span>@endif
                @if ($e['rarity'] !== 'common')<span class="vx-dex-rarity" title="{{ ucfirst($e['rarity']) }}" aria-label="{{ ucfirst($e['rarity']) }}">{{ ['uncommon' => '★', 'rare' => '★★', 'legendary' => '★★★'][$e['rarity']] }}</span>@endif
            </li>
        @endforeach
    </ul>
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
@media (max-width: 640px) { .vx-dex-latest { display: none; } .vx-dex-grid { grid-template-columns: repeat(auto-fill, minmax(4.8rem, 1fr)); } }
</style>
@endonce

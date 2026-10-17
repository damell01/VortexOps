<x-filament-panels::page>
@php
    $trainer = $this->trainer();
    $trades = $this->trades;
    $eggs = $this->eggs;
@endphp
<div class="vxp">
    <div>

        {{-- Mystery Eggs: a perfect week, or every few releases; each hatches after a few more catches. --}}
        <section class="vxp-card" aria-labelledby="vxp-eggs-h">
            <h2 id="vxp-eggs-h">Mystery Eggs</h2>
            @forelse ($eggs as $egg)
                <div class="vxp-egg-row">
                    @if ($egg->hatched_at && $egg->hatched)
                        <img src="/img/pokemon/{{ $egg->hatched->shiny ? 'shiny/' : '' }}{{ $egg->hatched->species }}.gif" alt="" class="vxp-egg-mon">
                        <span><b>Hatched {{ \App\Support\Pokedex::name($egg->hatched->species) }}</b><small>{{ $egg->hatched_at->diffForHumans() }}</small></span>
                    @else
                        <span class="vxp-egg" aria-hidden="true"></span>
                        <span><b>{{ $egg->reason === 'perfect_week' ? 'Perfect week egg' : 'Release egg' }}</b><small>Hatches in {{ max(0, $egg->steps_needed - $egg->steps) }} more {{ \Illuminate\Support\Str::plural('catch', max(0, $egg->steps_needed - $egg->steps)) }}</small></span>
                        <span class="vxp-steps" aria-label="{{ $egg->steps }} of {{ $egg->steps_needed }}">@for ($i = 0; $i < $egg->steps_needed; $i++)<i @class(['on' => $i < $egg->steps])></i>@endfor</span>
                    @endif
                </div>
            @empty
                <p class="vxp-sub">No eggs yet. A perfect week of on-time reports, or every {{ \App\Support\PokemonGames::RELEASES_PER_EGG }} duplicates you release, earns one.</p>
            @endforelse
        </section>
    </div>

    <x-vx.pokedex :trainer="$trainer" :title="'Your Pokédex'" :open="true" :interactive="true" />

    {{-- Trading duplicates: both people must accept, and neither ever loses their last of a kind. --}}
    <section class="vxp-card" aria-labelledby="vxp-trade-h">
        <div class="vxp-head">
            <h2 id="vxp-trade-h">Trade duplicates</h2>
            <span class="vxp-sub">Offer a spare for someone else’s spare. Nobody can trade away their last one.</span>
        </div>
        @foreach ($trades['incoming'] as $t)
            <div class="vxp-trade is-in">
                <img src="/img/pokemon/{{ $t->offered?->species }}.gif" alt=""><span class="vxp-arrow" aria-hidden="true">⇄</span><img src="/img/pokemon/{{ $t->requested?->species }}.gif" alt="">
                <span class="min-w-0 flex-1"><b>{{ $t->proposer?->name }}</b> offers {{ \App\Support\Pokedex::name($t->offered?->species ?? '') }} for your {{ \App\Support\Pokedex::name($t->requested?->species ?? '') }}</span>
                <button type="button" class="vxp-btn primary" wire:click="answerTrade({{ $t->id }}, true)">Accept</button>
                <button type="button" class="vxp-btn" wire:click="answerTrade({{ $t->id }}, false)">Decline</button>
            </div>
        @endforeach
        @foreach ($trades['outgoing'] as $t)
            <div class="vxp-trade">
                <img src="/img/pokemon/{{ $t->offered?->species }}.gif" alt=""><span class="vxp-arrow" aria-hidden="true">⇄</span><img src="/img/pokemon/{{ $t->requested?->species }}.gif" alt="">
                <span class="min-w-0 flex-1">Waiting on <b>{{ $t->recipient?->name }}</b>: your {{ \App\Support\Pokedex::name($t->offered?->species ?? '') }} for their {{ \App\Support\Pokedex::name($t->requested?->species ?? '') }}</span>
                <button type="button" class="vxp-btn" wire:click="answerTrade({{ $t->id }}, false)">Cancel</button>
            </div>
        @endforeach
        @if ($this->myDuplicates->isNotEmpty())
            <form wire:submit="proposeTrade" class="vxp-trade-form">
                <label>Your spare
                    <select wire:model="tradeMine"><option value="">Choose…</option>@foreach ($this->myDuplicates as $s => $n)<option value="{{ $s }}">{{ \App\Support\Pokedex::name($s) }} (×{{ $n }})</option>@endforeach</select>
                </label>
                <label>Trade with
                    <select wire:model.live="tradeWith"><option value="">Choose…</option>@foreach ($this->tradePartners as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </label>
                <label>Their spare
                    <select wire:model="tradeTheirs" @disabled($this->tradeWith === '')><option value="">Choose…</option>@foreach ($this->theirDuplicates as $s => $n)<option value="{{ $s }}">{{ \App\Support\Pokedex::name($s) }} (×{{ $n }})</option>@endforeach</select>
                </label>
                <button type="submit" class="vxp-btn primary">Offer trade</button>
            </form>
        @else
            <p class="vxp-sub">Catch two of something and you can trade one.</p>
        @endif
        @if ($trades['history']->isNotEmpty())
            <details class="vxp-history"><summary>Trade history</summary>
                <ul>@foreach ($trades['history'] as $t)<li>{{ $t->responded_at?->format('M j') }} · {{ $t->proposer?->name }}’s {{ \App\Support\Pokedex::name($t->offered?->species ?? '') }} ⇄ {{ $t->recipient?->name }}’s {{ \App\Support\Pokedex::name($t->requested?->species ?? '') }} · <span>{{ ucfirst($t->status) }}</span></li>@endforeach</ul>
            </details>
        @endif
    </section>

    {{-- An optional little chime when you catch one (resources/js/delight.js); off unless you turn it on. --}}
    <section class="vxp-card vxp-sound" x-data="{
            on: (() => { try { return localStorage.getItem('vx-dex-sound') === '1' } catch (e) { return false } })(),
            save() { try { localStorage.setItem('vx-dex-sound', this.on ? '1' : '0') } catch (e) {} if (this.on && window.vxCatchChime) window.vxCatchChime() },
        }">
        <label class="vxp-switch"><input type="checkbox" x-model="on" @change="save()"> <span>Play a chime when I catch a Pokémon (this device)</span></label>
    </section>
</div>
<style>
.vxp { display: grid; gap: 1rem; }
.vxp-row { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 1rem; }
@media (max-width: 900px) { .vxp-row { grid-template-columns: minmax(0, 1fr); } }
.vxp-card { padding: 1rem 1.15rem; border: 1px solid rgb(229 231 235); border-radius: 1rem; background: #fff; display: grid; gap: .6rem; align-content: start; }
.dark .vxp-card { border-color: rgb(55 65 81); background: rgb(17 24 39); }
.vxp-card h2 { font-size: 1rem; font-weight: 800; }
.vxp-card p { font-size: .84rem; color: rgb(75 85 99); }
.dark .vxp-card p { color: rgb(156 163 175); }
.vxp-sub { font-size: .78rem !important; color: rgb(107 114 128) !important; }
.vxp-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem 1rem; }
.vxp-bar { height: .55rem; border-radius: 999px; background: rgb(237 233 254); overflow: hidden; }
.dark .vxp-bar { background: rgb(46 16 101 / .6); }
.vxp-bar i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #7c3aed, #f59e0b); transition: width .6s ease; }
.vxp-btn { min-height: 2.25rem; padding: 0 .9rem; border-radius: .6rem; border: 1px solid rgb(209 213 219); background: #fff; font-size: .8rem; font-weight: 700; color: #4c1d95; }
.dark .vxp-btn { background: rgb(31 41 55); border-color: rgb(75 85 99); color: #ddd6fe; }
.vxp-btn.primary { background: #7c3aed; border-color: #7c3aed; color: #fff; }
.vxp-egg-row { display: flex; align-items: center; gap: .7rem; font-size: .82rem; }
.vxp-egg-row b { display: block; }
.vxp-egg-row small { color: rgb(107 114 128); }
.vxp-egg { flex: none; width: 2rem; height: 2.5rem; border-radius: 50% 50% 46% 46% / 60% 60% 40% 40%; background: radial-gradient(circle at 30% 30%, #fff 0 18%, transparent 19%), radial-gradient(circle at 65% 55%, #a7f3d0 0 14%, transparent 15%), radial-gradient(circle at 35% 72%, #a7f3d0 0 11%, transparent 12%), #f0fdf4; border: 2px solid #86efac; animation: vxp-wobble 3.2s ease-in-out infinite; transform-origin: 50% 90%; }
@keyframes vxp-wobble { 0%, 80%, 100% { transform: rotate(0); } 85% { transform: rotate(-8deg); } 90% { transform: rotate(7deg); } 95% { transform: rotate(-4deg); } }
.vxp-egg-mon { width: 2.5rem; height: 2.5rem; object-fit: contain; image-rendering: pixelated; }
.vxp-steps { margin-left: auto; display: flex; gap: 4px; }
.vxp-steps i { width: 10px; height: 10px; border-radius: 999px; background: rgb(229 231 235); }
.vxp-steps i.on { background: #22c55e; }
.vxp-trade { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: .55rem .7rem; border-radius: .8rem; background: rgb(249 250 251); font-size: .82rem; }
.dark .vxp-trade { background: rgb(31 41 55 / .6); }
.vxp-trade.is-in { background: #f5f3ff; box-shadow: inset 0 0 0 1px #ddd6fe; }
.dark .vxp-trade.is-in { background: rgb(46 16 101 / .35); box-shadow: inset 0 0 0 1px rgb(124 58 237 / .5); }
.vxp-trade img { width: 2.4rem; height: 2.4rem; object-fit: contain; image-rendering: pixelated; }
.vxp-arrow { color: #7c3aed; font-weight: 800; }
.vxp-trade-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) auto; gap: .6rem; align-items: end; }
.vxp-trade-form label { display: grid; gap: .25rem; font-size: .74rem; font-weight: 700; color: rgb(75 85 99); min-width: 0; }
.vxp-trade-form select { min-height: 2.4rem; border-radius: .6rem; border: 1px solid rgb(209 213 219); font-size: .84rem; background-color: transparent; }
@media (max-width: 760px) { .vxp-trade-form { grid-template-columns: minmax(0, 1fr); } }
.vxp-history summary { cursor: pointer; font-size: .8rem; font-weight: 700; color: #6d28d9; }
.vxp-history ul { margin-top: .4rem; display: grid; gap: .25rem; font-size: .78rem; color: rgb(75 85 99); }
.vxp-history li span { font-weight: 700; }
.vxp-sound { padding: .7rem 1rem; }
.vxp-switch { display: flex; align-items: center; gap: .6rem; font-size: .84rem; cursor: pointer; }
@media (prefers-reduced-motion: reduce) { .vxp-egg { animation: none; } .vxp-bar i { transition: none; } }
</style>
</x-filament-panels::page>

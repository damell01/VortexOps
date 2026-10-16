@php($season = \App\Support\Season::details())
@php($visit = \App\Support\VisitorCast::for(auth()->user(), \App\Support\Season::visitors()))
@php($visitors = $visit['cast'])
@if (\App\Support\Celebrations::level(auth()->user()) !== 'off')
    {{-- Visitors (App\Support\Season::visitors): one hangs upside down from under the top bar, another peeks
         up from the corner, taking turns; each peek is someone new (resources/js/delight.js). A click sends them away for this session. --}}
    <div class="vx-season" data-season="{{ $season['key'] ?? 'everyday' }}" aria-hidden="true"
         x-data="{ gone: (() => { try { return sessionStorage.getItem('vx-season-gone') === '1' } catch (e) { return false } })() }"
         x-show="! gone">
        @foreach (['top' => 'from-top', 'bottom' => 'from-bottom'] as $side => $class)
            <button type="button" class="vx-season-peek {{ $class }}" tabindex="-1" data-vx-visitors='@json($visitors[$side])'
                    title="{{ $season['title'] ?? 'Hi!' }} (click to send them away)"
                    @click="gone = true; try { sessionStorage.setItem('vx-season-gone', '1') } catch (e) {}">
                <img src="/img/pokemon/{{ $visitors[$side][0] }}.gif" alt="">
                @if($side === 'top' && $visit['rare'])<span style="position:absolute;right:-8px;bottom:0;font-size:15px" aria-hidden="true">✨</span>@endif
            </button>
        @endforeach
        @if ($season && in_array($season['effect'], ['snow', 'hearts', 'clovers'], true))
            {{-- Something light drifting down over the top of the page: snow, hearts or clovers. --}}
            <div class="vx-fall vx-fall--{{ $season['effect'] }}">
                @for ($i = 0; $i < 18; $i++)
                    <i style="--x: {{ ($i * 41 + 7) % 100 }}%; --s: {{ $season['effect'] === 'snow' ? 3 + $i % 3 : 9 + $i % 3 * 3 }}px; --t: {{ 7 + ($i % 5) * 1.4 }}s; --d: -{{ ($i * 1.3) % 9 }}s; --w: {{ ($i % 2 ? 1 : -1) * (10 + $i % 4 * 6) }}px"></i>
                @endfor
            </div>
        @elseif ($season && $season['effect'] === 'fireworks')
            @php($fwColours = \App\Support\Season::FIREWORK_COLOURS[$season['key']] ?? \App\Support\Season::FIREWORK_COLOURS['default'])
            {{-- All day: bursts keep popping across the top of every page. --}}
            <div class="vx-fireworks">
                @for ($n = 0; $n < 16; $n++)
                    <span style="--x: {{ 3 + ($n * 31) % 94 }}%; --y: {{ 36 + ($n * 47) % 140 }}px; --z: {{ [1.2, 1.7, 1, 1.9, 1.4, 1.1, 1.8, 1.5][$n % 8] }}; --d: {{ round(($n * 0.29) % 4.5, 2) }}s; --c: {{ $fwColours[$n % 8] }}">@for ($r = 0; $r < 20; $r++)<i style="--r: {{ $r * 18 + ($r % 3) * 5 }}deg; --k: {{ [1, .62, .84, .7][$r % 4] }}"></i>@endfor</span>
                @endfor
            </div>
            {{-- First page of the day: a full-screen show, once. --}}
            <div class="vx-fireworks-finale"
                 x-data="{ on: (() => { try { const k = 'vx-fireworks-finale', d = new Date().toDateString(); if (localStorage.getItem(k) === d) return false; localStorage.setItem(k, d); return true } catch (e) { return true } })() }"
                 x-show="on" x-init="on && setTimeout(() => on = false, 6500)">
                @for ($n = 0; $n < 18; $n++)
                    <span style="--x: {{ 5 + ($n * 37) % 90 }}%; --y: {{ 12 + ($n * 23) % 70 }}vh; --z: {{ 1.8 + ($n % 4) * 0.45 }}; --d: {{ round($n * 0.24, 2) }}s; --c: {{ $fwColours[$n % 8] }}">@for ($r = 0; $r < 24; $r++)<i style="--r: {{ $r * 15 + ($r % 3) * 4 }}deg; --k: {{ [1, .62, .84, .7][$r % 4] }}"></i>@endfor</span>
                @endfor
            </div>
        @endif
    </div>
@endif

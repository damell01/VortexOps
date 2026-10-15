@php($season = \App\Support\Season::details())
@if ($season)
    {{-- A seasonal visitor (App\Support\Season) peeks out now and then; a click sends it away for this session. --}}
    <div class="vx-season" data-season="{{ $season['key'] }}" data-icon="{{ $season['icon'] }}" aria-hidden="true"
         x-data="{ gone: (() => { try { return sessionStorage.getItem('vx-season-gone') === @js($season['key']) } catch (e) { return false } })() }"
         x-show="! gone">
        <button type="button" @class(['vx-season-peek', 'from-top' => $season['from'] === 'top', 'from-bottom' => $season['from'] === 'bottom']) tabindex="-1"
                title="{{ $season['title'] }} (click to send {{ ucfirst($season['mon']) }} away)"
                @click="gone = true; try { sessionStorage.setItem('vx-season-gone', @js($season['key'])) } catch (e) {}">
            <img src="/img/pokemon/{{ $season['mon'] }}.gif" alt="">
        </button>
        @if ($season['effect'] === 'snow')
            <div class="vx-snow">
                @for ($i = 0; $i < 18; $i++)
                    <i style="--x: {{ ($i * 41 + 7) % 100 }}%; --s: {{ 3 + $i % 3 }}px; --t: {{ 7 + ($i % 5) * 1.4 }}s; --d: -{{ ($i * 1.3) % 9 }}s; --w: {{ ($i % 2 ? 1 : -1) * (10 + $i % 4 * 6) }}px"></i>
                @endfor
            </div>
        @elseif ($season['effect'] === 'fireworks')
            <div class="vx-fireworks">
                @foreach ([[18, 0, '#f59e0b'], [52, 2.6, '#a78bfa'], [84, 5.1, '#f472b6']] as [$x, $d, $c])
                    <span style="--x: {{ $x }}%; --d: {{ $d }}s; --c: {{ $c }}">@for ($r = 0; $r < 16; $r++)<i style="--r: {{ $r * 22.5 + ($r % 3) * 6 }}deg; --k: {{ [1, .62, .84, .7][$r % 4] }}"></i>@endfor</span>
                @endforeach
            </div>
        @endif
    </div>
@endif

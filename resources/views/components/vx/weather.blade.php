@props(['kind' => null])
{{-- A little weather over a report header: rain when things are going badly, sun when they went well. Purely decorative. --}}
@if ($kind === 'rain')
    <div class="vx-weather vx-weather--rain" aria-hidden="true">
        @for ($i = 0; $i < 22; $i++)
            <i style="--x: {{ ($i * 37 + 11) % 100 }}%; --y: {{ ($i * 53) % 160 }}px; --t: {{ 1.1 + ($i % 5) * 0.18 }}s; --d: -{{ ($i * 0.29) % 2.2 }}s"></i>
        @endfor
    </div>
@elseif ($kind === 'sun')
    <div class="vx-weather vx-weather--sun" aria-hidden="true"></div>
@endif

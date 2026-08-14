@props(['stats' => []])

{{--
    Headline tiles above a list page. Each entry is:
      label, value, sub (optional caption), icon (heroicon name), tone
    Tones map to the .vx-tone-* palette: blue, green, amber, purple, red, orange.
    Captions are hidden on phones by the stylesheet — the numbers are labelled.
--}}
@if (! empty($stats))
    <div {{ $attributes->merge(['class' => 'vx-kpis']) }} data-count="{{ count($stats) }}">
        @foreach ($stats as $stat)
            <div class="vx-stat-tile is-lg">
                <span class="vx-stat-icon vx-tone-{{ $stat['tone'] ?? 'blue' }}">
                    <x-filament::icon :icon="$stat['icon'] ?? 'heroicon-o-chart-bar'" class="vx-stat-glyph" />
                </span>
                <span class="vx-stat-body">
                    <span class="vx-stat-label">{{ $stat['label'] }}</span>
                    <span class="vx-stat-value">{{ $stat['value'] }}</span>
                    @if (! empty($stat['sub']))
                        <span class="vx-stat-sub">{{ $stat['sub'] }}</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
@endif

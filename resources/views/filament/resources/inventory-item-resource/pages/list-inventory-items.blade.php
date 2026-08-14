<x-filament-panels::page>
    {{-- Headline counts. These read the resource's own scoped query, so a
         streamer's tiles agree with the rows listed underneath. --}}
    <div class="vx-inv-stats">
        @foreach ($this->getStats() as $stat)
            <div class="vx-stat-tile is-lg">
                <span class="vx-stat-icon vx-tone-{{ $stat['tone'] }}">
                    <x-filament::icon :icon="$stat['icon']" class="vx-stat-glyph" />
                </span>
                <span class="vx-stat-body">
                    <span class="vx-stat-label">{{ $stat['label'] }}</span>
                    <span class="vx-stat-value">{{ $stat['value'] }}</span>
                    <span class="vx-stat-sub">{{ $stat['sub'] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- The table renders directly rather than behind a skeleton swap: the
         two wrappers were flex siblings, so the hidden one still spent a
         gap between the tiles and the table. Filament's own deferred
         loading state covers the wait. --}}
    {{ $this->table }}
</x-filament-panels::page>

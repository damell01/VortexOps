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

    <div wire:loading.delay>
        <x-skeleton-loader type="table" :columns="5" :rows="3" />
    </div>

    <div wire:loading.delay.remove>
        {{ $this->table }}
    </div>
</x-filament-panels::page>

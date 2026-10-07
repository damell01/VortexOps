@php
    $data = $this->getBuckets();
@endphp

<x-filament-panels::page>
    <div class="vx-age vx-report">
        <section class="vx-age-summary">
            <div><span class="vx-age-eyebrow">Inventory health</span><h2>Age of stock on hand</h2><p>Prioritize older inventory by the money tied up in each age group. Age starts at the most recent receipt; it is not the age of individual lots.</p></div>
            <div class="vx-age-summary-value"><span>Stock value</span><strong>${{ number_format($data['total_value'], 2) }}</strong><span>{{ number_format($data['total_units']) }} units on hand</span></div>
        </section>
        <div class="vx-age-picker" x-data="{ open: false, search: '', selected: $wire.entangle('locationId').live, options: @js($this->getLocationOptions()) }" @keydown.escape.stop="open = false" @click.outside="open = false">
            <span class="vx-age-filter-label" id="age-location-label">Location</span>
            <button type="button" class="vx-age-picker-trigger" aria-labelledby="age-location-label age-location-value" :aria-expanded="open" aria-controls="age-location-options" @click="open = !open; search = ''; if (open) $nextTick(() => $refs.search.focus())">
                <span id="age-location-value" x-text="options[selected] || 'All Locations'"></span><span aria-hidden="true">⌄</span>
            </button>
            <div class="vx-age-picker-panel" x-show="open" x-cloak>
                <input x-ref="search" x-model="search" type="search" placeholder="Search locations…" aria-label="Search locations" autocomplete="off">
                <div id="age-location-options" class="vx-age-picker-options" role="group" aria-label="Locations">
                    <template x-for="[id, name] in Object.entries(options).filter(([id, name]) => name.toLowerCase().includes(search.toLowerCase()))" :key="id">
                        <button type="button" :aria-pressed="String(selected) === id" @click="selected = id; open = false; $nextTick(() => $root.querySelector('.vx-age-picker-trigger').focus())"><span x-text="name"></span><span x-show="String(selected) === id" aria-hidden="true">✓</span></button>
                    </template>
                    <p x-show="!Object.values(options).some(name => name.toLowerCase().includes(search.toLowerCase()))">No matching locations.</p>
                </div>
            </div>
        </div>

        @if (! $data['has_data'])
            <div class="vx-age-blank">
                <p>Nothing in stock at this location.</p>
                <p class="vx-age-blank-note">
                    Age is measured from each product's most recent receipt.
                </p>
            </div>
        @else
            <ul class="vx-age-list">
                @foreach ($data['buckets'] as $bucket)
                    @php $isOpen = $this->openBucket === $bucket['key']; @endphp

                    <li class="vx-age-row @if($isOpen) is-open @endif">
                        {{-- The whole row is the control: a bucket with nothing
                             in it has nothing to open, so it stays inert. --}}
                        <button type="button"
                            class="vx-age-toggle"
                            @if ($bucket['item_count'] === 0) disabled @endif
                            wire:click="toggleBucket('{{ $bucket['key'] }}')"
                            aria-expanded="{{ $isOpen ? 'true' : 'false' }}">

                            <div class="vx-age-head">
                                <span class="vx-age-label">{{ $bucket['label'] }}</span>
                                <span class="vx-age-pill is-{{ $bucket['tone'] }}">{{ $bucket['risk'] }}</span>

                                @if ($bucket['item_count'] > 0)
                                    <span class="vx-age-chevron" aria-hidden="true">
                                        {{ $isOpen ? '−' : '+' }}
                                    </span>
                                @endif
                            </div>

                            <div class="vx-age-figures">
                                <span class="vx-age-value">
                                    ${{ number_format($bucket['value'], 2) }}
                                    <span class="vx-age-pct">({{ $bucket['pct'] }}%)</span>
                                </span>
                                <span class="vx-age-units">
                                    {{ number_format($bucket['units']) }} units
                                    @if ($bucket['item_count'] > 0)
                                        · {{ $bucket['item_count'] }}
                                        {{ \Illuminate\Support\Str::plural('item', $bucket['item_count']) }}
                                    @endif
                                </span>
                            </div>

                            {{-- Share of value, so the bar and the percentage agree. --}}
                            <div class="vx-age-track">
                                <div class="vx-age-fill is-{{ $bucket['tone'] }}" style="width: {{ $bucket['pct'] }}%"></div>
                            </div>
                        </button>

                        @if ($isOpen && $bucket['item_count'] > 0)
                            <div class="vx-age-detail-heading">Items · highest value first</div>
                            <ul class="vx-age-items">
                                @foreach ($bucket['items'] as $item)
                                    <li wire:key="age-{{ $bucket['key'] }}-{{ $item['product_id'] }}-{{ $loop->index }}">
                                        <a class="vx-age-item"
                                           href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('edit', ['record' => $item['product_id']]) }}">
                                            <span class="vx-age-item-main">
                                                <span class="vx-age-item-name">{{ $item['name'] }}</span>
                                                <span class="vx-age-item-meta">
                                                    {{ $item['sku'] ?: 'No SKU' }} · {{ $item['location'] }} ·
                                                    {{ $item['days'] }} {{ \Illuminate\Support\Str::plural('day', $item['days']) }}
                                                </span>
                                            </span>
                                            <span class="vx-age-item-figures">
                                                <span class="vx-age-item-value">${{ number_format($item['value'], 2) }}</span>
                                                <span class="vx-age-item-units">{{ number_format($item['units']) }} units</span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach


                            </ul>
                            <nav class="vx-age-pagination" aria-label="{{ $bucket['label'] }} items">
                                <button type="button" wire:click="previousItems" @disabled($bucket['page'] <= 1) wire:loading.attr="disabled">Previous</button>
                                <span>Page {{ $bucket['page'] }} of {{ $bucket['pages'] }} · {{ $bucket['item_count'] }} items</span>
                                <button type="button" wire:click="nextItems" @disabled($bucket['page'] >= $bucket['pages']) wire:loading.attr="disabled">Next</button>
                            </nav>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="vx-age-total">
                <span>Total on hand</span>
                <span>
                    ${{ number_format($data['total_value'], 2) }}
                    · {{ number_format($data['total_units']) }} units
                </span>
            </div>
        @endif
    </div>
</x-filament-panels::page>


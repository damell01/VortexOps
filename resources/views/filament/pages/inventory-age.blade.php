@php
    $data = $this->getBuckets();
@endphp

<x-filament-panels::page>
    <div class="vx-age">
        <label class="vx-age-filter">
            <span class="vx-age-filter-label">Location</span>
            <select wire:model.live="locationId">
                @foreach ($this->getLocationOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>

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

                                @if ($bucket['hidden_items'] > 0)
                                    <li class="vx-age-more">
                                        + {{ $bucket['hidden_items'] }} more, smaller by value
                                    </li>
                                @endif
                            </ul>
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

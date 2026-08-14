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
                    <li class="vx-age-row">
                        <div class="vx-age-head">
                            <span class="vx-age-label">{{ $bucket['label'] }}</span>
                            <span class="vx-age-pill is-{{ $bucket['tone'] }}">{{ $bucket['risk'] }}</span>
                        </div>

                        <div class="vx-age-figures">
                            <span class="vx-age-value">
                                ${{ number_format($bucket['value'], 2) }}
                                <span class="vx-age-pct">({{ $bucket['pct'] }}%)</span>
                            </span>
                            <span class="vx-age-units">{{ number_format($bucket['units']) }} units</span>
                        </div>

                        {{-- Share of value, so the bar and the percentage agree. --}}
                        <div class="vx-age-track">
                            <div class="vx-age-fill is-{{ $bucket['tone'] }}" style="width: {{ $bucket['pct'] }}%"></div>
                        </div>
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

@php
    $kpis       = $this->getKpis();
    $trend      = $this->getValueTrend();
    $categories = $this->getCategoryBreakdown();
    $lowStock   = $this->getLowStockItems();
    $locations  = $this->getLocationHealth();
@endphp

<x-filament-panels::page>
    <div class="vx-an">

        {{-- ── KPI tiles ─────────────────────────────────────────────── --}}
        <div class="vx-an-kpis">
            @foreach ($kpis as $kpi)
                <div class="vx-an-kpi">
                    <div class="vx-an-kpi-top">
                        <span class="vx-an-kpi-label">{{ $kpi['label'] }}</span>
                        <span class="vx-stat-icon vx-tone-{{ $kpi['tone'] }}">
                            <x-filament::icon :icon="$kpi['icon']" class="vx-an-kpi-glyph" />
                        </span>
                    </div>

                    <p class="vx-an-kpi-value">{{ $kpi['value'] }}</p>

                    @if (! empty($kpi['delta']))
                        <p class="vx-an-delta is-{{ $kpi['delta']['direction'] }}">
                            <x-filament::icon
                                :icon="$kpi['delta']['direction'] === 'up' ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'"
                                class="vx-an-delta-glyph"
                            />
                            {{ $kpi['delta']['pct'] }}%
                            <span class="vx-an-delta-note">vs 30 days ago</span>
                        </p>
                    @elseif (! empty($kpi['sub']))
                        <p class="vx-an-kpi-sub">{{ $kpi['sub'] }}</p>
                    @else
                        <p class="vx-an-kpi-sub">No history to compare yet</p>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- ── Charts ────────────────────────────────────────────────── --}}
        <div class="vx-an-charts">

            {{-- Inventory value over time --}}
            <section class="vx-an-panel">
                <header class="vx-an-panel-head">
                    <div>
                        <h2>Inventory Value Over Time</h2>
                        <p>Last 30 days{{ ($trend['source'] ?? null) === 'movements' ? ' · reconstructed from stock movements' : '' }}</p>
                    </div>
                    @if (! ($trend['empty'] ?? true))
                        <div class="vx-an-panel-figure">
                            <span class="vx-an-panel-value">{{ $trend['latest'] }}</span>
                            @if (! empty($trend['change']))
                                <span class="vx-an-delta is-{{ $trend['change']['direction'] }}">
                                    {{ $trend['change']['direction'] === 'up' ? '▲' : '▼' }}
                                    {{ $trend['change']['pct'] }}%
                                </span>
                            @endif
                        </div>
                    @endif
                </header>

                @if ($trend['empty'] ?? true)
                    <div class="vx-an-blank">
                        <p>No valuation history yet.</p>
                        <p class="vx-an-blank-note">
                            A snapshot is captured nightly — the chart fills in once two days have been recorded.
                        </p>
                    </div>
                @else
                    <div class="vx-an-chart">
                        <svg viewBox="0 0 720 232" role="img"
                             aria-label="Inventory value over the last 30 days"
                             preserveAspectRatio="xMidYMid meet">
                            <defs>
                                <linearGradient id="vxAnFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.22" />
                                    <stop offset="100%" stop-color="#3b82f6" stop-opacity="0" />
                                </linearGradient>
                            </defs>

                            {{-- Gridlines + value axis --}}
                            @foreach ($trend['yTicks'] as $tick)
                                <line x1="56" x2="704" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}"
                                      class="vx-an-grid" />
                                <text x="48" y="{{ $tick['y'] + 4 }}" text-anchor="end"
                                      class="vx-an-axis">{{ $tick['label'] }}</text>
                            @endforeach

                            <path d="{{ $trend['area'] }}" fill="url(#vxAnFill)" />
                            <path d="{{ $trend['line'] }}" class="vx-an-line" />

                            @foreach ($trend['points'] as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="8"
                                        class="vx-an-hit">
                                    <title>{{ $point['date'] }} — {{ $point['value'] }}</title>
                                </circle>
                            @endforeach

                            {{-- Date axis --}}
                            @foreach ($trend['xLabels'] as $label)
                                <text x="{{ $label['x'] }}" y="224" text-anchor="{{ $label['anchor'] }}"
                                      class="vx-an-axis">{{ $label['label'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                @endif
            </section>

            {{-- Value by category --}}
            <section class="vx-an-panel">
                <header class="vx-an-panel-head">
                    <div>
                        <h2>Inventory by Category</h2>
                        <p>Share of stock value on hand</p>
                    </div>
                </header>

                @if ($categories['empty'])
                    <div class="vx-an-blank">
                        <p>Nothing in stock to break down.</p>
                    </div>
                @else
                    <div class="vx-an-donut-wrap">
                        <div class="vx-an-donut">
                            <svg viewBox="0 0 140 140" role="img" aria-label="Inventory value by category">
                                <g transform="rotate(-90 70 70)">
                                    <circle cx="70" cy="70" r="54" class="vx-an-donut-track" />
                                    @foreach ($categories['segments'] as $segment)
                                        <circle cx="70" cy="70" r="54"
                                                fill="none"
                                                stroke="{{ $segment['color'] }}"
                                                stroke-width="18"
                                                stroke-dasharray="{{ $segment['dash'] }}"
                                                stroke-dashoffset="{{ $segment['offset'] }}">
                                            <title>{{ $segment['name'] }} — {{ $segment['pct'] }}%</title>
                                        </circle>
                                    @endforeach
                                </g>
                            </svg>
                            <div class="vx-an-donut-center">
                                <span class="vx-an-donut-value">{{ $categories['total_label'] }}</span>
                                <span class="vx-an-donut-label">Total value</span>
                            </div>
                        </div>

                        <ul class="vx-an-legend">
                            @foreach ($categories['segments'] as $segment)
                                <li>
                                    <span class="vx-an-swatch" style="background: {{ $segment['color'] }}"></span>
                                    <span class="vx-an-legend-main">
                                        <span class="vx-an-legend-name">{{ $segment['name'] }}</span>
                                        <span class="vx-an-legend-units">
                                            {{ number_format($segment['units']) }} units ·
                                            {{ $segment['value_label'] }}
                                        </span>
                                    </span>
                                    <span class="vx-an-legend-pct">{{ $segment['pct'] }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        {{-- ── Low stock ─────────────────────────────────────────────── --}}
        <section class="vx-an-panel">
            <header class="vx-an-panel-head">
                <div>
                    <h2>Low Stock Items</h2>
                    <p>At or below their reorder level</p>
                </div>
                <a href="{{ route('filament.admin.resources.inventory-items.index') }}" class="vx-an-link">
                    View all items
                </a>
            </header>

            @if (empty($lowStock))
                <div class="vx-an-blank">
                    <p>Everything is above its reorder level.</p>
                </div>
            @else
                <div class="vx-an-table-wrap">
                    <table class="vx-an-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>SKU</th>
                                <th class="is-num">On Hand</th>
                                <th class="is-num">Reorder At</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lowStock as $item)
                                {{-- data-label drives the stacked mobile layout; the same
                                     markup is a plain table from 640px up. --}}
                                <tr>
                                    <td class="vx-an-cell-name" data-label="Item">{{ $item['name'] }}</td>
                                    <td class="vx-an-cell-muted" data-label="SKU">{{ $item['sku'] ?: '—' }}</td>
                                    <td class="is-num" data-label="On hand">{{ number_format($item['current']) }}</td>
                                    <td class="is-num vx-an-cell-muted" data-label="Reorder at">{{ number_format($item['reorder']) }}</td>
                                    <td data-label="Status">
                                        <span class="vx-an-pill is-{{ $item['status'] === 'out_of_stock' ? 'danger' : 'warn' }}">
                                            {{ $item['status'] === 'out_of_stock' ? 'Out of stock' : 'Low stock' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ── Locations ─────────────────────────────────────────────── --}}
        @if (! empty($locations))
            <section class="vx-an-panel">
                <header class="vx-an-panel-head">
                    <div>
                        <h2>Value by Location</h2>
                        <p>Where the stock is sitting</p>
                    </div>
                </header>

                @php ($topValue = max(array_column($locations, 'value') ?: [1]))
                <ul class="vx-an-bars">
                    @foreach ($locations as $location)
                        <li>
                            <div class="vx-an-bar-head">
                                <span class="vx-an-bar-name">{{ $location['name'] }}</span>
                                <span class="vx-an-bar-value">${{ number_format($location['value'], 2) }}</span>
                            </div>
                            <div class="vx-an-bar-track">
                                <div class="vx-an-bar-fill"
                                     style="width: {{ $topValue > 0 ? round(($location['value'] / $topValue) * 100, 1) : 0 }}%"></div>
                            </div>
                            <div class="vx-an-bar-meta">
                                {{ number_format($location['total_units']) }} units ·
                                {{ number_format($location['unique_items']) }} items
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-filament-panels::page>

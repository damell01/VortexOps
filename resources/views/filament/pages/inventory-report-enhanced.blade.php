<x-filament-panels::page>
    @php
        $data = $this->getData();
        $snapshot = $data['currentSnapshot'];
        $trendData = $data['trendData'];
        $health = $this->stockHealth;
        $abc = $this->abcAnalysis;
    @endphp

    <div class="space-y-6">

        {{-- Export Button --}}
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Inventory Report & Analytics</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">As of {{ $snapshot->snapshot_date->format('M d, Y g:i A') }}</p>
            </div>
            <button type="button"
                    wire:click="exportPdf"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition-colors">
                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                Export PDF
            </button>
        </div>

        {{-- Tab Navigation --}}
        <div class="flex gap-2 overflow-x-auto border-b border-gray-200 dark:border-gray-700 pb-px scrollbar-none -mx-1 px-1">
            @foreach([
                'overview' => 'Overview',
                'health' => 'Stock Health',
                'velocity' => 'Velocity',
                'abc' => 'ABC Analysis',
                'coverage' => 'Coverage',
                'locations' => 'Locations'
            ] as $key => $label)
            <button wire:click="setTab('{{ $key }}')" type="button"
                class="flex-shrink-0 px-4 py-2 text-sm font-medium rounded-t transition-colors whitespace-nowrap
                    {{ $activeTab === $key
                        ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 border border-b-white dark:border-b-gray-900 border-gray-200 dark:border-gray-700 shadow-sm'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>

        {{-- TAB: OVERVIEW --}}
        @if($activeTab === 'overview')
        <div class="space-y-6">
            {{-- Key Metrics Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Inventory Value</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100 tabular-nums">
                        ${{ number_format($snapshot->total_value, 2) }}
                    </p>
                    @php
                        $prevSnapshot = \App\Models\InventorySnapshot::where('snapshot_date', '<', $snapshot->snapshot_date)->latest('snapshot_date')->first();
                        $valueDiff = $prevSnapshot ? $snapshot->total_value - $prevSnapshot->total_value : 0;
                        $valueDiffPct = $prevSnapshot && $prevSnapshot->total_value > 0 ? (($valueDiff / $prevSnapshot->total_value) * 100) : 0;
                    @endphp
                    @if ($valueDiff != 0)
                    <p class="text-xs font-medium mt-2 {{ $valueDiff > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $valueDiff > 0 ? '↑' : '↓' }} ${{ abs($valueDiff) }} ({{ number_format($valueDiffPct, 1) }}%)
                    </p>
                    @endif
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Items</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $snapshot->total_items }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">SKUs in inventory</p>
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Units</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($snapshot->total_quantity) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Quantity on hand</p>
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stock Alerts</p>
                    <p class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400">
                        {{ collect($snapshot->stock_outs ?? [])->count() }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Items out of stock</p>
                </div>
            </div>

            {{-- Value Trend Chart --}}
            @if ($trendData->count() > 1)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">30-Day Value Trend</h3>
                <div class="flex items-end justify-between h-32 gap-1">
                    @php
                        $maxValue = $trendData->max('value') ?: 1;
                        $minValue = $trendData->min('value') ?: 0;
                        $range = $maxValue - $minValue ?: 1;
                    @endphp
                    @foreach ($trendData as $trend)
                    @php $height = (($trend['value'] - $minValue) / $range) * 100; @endphp
                    <div class="flex-1 flex flex-col items-center group">
                        <div class="w-full bg-gradient-to-t from-primary-500 to-primary-400 rounded-t" style="height: {{ max($height, 5) }}%"></div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 group-hover:font-semibold">{{ $trend['date'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Location Breakdown --}}
            @php $locations = $this->locationHealth; @endphp
            @if($locations)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Locations by Value</h3>
                <div class="space-y-3">
                    @foreach($locations->take(5) as $location)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $location['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $location['total_items'] }} items</p>
                        </div>
                        <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($location['total_value'], 2) }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- TAB: STOCK HEALTH --}}
        @if($activeTab === 'health')
        <div class="space-y-6">
            {{-- Stock Health Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-400">✓ Healthy Stock</p>
                    <p class="mt-2 text-3xl font-bold text-green-900 dark:text-green-100">{{ $health['healthy'] }}</p>
                    <p class="text-xs text-green-700 dark:text-green-400 mt-2">{{ round(($health['healthy']/$health['total'])*100) }}% of inventory</p>
                </div>

                <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-yellow-700 dark:text-yellow-400">⚠ Low Stock</p>
                    <p class="mt-2 text-3xl font-bold text-yellow-900 dark:text-yellow-100">{{ $health['low_stock'] }}</p>
                    <p class="text-xs text-yellow-700 dark:text-yellow-400 mt-2">Below reorder level</p>
                </div>

                <div class="rounded-lg border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-900/20 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-orange-700 dark:text-orange-400">📦 Over Stock</p>
                    <p class="mt-2 text-3xl font-bold text-orange-900 dark:text-orange-100">{{ $health['over_stock'] }}</p>
                    <p class="text-xs text-orange-700 dark:text-orange-400 mt-2">Above 3x reorder</p>
                </div>

                <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-400">🔴 Out of Stock</p>
                    <p class="mt-2 text-3xl font-bold text-red-900 dark:text-red-100">{{ $health['out_of_stock'] }}</p>
                    <p class="text-xs text-red-700 dark:text-red-400 mt-2">No inventory</p>
                </div>
            </div>

            {{-- Health Bar Visualization --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Stock Distribution</h3>
                <div class="flex items-end justify-between h-20 gap-2">
                    @php
                        $total = $health['total'];
                        $healthyPct = $total > 0 ? ($health['healthy'] / $total) * 100 : 0;
                        $lowPct = $total > 0 ? ($health['low_stock'] / $total) * 100 : 0;
                        $overPct = $total > 0 ? ($health['over_stock'] / $total) * 100 : 0;
                        $outPct = $total > 0 ? ($health['out_of_stock'] / $total) * 100 : 0;
                    @endphp

                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-green-500 rounded-t" style="height: {{ $healthyPct ?: 5 }}%"></div>
                        <p class="text-xs font-semibold text-green-700 dark:text-green-400 mt-2">{{ round($healthyPct) }}%</p>
                    </div>
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-yellow-500 rounded-t" style="height: {{ $lowPct ?: 5 }}%"></div>
                        <p class="text-xs font-semibold text-yellow-700 dark:text-yellow-400 mt-2">{{ round($lowPct) }}%</p>
                    </div>
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-orange-500 rounded-t" style="height: {{ $overPct ?: 5 }}%"></div>
                        <p class="text-xs font-semibold text-orange-700 dark:text-orange-400 mt-2">{{ round($overPct) }}%</p>
                    </div>
                    <div class="flex-1 flex flex-col items-center">
                        <div class="w-full bg-red-500 rounded-t" style="height: {{ $outPct ?: 5 }}%"></div>
                        <p class="text-xs font-semibold text-red-700 dark:text-red-400 mt-2">{{ round($outPct) }}%</p>
                    </div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mt-4">
                    <span>Healthy</span>
                    <span>Low Stock</span>
                    <span>Over Stock</span>
                    <span>Out</span>
                </div>
            </div>

            {{-- Low Stock Items Table --}}
            @if(!empty($this->lowStockItems))
            <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-white dark:bg-gray-800 p-6">
                <h3 class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 mb-4">⚠️ Low Stock Items ({{ count($this->lowStockItems) }})</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">SKU / Name</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Current</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Reorder</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Suggested</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->lowStockItems as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="py-3 px-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $item['sku'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item['name'] }}</div>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <span class="text-yellow-700 dark:text-yellow-300 font-semibold">{{ (int)$item['current_qty'] }}</span>
                                </td>
                                <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">{{ (int)$item['reorder_level'] }}</td>
                                <td class="py-3 px-3 text-right font-semibold text-green-700 dark:text-green-400">{{ (int)$item['suggested_qty'] }}</td>
                                <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($item['avg_cost'], 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- TAB: VELOCITY --}}
        @if($activeTab === 'velocity')
        <div class="space-y-6">
            {{-- Fast Movers --}}
            @php $fastMovers = $this->fastMovers; @endphp
            @if($fastMovers)
            <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-6">
                <h3 class="text-sm font-semibold text-green-900 dark:text-green-100 mb-4">🚀 Fast Movers ({{ count($fastMovers) }})</h3>
                <div class="space-y-2">
                    @foreach($fastMovers as $item)
                    <div class="flex justify-between items-center p-2 text-sm bg-white dark:bg-gray-800 rounded">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] ?? $item['sku'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ $item['sku'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-green-700 dark:text-green-400">{{ round($item['velocity'], 1) }} units/day</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Qty: {{ (int)$item['quantity'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Slow Movers --}}
            @php $slowMovers = $this->slowMovers; @endphp
            @if($slowMovers)
            <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 p-6">
                <h3 class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 mb-4">🐢 Slow Movers ({{ count($slowMovers) }})</h3>
                <div class="space-y-2">
                    @foreach($slowMovers as $item)
                    <div class="flex justify-between items-center p-2 text-sm bg-white dark:bg-gray-800 rounded">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] ?? $item['sku'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ $item['sku'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-yellow-700 dark:text-yellow-400">{{ round($item['velocity'], 1) }} units/day</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Qty: {{ (int)$item['quantity'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Dead Stock --}}
            @php $deadStock = $this->deadStock; @endphp
            @if($deadStock)
            <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-6">
                <h3 class="text-sm font-semibold text-red-900 dark:text-red-100 mb-4">💀 Dead Stock ({{ count($deadStock) }})</h3>
                <div class="space-y-2">
                    @foreach($deadStock as $item)
                    <div class="flex justify-between items-center p-2 text-sm bg-white dark:bg-gray-800 rounded">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] ?? $item['sku'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ $item['sku'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-red-700 dark:text-red-400">No sales</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Qty: {{ (int)$item['quantity'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- TAB: ABC ANALYSIS --}}
        @if($activeTab === 'abc')
        <div class="space-y-6">
            {{-- ABC Summary Cards --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Class A --}}
                <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-6">
                    <h3 class="text-sm font-semibold text-red-900 dark:text-red-100 mb-3">
                        🔴 Class A Items
                    </h3>
                    <p class="text-2xl font-bold text-red-900 dark:text-red-100 mb-1">{{ count($abc['class_a']) }}</p>
                    <p class="text-xs text-red-700 dark:text-red-400 mb-4">80% of inventory value</p>
                    <p class="text-xs text-red-700 dark:text-red-400">High priority - Critical stock items requiring careful management</p>
                </div>

                {{-- Class B --}}
                <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 p-6">
                    <h3 class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 mb-3">
                        🟡 Class B Items
                    </h3>
                    <p class="text-2xl font-bold text-yellow-900 dark:text-yellow-100 mb-1">{{ count($abc['class_b']) }}</p>
                    <p class="text-xs text-yellow-700 dark:text-yellow-400 mb-4">15% of inventory value</p>
                    <p class="text-xs text-yellow-700 dark:text-yellow-400">Medium priority - Regular monitoring recommended</p>
                </div>

                {{-- Class C --}}
                <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-6">
                    <h3 class="text-sm font-semibold text-green-900 dark:text-green-100 mb-3">
                        🟢 Class C Items
                    </h3>
                    <p class="text-2xl font-bold text-green-900 dark:text-green-100 mb-1">{{ count($abc['class_c']) }}</p>
                    <p class="text-xs text-green-700 dark:text-green-400 mb-4">5% of inventory value</p>
                    <p class="text-xs text-green-700 dark:text-green-400">Low priority - Bulk ordering possible</p>
                </div>
            </div>

            {{-- Class A Items Table --}}
            @if(!empty($abc['class_a']))
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Class A - Top Value Items</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">SKU</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Qty</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Unit Cost</th>
                                <th class="text-right py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Total Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($abc['class_a'] as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="py-3 px-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $item['sku'] }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item['name'] }}</div>
                                </td>
                                <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">{{ (int)$item['qty'] }}</td>
                                <td class="py-3 px-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($item['cost'], 2) }}</td>
                                <td class="py-3 px-3 text-right font-semibold text-gray-900 dark:text-white">${{ number_format($item['value'], 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- TAB: COVERAGE --}}
        @if($activeTab === 'coverage')
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">📊 Days of Stock Coverage</h3>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($this->stockCoverage as $item)
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 dark:text-white truncate">{{ $item['sku'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['name'] }}</p>
                    </div>
                    <div class="text-right">
                        <div class="font-semibold text-gray-900 dark:text-white">
                            @if($item['days_of_stock'] >= 90)
                                <span class="text-green-600 dark:text-green-400">{{ $item['days_of_stock'] }} days</span>
                            @elseif($item['days_of_stock'] >= 30)
                                <span class="text-blue-600 dark:text-blue-400">{{ $item['days_of_stock'] }} days</span>
                            @elseif($item['days_of_stock'] > 0)
                                <span class="text-yellow-600 dark:text-yellow-400">{{ $item['days_of_stock'] }} days</span>
                            @else
                                <span class="text-red-600 dark:text-red-400">Out</span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($item['velocity'], 1) }} units/day</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- TAB: LOCATIONS --}}
        @if($activeTab === 'locations')
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">📍 Inventory by Location</h3>
            <div class="space-y-3">
                @foreach($this->locationHealth as $location)
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $location['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                @if($location['type'])
                                    {{ ucfirst(str_replace('_', ' ', $location['type'])) }}
                                @endif
                            </p>
                        </div>
                        <p class="font-semibold text-gray-900 dark:text-white">${{ number_format($location['total_value'], 2) }}</p>
                    </div>
                    <div class="flex gap-2 text-xs">
                        <span class="px-2 py-1 rounded bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                            ✓ {{ $location['healthy'] }} healthy
                        </span>
                        @if($location['low_stock'] > 0)
                        <span class="px-2 py-1 rounded bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                            ⚠ {{ $location['low_stock'] }} low
                        </span>
                        @endif
                        @if($location['out_of_stock'] > 0)
                        <span class="px-2 py-1 rounded bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                            🔴 {{ $location['out_of_stock'] }} out
                        </span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</x-filament-panels::page>

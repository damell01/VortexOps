<x-filament-panels::page>
    @php
        $data = $this->getData();
        $snapshot = $data['currentSnapshot'];
        $trendData = $data['trendData'];
        $health = $this->stockHealth;
        $abc = $this->abcAnalysis;
    @endphp

    <div class="space-y-6">

        {{-- Header with Export Options --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Inventory Report & Analytics</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">As of {{ $snapshot->snapshot_date->format('M d, Y g:i A') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button"
                        wire:click="exportComprehensivePdf"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 hover:bg-red-700 px-3 py-2 text-xs font-semibold text-white transition-colors" title="Full report with all analytics">
                    <x-heroicon-o-document-text class="h-4 w-4" />
                    Full PDF
                </button>
                <button type="button"
                        wire:click="exportLowStockAlertPdf"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-600 hover:bg-amber-700 px-3 py-2 text-xs font-semibold text-white transition-colors" title="Alert for items needing reorder">
                    <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                    Low Stock Alert
                </button>
                <button type="button"
                        wire:click="exportCsv"
                        class="inline-flex items-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 px-3 py-2 text-xs font-semibold text-white transition-colors" title="Download as CSV for Excel">
                    <x-heroicon-o-table-cells class="h-4 w-4" />
                    CSV
                </button>
                <button type="button"
                        wire:click="exportBreakdown"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-2 text-xs font-semibold text-white transition-colors" title="Download breakdown by location">
                    <x-heroicon-o-chart-bar class="h-4 w-4" />
                    Breakdown
                </button>
                <div class="flex items-center text-xs text-gray-500 dark:text-gray-400 ml-2 pl-2 border-l border-gray-300 dark:border-gray-600">
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4 mr-1" />
                    Export Options
                </div>
            </div>
        </div>

        {{-- Quick Action Tools --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400 mb-3">Quick Tools</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('filament.admin.pages.inventory-scanner') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 hover:bg-blue-200 dark:hover:bg-blue-900/50 px-3 py-2 text-xs font-medium text-blue-700 dark:text-blue-300 transition-colors">
                    <x-heroicon-o-qr-code class="h-4 w-4" />
                    Scanner
                </a>
                <a href="{{ route('filament.admin.pages.inventory-search') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-purple-100 dark:bg-purple-900/30 hover:bg-purple-200 dark:hover:bg-purple-900/50 px-3 py-2 text-xs font-medium text-purple-700 dark:text-purple-300 transition-colors">
                    <x-heroicon-o-magnifying-glass class="h-4 w-4" />
                    Advanced Search
                </a>
                <a href="{{ route('filament.admin.pages.stock-transfer') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-orange-100 dark:bg-orange-900/30 hover:bg-orange-200 dark:hover:bg-orange-900/50 px-3 py-2 text-xs font-medium text-orange-700 dark:text-orange-300 transition-colors">
                    <x-heroicon-o-arrow-path class="h-4 w-4" />
                    Stock Transfer
                </a>
                <a href="{{ route('filament.admin.pages.inventory-reconciliation') }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-green-100 dark:bg-green-900/30 hover:bg-green-200 dark:hover:bg-green-900/50 px-3 py-2 text-xs font-medium text-green-700 dark:text-green-300 transition-colors">
                    <x-heroicon-o-check-circle class="h-4 w-4" />
                    Reconciliation
                </a>
            </div>
        </div>

        {{-- Tab Navigation with Icons --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <div class="flex gap-1 overflow-x-auto scrollbar-none -mx-1 px-1">
                @php
                    $tabs = [
                        'overview' => ['Overview', '📊'],
                        'health' => ['Stock Health', '❤️'],
                        'velocity' => ['Velocity', '⚡'],
                        'abc' => ['ABC Analysis', '🎯'],
                        'coverage' => ['Coverage', '📈'],
                        'locations' => ['Locations', '📍'],
                        'breakdowns' => ['Breakdowns', '🔍'],
                        'aging' => ['Aging', '⏳'],
                        'margins' => ['Margins', '💰'],
                        'lot-aging' => ['Lot Aging', '📦']
                    ];
                @endphp
                @foreach($tabs as $key => $info)
                <button wire:click="setTab('{{ $key }}')" type="button"
                    class="flex-shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-all duration-200
                        {{ $activeTab === $key
                            ? 'text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400 bg-primary-50 dark:bg-primary-950/30'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                    <span class="mr-2">{{ $info[1] }}</span>{{ $info[0] }}
                </button>
                @endforeach
            </div>
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
                    <div class="flex-1 flex flex-col items-center justify-end">
                        <div class="w-full bg-primary-500 rounded-t" style="height: {{ max(5, $height) }}%"></div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $trend['date'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Breakdown by Location --}}
            @if ($snapshot->location_breakdown)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Inventory by Location</h3>
                <div class="space-y-3">
                    @foreach ($snapshot->location_breakdown as $locId => $location)
                    <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $location['name'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($location['quantity']) }} units</p>
                        </div>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($location['value'], 2) }}</p>
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
            {{-- Health Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-400">✓ Healthy</p>
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-400">🔴 Out</p>
                    <p class="mt-2 text-3xl font-bold text-red-900 dark:text-red-100">{{ $health['out_of_stock'] }}</p>
                    <p class="text-xs text-red-700 dark:text-red-400 mt-2">No inventory</p>
                </div>
            </div>

            {{-- Health Distribution Bar --}}
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

            {{-- Low Stock Items --}}
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Fast Movers --}}
            @if(!empty($this->fastMovers))
            <div class="rounded-lg border border-green-200 dark:border-green-800 bg-white dark:bg-gray-800 p-6">
                <h3 class="text-sm font-semibold text-green-900 dark:text-green-100 mb-4">🚀 Fast Movers (30 days)</h3>
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    @foreach($this->fastMovers as $item)
                    <div class="flex justify-between items-start p-2 bg-gray-50 dark:bg-gray-700 rounded">
                        <div class="flex-1">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['sku'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['name'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-green-700 dark:text-green-400">{{ round($item['velocity'], 1) }} units/day</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Dead Stock --}}
            @if(!empty($this->deadStock))
            <div class="rounded-lg border border-red-200 dark:border-red-800 bg-white dark:bg-gray-800 p-6">
                <h3 class="text-sm font-semibold text-red-900 dark:text-red-100 mb-4">💀 Dead Stock (No sales)</h3>
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    @foreach($this->deadStock as $item)
                    <div class="flex justify-between items-start p-2 bg-gray-50 dark:bg-gray-700 rounded">
                        <div class="flex-1">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['sku'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['name'] }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-red-700 dark:text-red-400">{{ (int)$item['qty'] }} units</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Slow Movers --}}
            @if(!empty($this->slowMovers))
            <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-white dark:bg-gray-800 p-6 lg:col-span-2">
                <h3 class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 mb-4">⏸️ Slow Movers (30+ days)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-80 overflow-y-auto">
                    @foreach($this->slowMovers as $item)
                    <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $item['sku'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['name'] }}</p>
                        <p class="text-sm font-semibold text-yellow-700 dark:text-yellow-400 mt-1">{{ round($item['velocity'], 2) }} units/day</p>
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
                <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-6">
                    <h3 class="text-sm font-semibold text-red-900 dark:text-red-100 mb-3">🔴 Class A Items</h3>
                    <p class="text-2xl font-bold text-red-900 dark:text-red-100 mb-1">{{ count($abc['class_a']) }}</p>
                    <p class="text-xs text-red-700 dark:text-red-400 mb-3">80% of inventory value</p>
                    <p class="text-xs text-red-700 dark:text-red-400">High priority - Critical stock items requiring careful management</p>
                </div>

                <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 p-6">
                    <h3 class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 mb-3">🟡 Class B Items</h3>
                    <p class="text-2xl font-bold text-yellow-900 dark:text-yellow-100 mb-1">{{ count($abc['class_b']) }}</p>
                    <p class="text-xs text-yellow-700 dark:text-yellow-400 mb-3">15% of inventory value</p>
                    <p class="text-xs text-yellow-700 dark:text-yellow-400">Medium priority - Regular monitoring recommended</p>
                </div>

                <div class="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-6">
                    <h3 class="text-sm font-semibold text-green-900 dark:text-green-100 mb-3">🟢 Class C Items</h3>
                    <p class="text-2xl font-bold text-green-900 dark:text-green-100 mb-1">{{ count($abc['class_c']) }}</p>
                    <p class="text-xs text-green-700 dark:text-green-400 mb-3">5% of inventory value</p>
                    <p class="text-xs text-green-700 dark:text-green-400">Low priority - Bulk ordering possible</p>
                </div>
            </div>

            {{-- ABC Items Tables --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @if(!empty($abc['class_a']))
                <div class="rounded-lg border border-red-200 dark:border-red-800 bg-white dark:bg-gray-800 p-6">
                    <h3 class="text-sm font-semibold text-red-900 dark:text-red-100 mb-4">Class A Top Items</h3>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @foreach($abc['class_a'] as $item)
                        <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['sku'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['name'] }}</p>
                            <p class="text-sm font-semibold text-red-700 dark:text-red-400 mt-1">${{ number_format($item['value'], 2) }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(!empty($abc['class_b']))
                <div class="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-white dark:bg-gray-800 p-6">
                    <h3 class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 mb-4">Class B Top Items</h3>
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @foreach($abc['class_b'] as $item)
                        <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['sku'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['name'] }}</p>
                            <p class="text-sm font-semibold text-yellow-700 dark:text-yellow-400 mt-1">${{ number_format($item['value'], 2) }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
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
                            @if($item['days_of_stock'] == 0)
                                <span class="text-red-600 dark:text-red-400">Out</span>
                            @elseif($item['days_of_stock'] < 7)
                                <span class="text-red-600 dark:text-red-400">{{ $item['days_of_stock'] }} days</span>
                            @elseif($item['days_of_stock'] < 30)
                                <span class="text-yellow-600 dark:text-yellow-400">{{ $item['days_of_stock'] }} days</span>
                            @elseif($item['days_of_stock'] < 90)
                                <span class="text-blue-600 dark:text-blue-400">{{ $item['days_of_stock'] }} days</span>
                            @else
                                <span class="text-green-600 dark:text-green-400">{{ $item['days_of_stock'] }} days</span>
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
        <div class="space-y-4">
            @foreach($this->locationHealth as $location)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $location['name'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @if($location['type'])
                                {{ ucfirst(str_replace('_', ' ', $location['type'])) }}
                            @endif
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-2xl text-gray-900 dark:text-white">${{ number_format($location['total_value'], 2) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $location['total_items'] }} items</p>
                    </div>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <span class="px-3 py-1 rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs font-medium">
                        ✓ {{ $location['healthy'] }} healthy
                    </span>
                    @if($location['low_stock'] > 0)
                    <span class="px-3 py-1 rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 text-xs font-medium">
                        ⚠ {{ $location['low_stock'] }} low
                    </span>
                    @endif
                    @if($location['out_of_stock'] > 0)
                    <span class="px-3 py-1 rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs font-medium">
                        🔴 {{ $location['out_of_stock'] }} out
                    </span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- TAB: BREAKDOWNS --}}
        @if($activeTab === 'breakdowns')
        <div class="space-y-8">
            {{-- Category Breakdown --}}
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">By Category</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach($this->categoryBreakdown as $cat)
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $cat['category'] }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $cat['item_count'] }} items</p>
                            </div>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">${{ number_format($cat['total_value'], 2) }}</p>
                        </div>
                        <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400 pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span>{{ number_format($cat['total_quantity'], 0) }} units</span>
                            <span>${{ number_format($cat['avg_unit_cost'], 2) }} avg cost</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Vendor Breakdown --}}
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">By Vendor</h3>
                <div class="space-y-3">
                    @foreach($this->vendorBreakdown as $vendor)
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $vendor['vendor'] }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $vendor['item_count'] }} products • {{ number_format($vendor['total_quantity'], 0) }} units</p>
                            </div>
                            <p class="text-lg font-bold text-gray-900 dark:text-white text-right">${{ number_format($vendor['total_value'], 2) }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- TAB: AGING --}}
        @if($activeTab === 'aging')
        <div class="space-y-6">
            @php
                $aging = $this->agingInventory;
                $periods = ['current', 'thirty_days', 'sixty_days', 'ninety_days'];
                $colorMap = [
                    'current' => 'green',
                    'thirty_days' => 'yellow',
                    'sixty_days' => 'orange',
                    'ninety_days' => 'red'
                ];
            @endphp

            {{-- Age Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($periods as $period)
                @php $data = $aging[$period]; $color = $colorMap[$period]; @endphp
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ $data['label'] }}</p>
                    <div class="space-y-2">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">${{ number_format($data['value'], 2) }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ $data['count'] }} distinct items</p>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Detailed Lists --}}
            @foreach($periods as $period)
            @php $data = $aging[$period]; $color = $colorMap[$period]; @endphp
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ $data['label'] }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Product</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Qty</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Unit Cost</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Total Value</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Days Old</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($data['items'] as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['sku'] }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-900 dark:text-white">{{ number_format($item['quantity'], 0) }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-white">${{ number_format($item['unit_cost'], 2) }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">${{ number_format($item['total_value'], 2) }}</td>
                                <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $item['days_old'] }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No items in this age group</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- TAB: MARGINS --}}
        @if($activeTab === 'margins')
        <div class="space-y-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                <p class="text-sm text-blue-800 dark:text-blue-300">
                    <strong>Margin Analysis:</strong> Shows profitability per item based on retail price minus average cost.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Product</th>
                            <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Qty</th>
                            <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Cost</th>
                            <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Revenue</th>
                            <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Margin %</th>
                            <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Total Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->marginAnalysis as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <td class="px-4 py-3">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['sku'] }}</p>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-900 dark:text-white">{{ number_format($item['quantity'], 0) }}</td>
                            <td class="px-4 py-3 text-gray-900 dark:text-white">${{ number_format($item['cost'], 2) }}</td>
                            <td class="px-4 py-3 text-gray-900 dark:text-white">${{ number_format($item['revenue'], 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs font-semibold
                                    @if($item['margin_percent'] >= 50)
                                        bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                                    @elseif($item['margin_percent'] >= 25)
                                        bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200
                                    @else
                                        bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                                    @endif
                                ">
                                    {{ number_format($item['margin_percent'], 1) }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">${{ number_format($item['total_margin'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No items with margin data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- TAB: LOT AGING --}}
        @if($activeTab === 'lot-aging')
        <div class="space-y-6">
            @php $lotAging = $this->lotAging; @endphp

            {{-- Aging Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach(['fresh', 'aging_30', 'aging_60', 'aging_90'] as $key)
                @php $data = $lotAging[$key]; @endphp
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $data['label'] }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($data['total_value'], 2) }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                            @if($key === 'fresh')
                                bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300
                            @elseif($key === 'aging_30')
                                bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300
                            @elseif($key === 'aging_60')
                                bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-300
                            @else
                                bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300
                            @endif
                        ">
                            {{ $data['count'] }} lots
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ number_format($data['total_quantity']) }} units</p>
                </div>
                @endforeach
            </div>

            {{-- Aging Details Tables --}}
            @foreach(['fresh', 'aging_30', 'aging_60', 'aging_90'] as $key)
            @php $data = $lotAging[$key]; @endphp
            @if($data['count'] > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $data['label'] }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <tr>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Item</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Vendor</th>
                                <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-medium">Received</th>
                                <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300 font-medium">Qty</th>
                                <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300 font-medium">Unit Cost</th>
                                <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300 font-medium">Total Value</th>
                                <th class="px-4 py-2 text-center text-gray-600 dark:text-gray-300 font-medium">Age</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($data['items'] as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $item['item_name'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['sku'] }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 text-sm">{{ $item['vendor'] }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-sm">{{ $item['received_at'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-white font-medium">{{ number_format($item['quantity']) }}</td>
                                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($item['unit_cost'], 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-white font-bold">${{ number_format($item['total_value'], 2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        @if($item['days_old'] <= 30)
                                            bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300
                                        @elseif($item['days_old'] <= 60)
                                            bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300
                                        @elseif($item['days_old'] <= 90)
                                            bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-300
                                        @else
                                            bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300
                                        @endif
                                    ">
                                        {{ $item['days_old'] }}d
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            @endforeach
        </div>
        @endif

    </div>
</x-filament-panels::page>

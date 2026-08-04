<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- KEY METRICS OVERVIEW                                               --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Total Inventory Value --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-950 dark:to-blue-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Inventory Value</p>
                        <p class="text-3xl font-bold text-blue-900 dark:text-blue-100 mt-2">${{ number_format($this->getTotalInventoryValueProperty(), 2) }}</p>
                    </div>
                    <x-heroicon-o-banknotes class="h-10 w-10 text-blue-200 dark:text-blue-800 opacity-50" />
                </div>
            </div>

            {{-- Total Items --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-green-50 to-green-100 dark:from-green-950 dark:to-green-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-600 dark:text-green-400">Total SKUs</p>
                        <p class="text-3xl font-bold text-green-900 dark:text-green-100 mt-2">{{ $this->getInventoryHealthProperty()['total_items'] }}</p>
                        <p class="text-xs text-green-700 dark:text-green-300 mt-1">{{ number_format($this->getInventoryHealthProperty()['total_units'], 0) }} units</p>
                    </div>
                    <x-heroicon-o-squares-2x2 class="h-10 w-10 text-green-200 dark:text-green-800 opacity-50" />
                </div>
            </div>

            {{-- Low Stock Alerts --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-950 dark:to-orange-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-orange-600 dark:text-orange-400">Low Stock</p>
                        <p class="text-3xl font-bold text-orange-900 dark:text-orange-100 mt-2">{{ $this->getInventoryHealthProperty()['low_stock_count'] }}</p>
                        <p class="text-xs text-orange-700 dark:text-orange-300 mt-1">Items below reorder</p>
                    </div>
                    <x-heroicon-o-exclamation-circle class="h-10 w-10 text-orange-200 dark:text-orange-800 opacity-50" />
                </div>
            </div>

            {{-- Issues to Resolve --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-red-50 to-red-100 dark:from-red-950 dark:to-red-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-red-600 dark:text-red-400">Issues</p>
                        <p class="text-3xl font-bold text-red-900 dark:text-red-100 mt-2">{{ $this->getInventoryHealthProperty()['no_cost_count'] + $this->getInventoryHealthProperty()['high_variance_count'] }}</p>
                        <p class="text-xs text-red-700 dark:text-red-300 mt-1">No cost or high variance</p>
                    </div>
                    <x-heroicon-o-exclamation-triangle class="h-10 w-10 text-red-200 dark:text-red-800 opacity-50" />
                </div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- VALUE BY LOCATION                                                  --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-map-pin class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                Inventory Value by Location
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Location</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Type</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">SKUs</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Value</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalValue = $this->getTotalInventoryValueProperty();
                        @endphp
                        @forelse($this->getValueByLocationProperty() as $loc)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">{{ $loc['name'] }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400 text-xs uppercase tracking-wide">{{ $loc['type'] ?? 'Standard' }}</td>
                            <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">{{ $loc['count'] }}</td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-gray-100 font-semibold">${{ number_format($loc['value'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">{{ $totalValue > 0 ? round(($loc['value'] / $totalValue) * 100, 1) : 0 }}%</td>
                        </tr>
                        @empty
                        <tr>
                            <td class="py-6 px-4 text-center text-gray-500 dark:text-gray-400 col-span-5">No locations found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- ════════════════════════════════════════════════════════════════════ --}}
            {{-- TOP 10 MOST VALUABLE ITEMS                                          --}}
            {{-- ════════════════════════════════════════════════════════════════════ --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-chart-bar class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    Top 10 Most Valuable Items
                </h2>
                <div class="space-y-3">
                    @forelse($this->getTopItemsProperty() as $idx => $item)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $idx + 1 }}</span>
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $item['name'] }}</p>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['sku'] }} • {{ number_format($item['qty'], 0) }} units @ ${{ number_format($item['avg_cost'], 2) }}</p>
                        </div>
                        <div class="text-right ml-4 flex-shrink-0">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">${{ number_format($item['value'], 2) }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-6">No items found</p>
                    @endforelse
                </div>
            </div>

            {{-- ════════════════════════════════════════════════════════════════════ --}}
            {{-- CATEGORY BREAKDOWN                                                  --}}
            {{-- ════════════════════════════════════════════════════════════════════ --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-tag class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    Value by Category
                </h2>
                <div class="space-y-3">
                    @php
                        $totalValue = $this->getTotalInventoryValueProperty();
                    @endphp
                    @forelse($this->getCategoryBreakdownProperty() as $cat)
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $cat['category'] }}</p>
                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ $cat['count'] }} items</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $totalValue > 0 ? round(($cat['value'] / $totalValue) * 100, 1) : 0 }}%"></div>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ number_format($cat['qty'], 0) }} units</p>
                            <p class="text-xs font-semibold text-gray-900 dark:text-white">${{ number_format($cat['value'], 2) }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-6">No categories found</p>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- LOW STOCK HIGH VALUE ALERTS                                         --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if(!empty($this->getLowStockHighValueProperty()))
        <div class="rounded-xl border-2 border-orange-300 dark:border-orange-700 bg-orange-50 dark:bg-orange-950 p-6">
            <h2 class="text-lg font-semibold text-orange-900 dark:text-orange-100 mb-4 flex items-center gap-2">
                <x-heroicon-o-exclamation-circle class="h-5 w-5" />
                Low Stock High Value Alerts
            </h2>
            <p class="text-sm text-orange-700 dark:text-orange-300 mb-4">
                These items are below reorder level but represent significant inventory value. Prioritize replenishment.
            </p>
            <div class="space-y-3">
                @foreach($this->getLowStockHighValueProperty() as $item)
                <div class="flex items-start justify-between p-4 bg-white dark:bg-gray-900 rounded-lg border border-orange-200 dark:border-orange-800">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            {{ $item['sku'] }} • Stock: {{ number_format($item['qty'], 0) }} (Reorder: {{ number_format($item['reorder'], 0) }})
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                            @ ${{ number_format($item['avg_cost'], 2) }} per unit
                        </p>
                    </div>
                    <div class="text-right ml-4 flex-shrink-0">
                        <p class="text-sm font-bold text-orange-900 dark:text-orange-100">${{ number_format($item['value'], 2) }}</p>
                        <span class="text-xs font-medium text-orange-700 dark:text-orange-300">At Risk</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</x-filament-panels::page>

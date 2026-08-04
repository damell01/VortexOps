<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- SUMMARY METRICS                                                     --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @php
            $summary = $this->getSummaryProperty();
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-950 dark:to-blue-900 p-6">
                <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Items</p>
                <p class="text-3xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ $summary['total_items'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-green-50 to-green-100 dark:from-green-950 dark:to-green-900 p-6">
                <p class="text-sm font-medium text-green-600 dark:text-green-400">Fast Movers</p>
                <p class="text-3xl font-bold text-green-900 dark:text-green-100 mt-2">{{ $summary['fast_movers'] }}</p>
                <p class="text-xs text-green-700 dark:text-green-300 mt-1">${{ number_format($summary['fast_movers_value'], 0) }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-950 dark:to-yellow-900 p-6">
                <p class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Slow Movers</p>
                <p class="text-3xl font-bold text-yellow-900 dark:text-yellow-100 mt-2">{{ $summary['slow_movers'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-950 dark:to-orange-900 p-6">
                <p class="text-sm font-medium text-orange-600 dark:text-orange-400">Dead Stock</p>
                <p class="text-3xl font-bold text-orange-900 dark:text-orange-100 mt-2">{{ $summary['dead_stock'] }}</p>
                <p class="text-xs text-orange-700 dark:text-orange-300 mt-1">${{ number_format($summary['dead_stock_value'], 0) }} ({{ $summary['dead_stock_pct'] }}%)</p>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-950 dark:to-purple-900 p-6">
                <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Total Value</p>
                <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-2">${{ number_format($summary['total_inventory_value'], 0) }}</p>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- HIGH-VALUE FAST MOVERS                                              --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-fire class="h-5 w-5 text-red-500" />
                High-Value Fast Movers (Priority Stock)
            </h2>
            <div class="space-y-3">
                @forelse($this->getHighValueFastMoversProperty() as $item)
                <div class="flex items-start justify-between p-4 bg-green-50 dark:bg-green-950 rounded-lg border border-green-200 dark:border-green-800">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $item['item_name'] }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            {{ $item['sku'] }} • {{ number_format($item['current_stock'], 0) }} units • Turnover: {{ $item['turnover_ratio'] }}x
                        </p>
                    </div>
                    <div class="text-right ml-4">
                        <p class="font-bold text-green-900 dark:text-green-100">${{ number_format($item['inventory_value'], 0) }}</p>
                        <p class="text-xs text-green-700 dark:text-green-300">{{ $item['daily_velocity'] }} units/day</p>
                    </div>
                </div>
                @empty
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No fast movers found</p>
                @endforelse
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- ════════════════════════════════════════════════════════════════════ --}}
            {{-- FAST MOVERS                                                         --}}
            {{-- ════════════════════════════════════════════════════════════════════ --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-bolt class="h-5 w-5 text-yellow-500" />
                    Fast Movers
                </h3>
                <div class="space-y-2">
                    @forelse($this->getFastMoversProperty() as $item)
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $item['item_name'] }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['sku'] }}</p>
                            </div>
                            <div class="text-right ml-2">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ number_format($item['current_stock'], 0) }} units</p>
                                <p class="text-xs text-yellow-600 dark:text-yellow-400">{{ $item['daily_velocity'] }}/day</p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No fast movers</p>
                    @endforelse
                </div>
            </div>

            {{-- ════════════════════════════════════════════════════════════════════ --}}
            {{-- DEAD STOCK                                                          --}}
            {{-- ════════════════════════════════════════════════════════════════════ --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <x-heroicon-o-exclamation-circle class="h-5 w-5 text-red-500" />
                    Dead Stock (Consider Clearance)
                </h3>
                <div class="space-y-2">
                    @forelse($this->getDeadStockProperty() as $item)
                    <div class="p-3 bg-red-50 dark:bg-red-950 rounded-lg border border-red-200 dark:border-red-800">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $item['item_name'] }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['sku'] }}</p>
                            </div>
                            <div class="text-right ml-2">
                                <p class="text-sm font-bold text-red-900 dark:text-red-100">${{ number_format($item['inventory_value'], 0) }}</p>
                                <p class="text-xs text-red-600 dark:text-red-400">{{ $item['current_stock'] }} units</p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No dead stock</p>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- SLOW MOVERS                                                         --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Slow Movers</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Item</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">SKU</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Stock</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Daily Velocity</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Days Remaining</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->getSlowMoversProperty() as $item)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-4 text-gray-900 dark:text-white">{{ $item['item_name'] }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $item['sku'] }}</td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ number_format($item['current_stock'], 0) }}</td>
                            <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">{{ $item['daily_velocity'] }} units</td>
                            <td class="py-3 px-4 text-right font-semibold {{ $item['days_stock'] < 30 ? 'text-orange-600 dark:text-orange-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $item['days_stock'] }} days
                            </td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-white font-semibold">${{ number_format($item['inventory_value'], 0) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td class="py-6 px-4 text-center text-gray-500 dark:text-gray-400 col-span-6">No slow movers</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-filament-panels::page>

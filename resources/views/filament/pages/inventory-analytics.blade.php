<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $lowStock = $this->getLowStockItems();
        $topVendors = $this->getTopVendors();
        $locations = $this->getLocationHealth();
        $fastMovers = $this->getFastMovers();
        $deadStock = $this->getDeadStock();
    @endphp

    <div class="space-y-6">
        <!-- Header with Export -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Inventory Analytics</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Real-time overview of your inventory health and metrics</p>
            </div>
            <a href="{{ route('export.inventory-analytics-pdf') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-red-600 hover:bg-red-700 px-4 py-2 text-sm font-semibold text-white transition-colors">
                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                Export PDF
            </a>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <!-- Total Inventory Value -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-600 dark:text-gray-400">Inventory Value</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">${{ number_format($summary['total_value'], 0) }}</p>
                    </div>
                    <div class="text-purple-600 dark:text-purple-400">
                        <x-heroicon-o-banknotes class="h-8 w-8" />
                    </div>
                </div>
            </div>

            <!-- Total Units -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-600 dark:text-gray-400">Units in Stock</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($summary['total_units']) }}</p>
                    </div>
                    <div class="text-blue-600 dark:text-blue-400">
                        <x-heroicon-o-cube class="h-8 w-8" />
                    </div>
                </div>
            </div>

            <!-- Active Items -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-600 dark:text-gray-400">Active Items</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $summary['total_items'] }}</p>
                    </div>
                    <div class="text-green-600 dark:text-green-400">
                        <x-heroicon-o-archive-box class="h-8 w-8" />
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase text-gray-600 dark:text-gray-400">Low Stock</p>
                        <p class="text-2xl font-bold {{ $summary['low_stock_count'] > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400' }} mt-1">{{ $summary['low_stock_count'] }}</p>
                    </div>
                    <div class="text-orange-600 dark:text-orange-400">
                        <x-heroicon-o-exclamation-triangle class="h-8 w-8" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Low Stock Items -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">⚠️ Low Stock Items</h3>
                @if(empty($lowStock))
                    <p class="text-sm text-gray-500 dark:text-gray-400">All items are well stocked</p>
                @else
                    <div class="space-y-3">
                        @foreach($lowStock as $item)
                            <div class="flex items-center justify-between p-3 rounded border border-{{ $item['status'] === 'out_of_stock' ? 'red' : 'orange' }}-200 dark:border-{{ $item['status'] === 'out_of_stock' ? 'red' : 'orange' }}-800 bg-{{ $item['status'] === 'out_of_stock' ? 'red' : 'orange' }}-50 dark:bg-{{ $item['status'] === 'out_of_stock' ? 'red' : 'orange' }}-900/20">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $item['name'] }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['sku'] }}</p>
                                </div>
                                <div class="ml-4 text-right">
                                    <p class="text-sm font-bold {{ $item['status'] === 'out_of_stock' ? 'text-red-600 dark:text-red-400' : 'text-orange-600 dark:text-orange-400' }}">
                                        {{ $item['current'] }}/{{ $item['reorder'] }}
                                    </p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">units</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Top Vendors -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">🏪 Top Vendors</h3>
                <div class="space-y-3">
                    @foreach($topVendors as $vendor)
                        <div class="flex items-center justify-between p-3 rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $vendor['name'] }}</p>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                                {{ $vendor['items_count'] }} items
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Fast Movers -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">🚀 High Value Items</h3>
                <div class="space-y-3">
                    @foreach($fastMovers as $item)
                        <div class="flex items-center justify-between p-3 rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $item['name'] }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['location'] }}</p>
                            </div>
                            <div class="ml-4 text-right">
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">${{ number_format($item['value'], 0) }}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['quantity'] }} units</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Dead Stock -->
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">💀 Dead Stock</h3>
                @if(empty($deadStock))
                    <p class="text-sm text-gray-500 dark:text-gray-400">No dead stock detected</p>
                @else
                    <div class="space-y-3">
                        @foreach($deadStock as $item)
                            <div class="flex items-center justify-between p-3 rounded border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $item['name'] }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['sku'] }}</p>
                                </div>
                                <div class="ml-4 text-right">
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">${{ number_format($item['value'], 2) }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['quantity'] }} units</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Location Health -->
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">📍 Location Health</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($locations as $location)
                    <div class="rounded border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800/50">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $location['name'] }}</p>
                        <div class="mt-3 space-y-2 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex justify-between">
                                <span>Total Units:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($location['total_units']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Unique Items:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $location['unique_items'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Value:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">${{ number_format($location['value'], 0) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">🔧 Quick Actions</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <a href="{{ route('filament.admin.pages.inventory-scanner') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 px-4 py-2 text-xs font-semibold text-white transition-colors">
                    <x-heroicon-o-video-camera class="h-4 w-4" />
                    <span class="hidden sm:inline">Scanner</span>
                </a>
                <a href="{{ route('filament.admin.resources.inventory-items.index') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 px-4 py-2 text-xs font-semibold text-white transition-colors">
                    <x-heroicon-o-archive-box class="h-4 w-4" />
                    <span class="hidden sm:inline">Items</span>
                </a>
                <a href="{{ route('filament.admin.resources.inventory-stocks.index') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-purple-600 hover:bg-purple-700 px-4 py-2 text-xs font-semibold text-white transition-colors">
                    <x-heroicon-o-chart-bar class="h-4 w-4" />
                    <span class="hidden sm:inline">Stock</span>
                </a>
                <a href="{{ route('filament.admin.pages.inventory-report') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-orange-600 hover:bg-orange-700 px-4 py-2 text-xs font-semibold text-white transition-colors">
                    <x-heroicon-o-document-text class="h-4 w-4" />
                    <span class="hidden sm:inline">Report</span>
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>

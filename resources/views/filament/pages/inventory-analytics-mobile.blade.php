@php
$summary = $this->getSummary();
$lowStock = $this->getLowStockItems();
$vendors = $this->getTopVendors();
$locations = $this->getLocationHealth();
$fastMovers = $this->getFastMovers();
$deadStock = $this->getDeadStock();
@endphp

<x-filament-panels::page>
    <!-- Loading skeleton -->
    <div wire:loading.delay>
        <x-skeleton-loader type="dashboard" :cards="3" />
    </div>

    <div wire:loading.delay.remove class="space-y-4">
        <!-- Header with export buttons -->
        <div class="flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Inventory Analytics</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Key metrics and health status</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('export.inventory-analytics-pdf') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 touch-action-manipulation"
                   download>
                    📄 PDF
                </a>
                <a href="{{ route('export.inventory-analytics-pdf') }}?download=1"
                   class="inline-flex items-center gap-2 px-3 py-2 bg-green-500 text-white rounded-lg text-sm font-medium hover:bg-green-600 touch-action-manipulation">
                    📊 Excel
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @php
            $metrics = [
                ['label' => 'Total Value', 'value' => '$' . number_format($summary['total_value'], 2), 'icon' => '💰'],
                ['label' => 'Units in Stock', 'value' => number_format($summary['total_units']), 'icon' => '📦'],
                ['label' => 'Active Items', 'value' => $summary['total_items'], 'icon' => '📋'],
                ['label' => 'Low Stock', 'value' => $summary['low_stock_count'], 'icon' => '⚠️'],
            ];
            @endphp

            @foreach($metrics as $metric)
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $metric['label'] }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $metric['value'] }}</p>
                    </div>
                    <span class="text-2xl">{{ $metric['icon'] }}</span>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Collapsible Sections for Better Mobile UX -->

        <!-- Low Stock Items -->
        <x-collapsible-section title="⚠️ Low Stock Items" :default-open="true">
            @if($lowStock)
            <div class="overflow-x-auto -webkit-overflow-scrolling-touch">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-2 px-2 font-semibold">Item</th>
                            <th class="text-right py-2 px-2 font-semibold">Current</th>
                            <th class="text-right py-2 px-2 font-semibold">Reorder</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lowStock as $item)
                        <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="py-3 px-2">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">{{ $item['sku'] }}</div>
                            </td>
                            <td class="text-right py-3 px-2">
                                <span class="inline-block px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $item['status'] === 'out_of_stock' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' }}">
                                    {{ $item['current'] }}
                                </span>
                            </td>
                            <td class="text-right py-3 px-2 text-gray-600 dark:text-gray-400">{{ $item['reorder'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-center text-gray-600 dark:text-gray-400 py-4">No low stock items</p>
            @endif
        </x-collapsible-section>

        <!-- Top Vendors -->
        <x-collapsible-section title="🏢 Top Vendors">
            @if($vendors)
            <div class="space-y-3">
                @foreach($vendors as $vendor)
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <span class="font-medium text-gray-900 dark:text-white">{{ $vendor['name'] }}</span>
                    <span class="text-sm bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-3 py-1 rounded-full font-semibold">
                        {{ $vendor['items_count'] }} items
                    </span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-600 dark:text-gray-400 py-4">No vendor data</p>
            @endif
        </x-collapsible-section>

        <!-- Fast Movers -->
        <x-collapsible-section title="🚀 High Value Items">
            @if($fastMovers)
            <div class="space-y-3">
                @foreach($fastMovers as $item)
                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['location'] }}</p>
                        </div>
                        <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                            ${{ number_format($item['value'], 2) }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">Qty: {{ $item['quantity'] }}</p>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-600 dark:text-gray-400 py-4">No data available</p>
            @endif
        </x-collapsible-section>

        <!-- Dead Stock -->
        <x-collapsible-section title="📉 Dead Stock">
            @if($deadStock)
            <div class="overflow-x-auto -webkit-overflow-scrolling-touch">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-2 px-2 font-semibold">Item</th>
                            <th class="text-right py-2 px-2 font-semibold">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deadStock as $item)
                        <tr class="border-b border-gray-100 dark:border-gray-700">
                            <td class="py-3 px-2">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">{{ $item['sku'] }}</div>
                            </td>
                            <td class="text-right py-3 px-2 text-red-600 dark:text-red-400">
                                ${{ number_format($item['value'], 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-center text-gray-600 dark:text-gray-400 py-4">No dead stock</p>
            @endif
        </x-collapsible-section>

        <!-- Location Health -->
        <x-collapsible-section title="🏪 Location Health">
            @if($locations)
            <div class="space-y-3">
                @foreach($locations as $location)
                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <div class="flex justify-between items-start mb-2">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $location['name'] }}</p>
                        <span class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                            ${{ number_format($location['value'], 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                        <span>{{ $location['total_units'] }} units</span>
                        <span>{{ $location['unique_items'] }} items</span>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-center text-gray-600 dark:text-gray-400 py-4">No location data</p>
            @endif
        </x-collapsible-section>

        <!-- Quick Actions -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mt-6">
            <p class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-3">Quick Actions</p>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('filament.admin.resources.inventory-items.index') }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    📋 View Items
                </a>
                <a href="{{ route('filament.admin.resources.inventory-locations.index') }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    🏪 Manage Locations
                </a>
                <a href="{{ route('filament.admin.pages.mobile-scanner-app') }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    📱 Scanner
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>

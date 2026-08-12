<div class="space-y-6">
    {{-- Key Metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-900/10 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium uppercase tracking-wide">Total Value</p>
            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-2">${{ number_format((float) $snapshot->total_value, 2) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $snapshot->total_items }} SKUs • {{ number_format($snapshot->total_quantity) }} units</p>
        </div>

        <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-900/10 rounded-lg p-6 border border-green-200 dark:border-green-800">
            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium uppercase tracking-wide">Healthy Stock</p>
            <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $healthyCount }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ number_format(($healthyCount / max(1, $snapshot->total_items)) * 100, 0) }}% of items</p>
        </div>

        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-900/10 rounded-lg p-6 border border-yellow-200 dark:border-yellow-800">
            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium uppercase tracking-wide">Low Stock</p>
            <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mt-2">{{ $lowStockCount }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Need attention soon</p>
        </div>

        <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-900/10 rounded-lg p-6 border border-red-200 dark:border-red-800">
            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium uppercase tracking-wide">Out of Stock</p>
            <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-2">{{ $outOfStockCount }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Order immediately</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex flex-wrap gap-3 items-center">
            <select
                wire:model.live="filterCategory"
                class="px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm"
            >
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>

            <select
                wire:model.live="filterLocation"
                class="px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg text-sm"
            >
                <option value="">All Locations</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>

            <label class="flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg cursor-pointer">
                <input
                    type="checkbox"
                    wire:model.live="lowStockOnly"
                    class="rounded"
                >
                <span class="text-sm text-gray-900 dark:text-white">Low Stock Only</span>
            </label>
        </div>
    </div>

    {{-- Items Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white uppercase">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 dark:text-white uppercase">Category</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white uppercase">Quantity</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white uppercase">Avg Cost</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-900 dark:text-white uppercase">Total Value</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-gray-900 dark:text-white uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($items as $item)
                        @php
                            $qty = $item->stock_sum_quantity ?? 0;
                            $cost = $item->average_cost ?? 0;
                            $value = $qty * $cost;
                            $reorder = $item->reorder_level ?? 0;

                            if ($qty <= 0) {
                                $status = ['label' => 'Out of Stock', 'color' => 'red'];
                            } elseif ($qty <= $reorder) {
                                $status = ['label' => 'Low Stock', 'color' => 'yellow'];
                            } else {
                                $status = ['label' => 'Healthy', 'color' => 'green'];
                            }
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-6 py-4 text-sm font-mono font-semibold text-gray-900 dark:text-white">{{ $item->sku }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                <a href="{{ route('filament.admin.resources.inventory-items.edit', $item) }}" class="hover:underline">
                                    {{ $item->name }}
                                </a>
                                @if($item->description)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $item->description }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($item->category)
                                    <span class="inline-block px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-xs font-medium">
                                        {{ $item->category }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900 dark:text-white">
                                {{ number_format($qty) }}
                                @if($reorder > 0)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Reorder: {{ $reorder }}</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-right text-gray-700 dark:text-gray-300">${{ number_format($cost, 4) }}</td>
                            <td class="px-6 py-4 text-sm text-right font-semibold text-gray-900 dark:text-white">${{ number_format($value, 2) }}</td>
                            <td class="px-6 py-4 text-center">
                                <span @class([
                                    'inline-block px-3 py-1 rounded-full text-xs font-medium',
                                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' => $status['color'] === 'green',
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' => $status['color'] === 'yellow',
                                    'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' => $status['color'] === 'red',
                                ])>
                                    {{ $status['label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <p class="text-gray-500 dark:text-gray-400">No items found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Location Breakdown --}}
    @if($snapshot->location_breakdown && count($snapshot->location_breakdown) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">By Location</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($snapshot->location_breakdown as $locId => $location)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $location['name'] }}</p>
                        <p class="text-3xl font-bold text-primary-600 mt-2">${{ number_format($location['value'], 2) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ number_format($location['quantity']) }} units</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Last Updated --}}
    <div class="text-center text-xs text-gray-500 dark:text-gray-400">
        Last updated: {{ $snapshot->snapshot_date->format('M d, Y g:i A') }}
    </div>
</div>

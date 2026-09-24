<x-filament-panels::page>
    @php
        $data = $this->getViewerData();
        $snapshot = (object) ['snapshot_date' => now(), 'total_value' => $data['summary']['value'], 'total_items' => $data['summary']['items'], 'total_quantity' => $data['summary']['quantity']];
        $trendData = $data['trend'];
        $itemDetails = $data['items'];
        $stocks = collect($data['items']);
    @endphp

    <div class="space-y-6">

        {{-- Export Button --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Inventory Report</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Live in-app inventory reporting • Updated {{ now()->format('M d, Y g:i A') }}</p>
            </div>
            <button type="button"
                    wire:click="exportPdf"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition-colors">
                <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                Export Report
            </button>
        </div>

        {{-- Key Metrics Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
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
                <div class="space-y-4">
                    <div class="flex items-end justify-between h-32 gap-1">
                        @php
                            $maxValue = $trendData->max('value') ?: 1;
                            $minValue = $trendData->min('value') ?: 0;
                            $range = $maxValue - $minValue ?: 1;
                        @endphp
                        @foreach ($trendData as $trend)
                            @php
                                $height = (($trend['value'] - $minValue) / $range) * 100;
                            @endphp
                            <div class="flex-1 flex flex-col items-center justify-end">
                                <div class="w-full bg-primary-500 rounded-t" style="height: {{ max(5, $height) }}%"></div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $trend['date'] }}</p>
                            </div>
                        @endforeach
                    </div>
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

        {{-- Breakdown by Type --}}
        @if ($snapshot->item_type_breakdown)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Inventory by Type</h3>
                <div class="space-y-3">
                    @foreach ($snapshot->item_type_breakdown as $type => $data)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ ucfirst(str_replace('_', ' ', $type)) }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($data['quantity']) }} units</p>
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($data['value'], 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Slow-Moving Items --}}
        @if ($snapshot->slow_moving_items && count($snapshot->slow_moving_items) > 0)
            <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-6">
                <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-100 mb-4">⏸️ Slow-Moving Items (30+ days)</h3>
                <div class="space-y-2">
                    @foreach ($snapshot->slow_moving_items as $item)
                        <div class="flex items-center justify-between p-2 text-sm">
                            <span class="text-amber-900 dark:text-amber-100">{{ $item['name'] }}</span>
                            <span class="text-amber-700 dark:text-amber-300">{{ $item['quantity'] }} units • ${{ number_format($item['value'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Stock Outs --}}
        @if ($snapshot->stock_outs && count($snapshot->stock_outs) > 0)
            <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-6">
                <h3 class="text-sm font-semibold text-red-900 dark:text-red-100 mb-4">🔴 Out of Stock</h3>
                <div class="space-y-2">
                    @foreach ($snapshot->stock_outs as $item)
                        <div class="flex items-center justify-between p-2 text-sm">
                            <span class="text-red-900 dark:text-red-100">{{ $item['name'] }}</span>
                            <span class="text-xs text-red-700 dark:text-red-300 font-mono">{{ $item['sku'] ?? 'N/A' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Detailed Item List --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Inventory Details</h3>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <div class="hidden md:grid grid-cols-[minmax(0,2fr)_minmax(0,1.1fr)_90px_110px_120px] gap-3 bg-gray-50 dark:bg-gray-800/50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <span>Item</span><span>Location</span><span class="text-right">Qty</span><span class="text-right">Avg Cost</span><span class="text-right">Value</span>
                </div>
                @forelse ($itemDetails as $item)
                    <div class="grid gap-3 px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 md:grid-cols-[minmax(0,2fr)_minmax(0,1.1fr)_90px_110px_120px] md:items-center {{ $item['is_low_stock'] ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                        <div class="min-w-0"><div class="font-semibold text-gray-900 dark:text-gray-100">{{ $item['name'] }}</div><div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item['sku'] }}</div></div>
                        <div class="text-sm text-gray-600 dark:text-gray-300">{{ $item['location'] }}</div>
                        <div class="flex justify-between md:block md:text-right"><span class="text-xs text-gray-500 md:hidden">Qty</span><span class="font-semibold">{{ number_format($item['quantity']) }} @if ($item['is_low_stock'])<span class="text-amber-500">⚠</span>@endif</span></div>
                        <div class="flex justify-between md:block md:text-right"><span class="text-xs text-gray-500 md:hidden">Avg Cost</span><span>${{ number_format($item['unit_cost'], 2) }}</span></div>
                        <div class="flex justify-between md:block md:text-right"><span class="text-xs text-gray-500 md:hidden">Value</span><span class="font-bold text-gray-900 dark:text-gray-100">${{ number_format($item['total_value'], 2) }}</span></div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No inventory currently in stock.</div>
                @endforelse
            </div>
        </div>

    </div>
</x-filament-panels::page>

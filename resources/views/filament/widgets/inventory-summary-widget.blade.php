<x-filament-widgets::widget>
    @php
        $data = $this->getInventoryData();
        $reportUrl = $this->getReportUrl();
    @endphp

    <div class="space-y-4">
        @if ($data['snapshot_date'])
            <div class="text-xs text-gray-500 dark:text-gray-400">
                As of {{ $data['snapshot_date']->format('M j, Y g:i A') }}
            </div>
        @endif

        {{-- Key Metrics Row --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Value --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                    Inventory Value
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                    ${{ number_format($data['total_value'], 0) }}
                </div>
                @if ($data['value_diff'] !== null)
                    <div class="mt-1 text-xs font-medium {{ $data['value_diff'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $data['value_diff'] > 0 ? '↑' : '↓' }}
                        ${{ number_format(abs($data['value_diff']), 0) }}
                        ({{ number_format($data['value_diff_pct'], 1) }}%)
                    </div>
                @endif
            </div>

            {{-- SKU Count --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                    SKUs
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ $data['total_items'] }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    unique items
                </div>
            </div>

            {{-- Units --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-900/30 dark:to-amber-800/30 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                    Units
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ number_format($data['total_quantity']) }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    total quantity
                </div>
            </div>

            {{-- Alerts --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/30 dark:to-orange-900/30 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">
                    Alerts
                </div>
                <div class="mt-2 space-y-1">
                    @if ($data['stock_outs_count'] > 0)
                        <div class="text-sm font-semibold text-red-600 dark:text-red-400">
                            {{ $data['stock_outs_count'] }} out of stock
                        </div>
                    @else
                        <div class="text-sm text-green-600 dark:text-green-400">
                            ✓ All in stock
                        </div>
                    @endif
                    @if ($data['slow_moving_count'] > 0)
                        <div class="text-xs text-amber-600 dark:text-amber-400">
                            {{ $data['slow_moving_count'] }} slow-moving
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Breakdown Tables --}}
        @if (!empty($data['locations']) || !empty($data['types']))
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- By Location --}}
                @if (!empty($data['locations']))
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">By Location</h3>
                        <div class="space-y-2">
                            @foreach ($data['locations'] as $locId => $location)
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $location['name'] ?? "Location $locId" }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ number_format($location['quantity'] ?? 0) }} units
                                        </div>
                                    </div>
                                    <div class="text-right font-semibold text-gray-900 dark:text-gray-100">
                                        ${{ number_format($location['value'] ?? 0, 0) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- By Type --}}
                @if (!empty($data['types']))
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">By Type</h3>
                        <div class="space-y-2">
                            @foreach ($data['types'] as $type => $breakdown)
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $type }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ number_format($breakdown['quantity'] ?? 0) }} units
                                        </div>
                                    </div>
                                    <div class="text-right font-semibold text-gray-900 dark:text-gray-100">
                                        ${{ number_format($breakdown['value'] ?? 0, 0) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- View Full Report Link --}}
        <div class="pt-2">
            <a href="{{ $reportUrl }}"
               class="inline-flex items-center gap-2 rounded-lg bg-primary-50 dark:bg-primary-900/30 px-4 py-2.5 text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-800/50 transition-colors">
                <x-heroicon-o-chart-bar class="h-4 w-4" />
                View Full Report
            </a>
        </div>
    </div>
</x-filament-widgets::widget>

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- KEY METRICS OVERVIEW                                               --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            {{-- Pending Pallets --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-950 dark:to-amber-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Pending</p>
                        <p class="text-3xl font-bold text-amber-900 dark:text-amber-100 mt-2">{{ $this->getPalletStatsProperty()['pending'] }}</p>
                    </div>
                    <x-heroicon-o-clock class="h-10 w-10 text-amber-200 dark:text-amber-800 opacity-50" />
                </div>
            </div>

            {{-- Shipped Pallets --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-950 dark:to-blue-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600 dark:text-blue-400">In Transit</p>
                        <p class="text-3xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ $this->getPalletStatsProperty()['shipped'] }}</p>
                    </div>
                    <x-heroicon-o-truck class="h-10 w-10 text-blue-200 dark:text-blue-800 opacity-50" />
                </div>
            </div>

            {{-- Currently Receiving --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-violet-50 to-violet-100 dark:from-violet-950 dark:to-violet-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-violet-600 dark:text-violet-400">Receiving</p>
                        <p class="text-3xl font-bold text-violet-900 dark:text-violet-100 mt-2">{{ $this->getPalletStatsProperty()['receiving'] }}</p>
                    </div>
                    <x-heroicon-o-arrow-path class="h-10 w-10 text-violet-200 dark:text-violet-800 opacity-50" />
                </div>
            </div>

            {{-- Received --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-green-50 to-green-100 dark:from-green-950 dark:to-green-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-600 dark:text-green-400">Received</p>
                        <p class="text-3xl font-bold text-green-900 dark:text-green-100 mt-2">{{ $this->getPalletStatsProperty()['received'] }}</p>
                    </div>
                    <x-heroicon-o-check-circle class="h-10 w-10 text-green-200 dark:text-green-800 opacity-50" />
                </div>
            </div>

            {{-- Total Inventory Value --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-950 dark:to-purple-900 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-600 dark:text-purple-400">Total Value</p>
                        <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-2">${{ number_format($this->getPalletStatsProperty()['total_value'], 2) }}</p>
                    </div>
                    <x-heroicon-o-banknotes class="h-10 w-10 text-purple-200 dark:text-purple-800 opacity-50" />
                </div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- PENDING PALLETS (AWAITING SHIPMENT)                                 --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if(!empty($this->getPendingPalletsProperty()))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-clock class="h-5 w-5 text-amber-600" />
                Pending Orders (Awaiting Shipment)
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Pallet / PO</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Vendor</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Expected Delivery</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Days Remaining</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Tracking</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getPendingPalletsProperty() as $pallet)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">{{ $pallet['reference'] }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $pallet['vendor'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $pallet['expected_date'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-right">
                                @if($pallet['days_until'] !== null)
                                    @if($pallet['days_until'] > 0)
                                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $pallet['days_until'] }} days</span>
                                    @elseif($pallet['days_until'] == 0)
                                        <span class="text-sm font-semibold text-red-600">Arriving Today!</span>
                                    @else
                                        <span class="text-sm font-semibold text-orange-600">{{ abs($pallet['days_until']) }} days overdue</span>
                                    @endif
                                @else
                                    <span class="text-sm text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-600 dark:text-gray-400">
                                {{ $pallet['carrier'] ? $pallet['carrier'] . ' • ' : '' }}{{ $pallet['tracking_number'] ?? '—' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- IN TRANSIT PALLETS                                                  --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if(!empty($this->getShippedPalletsProperty()))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-truck class="h-5 w-5 text-blue-600" />
                In Transit Pallets
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Pallet / PO</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Vendor</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Carrier & Tracking</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Expected Arrival</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">In Transit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getShippedPalletsProperty() as $pallet)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">{{ $pallet['reference'] }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $pallet['vendor'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-xs text-gray-600 dark:text-gray-400">
                                {{ $pallet['carrier'] ? $pallet['carrier'] . ' • ' : '' }}<span class="font-mono">{{ $pallet['tracking_number'] ?? '—' }}</span>
                            </td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $pallet['expected_date'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-gray-100 font-semibold">{{ $pallet['in_transit_days'] }} days</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- CURRENTLY RECEIVING                                                 --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if(!empty($this->getReceivingPalletsProperty()))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-arrow-path class="h-5 w-5 text-violet-600" />
                Currently Receiving
            </h2>
            <div class="space-y-4">
                @foreach($this->getReceivingPalletsProperty() as $pallet)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $pallet['reference'] }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $pallet['vendor'] ?? '—' }} • Started {{ $pallet['started_at'] }}</p>
                        </div>
                        <span class="text-sm font-semibold text-violet-600 dark:text-violet-400">{{ $pallet['progress'] }}% Complete</span>
                    </div>
                    <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-3">
                        <div class="bg-violet-600 h-2 rounded-full transition-all" style="width: {{ $pallet['progress'] }}%"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Lines</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $pallet['lines_received'] }}/{{ $pallet['total_lines'] }}</p>
                        </div>
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">Cases</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ $pallet['received_cases'] }}/{{ $pallet['total_cases'] }}</p>
                        </div>
                        <div class="text-right">
                            <a href="{{ route('filament.admin.resources.pallets.index') }}" class="text-violet-600 dark:text-violet-400 hover:underline">Continue →</a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- RECENTLY RECEIVED                                                   --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if(!empty($this->getReceivedPalletsProperty()))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-check-circle class="h-5 w-5 text-green-600" />
                Recently Received
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Pallet / PO</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Vendor</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Received Date</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Items / Cases</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getReceivedPalletsProperty() as $pallet)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-4 text-gray-900 dark:text-gray-100 font-medium">{{ $pallet['reference'] }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $pallet['vendor'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $pallet['received_date'] ?? '—' }}</td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-gray-100">{{ $pallet['total_items'] }} / {{ $pallet['total_cases'] }}</td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-gray-100 font-semibold">{{ $pallet['total_value'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</x-filament-panels::page>

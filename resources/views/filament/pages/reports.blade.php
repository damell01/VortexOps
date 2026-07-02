<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Period selector --}}
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Period:</span>
            @foreach ($this->getPeriodOptions() as $days => $label)
                <button
                    wire:click="setPeriod('{{ $days }}')"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition
                        {{ $period === (string) $days
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Revenue KPI tiles --}}
        @php $rev = $this->revenueSummary; @endphp
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                ['label' => 'Shows',       'value' => number_format($rev['shows']),               'color' => 'violet',   'icon' => 'heroicon-o-video-camera'],
                ['label' => 'Units Sold',  'value' => number_format($rev['units']),               'color' => 'sky',      'icon' => 'heroicon-o-shopping-bag'],
                ['label' => 'Gross Rev',   'value' => '$' . number_format($rev['gross'], 0),      'color' => 'emerald',  'icon' => 'heroicon-o-banknotes'],
                ['label' => 'Whatnot Net', 'value' => '$' . number_format($rev['net'], 0),        'color' => 'green',    'icon' => 'heroicon-o-arrow-trending-up'],
                ['label' => 'Tips',        'value' => '$' . number_format($rev['tips'], 0),       'color' => 'amber',    'icon' => 'heroicon-o-star'],
                ['label' => 'Paper Sales', 'value' => '$' . number_format($rev['paper'], 0),      'color' => 'rose',     'icon' => 'heroicon-o-document-text'],
            ] as $tile)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <x-dynamic-component :component="$tile['icon']" class="h-4 w-4" />
                        {{ $tile['label'] }}
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ $tile['value'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Revenue by channel --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Revenue by Channel</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3 text-right">Shows</th>
                            <th class="px-5 py-3 text-right">Units</th>
                            <th class="px-5 py-3 text-right">Gross Revenue</th>
                            <th class="px-5 py-3 text-right">Whatnot Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($this->revenueByChannel as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $row['channel'] }}</td>
                                <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($row['shows']) }}</td>
                                <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($row['units']) }}</td>
                                <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-white">${{ number_format($row['gross'], 2) }}</td>
                                <td class="px-5 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($row['net'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No shows in this period</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

            {{-- Revenue by week --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Revenue by Week</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                <th class="px-4 py-3">Week of</th>
                                <th class="px-4 py-3 text-right">Shows</th>
                                <th class="px-4 py-3 text-right">Gross</th>
                                <th class="px-4 py-3 text-right">Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($this->revenueByWeek as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $row['week'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $row['shows'] }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">${{ number_format($row['gross'], 0) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($row['net'], 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Top streamers --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Top Streamers by Payout</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                <th class="px-4 py-3">Streamer</th>
                                <th class="px-4 py-3 text-right">Shows</th>
                                <th class="px-4 py-3 text-right">Total Paid</th>
                                <th class="px-4 py-3 text-right">Avg/Show</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($this->topStreamersByPayout as $i => $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        <span class="mr-2 text-xs text-gray-400">#{{ $i + 1 }}</span>
                                        {{ $row['streamer'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $row['shows'] }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-violet-600 dark:text-violet-400">${{ number_format($row['total'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">${{ number_format($row['avg'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No payouts in this period</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Payout status summary --}}
        @php $ps = $this->payoutStatusSummary; @endphp
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Payout Pipeline</h3>
            </div>
            <div class="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-700">
                @foreach ([
                    ['key' => 'draft',    'label' => 'Draft',    'color' => 'gray'],
                    ['key' => 'approved', 'label' => 'Approved', 'color' => 'sky'],
                    ['key' => 'paid',     'label' => 'Paid',     'color' => 'emerald'],
                ] as $col)
                    <div class="px-5 py-5 text-center">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $col['label'] }}</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($ps[$col['key']]['count']) }}</div>
                        <div class="mt-0.5 text-sm text-{{ $col['color'] }}-600 dark:text-{{ $col['color'] }}-400">
                            ${{ number_format($ps[$col['key']]['total'], 2) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</x-filament-panels::page>

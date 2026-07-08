<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Controls --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 print:hidden">
            <div class="flex flex-col sm:flex-row sm:items-end gap-4 flex-wrap">

                {{-- Mode toggle --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date Range</label>
                    <div class="flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden text-sm">
                        <button wire:click="$set('dateMode','month')" type="button"
                            class="px-3 py-2 transition-colors {{ $dateMode === 'month' ? 'bg-primary-600 text-white font-medium' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                            Month
                        </button>
                        <button wire:click="$set('dateMode','custom')" type="button"
                            class="px-3 py-2 border-l border-gray-300 dark:border-gray-600 transition-colors {{ $dateMode === 'custom' ? 'bg-primary-600 text-white font-medium' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                            Custom
                        </button>
                    </div>
                </div>

                {{-- Month picker --}}
                @if($dateMode === 'month')
                    <div class="max-w-xs">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Month</label>
                        <input wire:model.live="month" type="month"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                    </div>
                @else
                    {{-- Custom date range --}}
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">From</label>
                            <input wire:model.live="dateFrom" type="date"
                                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">To</label>
                            <input wire:model.live="dateTo" type="date"
                                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        </div>
                    </div>
                @endif

                {{-- Print --}}
                <div class="sm:ml-auto">
                    <button x-on:click="window.print()" type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors">
                        <x-heroicon-o-printer class="h-4 w-4" />
                        Print Packet
                    </button>
                </div>
            </div>
        </div>

        @php $rows = $this->packetData; @endphp

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Profit Share Packet — {{ $this->dateRangeLabel }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Profit share and hybrid streamers only</p>
            </div>

            @if(empty($rows))
                <div class="px-6 py-10 text-center text-sm text-gray-400">No profit share streamers with shows in this month.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Streamer</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Shows</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Gross Rev</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">PS %</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">PS Earned</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Paid</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($rows as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['shows'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($row['gross_rev'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['ps_pct'], 1) }}%</td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($row['ps_earned'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($row['paid'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $row['balance'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        ${{ number_format(abs($row['balance']), 2) }}
                                        @if($row['balance'] < 0) <span class="text-xs font-normal">(overpaid)</span> @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 font-semibold">
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">Totals</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ collect($rows)->sum('shows') }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format(collect($rows)->sum('gross_rev'), 2) }}</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format(collect($rows)->sum('ps_earned'), 2) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format(collect($rows)->sum('paid'), 2) }}</td>
                                <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">${{ number_format(collect($rows)->sum('balance'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>

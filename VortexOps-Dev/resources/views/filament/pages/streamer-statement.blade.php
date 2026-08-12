<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Controls --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Streamer</label>
                    @if($this->isSelfService)
                        <div class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                            {{ $this->selectedStreamer?->name ?? 'Your profile' }}
                        </div>
                    @else
                        <select
                            wire:model.live="streamerId"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                            <option value="">— Select a streamer —</option>
                            @foreach($this->streamersList as $streamer)
                                <option value="{{ $streamer->id }}">{{ $streamer->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
                    <input wire:model.live="dateFrom" type="date"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
                    <input wire:model.live="dateTo" type="date"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                </div>
            </div>
        </div>

        @if($streamerId)
            @php $data = $this->statementData; @endphp

            {{-- Statement Header --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden" id="printable-statement">

                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            Statement: {{ $this->selectedStreamer?->name }}
                        </h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $dateFrom }} — {{ $dateTo }}</p>
                    </div>
                    <button
                        x-on:click="window.print()"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors print:hidden">
                        <x-heroicon-o-printer class="h-4 w-4" />
                        Print
                    </button>
                </div>

                @if(empty($data['shows']))
                    <div class="px-6 py-10 text-center text-sm text-gray-400">No shows found for this period.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Show</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Gross</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Payout Type</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Calculated</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Surcharge</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Net Payout</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Paid</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($data['shows'] as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['show_date'] }}</td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100 max-w-[200px] truncate">{{ $row['title'] }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($row['gross'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400 text-xs">{{ $row['payout_type'] }}</td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($row['calculated'], 2) }}</td>
                                        <td class="px-4 py-3 text-right {{ $row['surcharge'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                                            {{ $row['surcharge'] > 0 ? '-$' . number_format($row['surcharge'], 2) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($row['net_payout'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($row['paid'], 2) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $row['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : ($row['status'] === 'approved' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300') }}">
                                                {{ ucfirst($row['status']) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 font-semibold">
                                    <td colspan="2" class="px-4 py-3 text-gray-900 dark:text-gray-100">Totals</td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format($data['totals']['gross'], 2) }}</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format($data['totals']['due'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">
                                        {{ $data['totals']['surcharge'] > 0 ? '-$' . number_format($data['totals']['surcharge'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format($data['totals']['due'] - $data['totals']['surcharge'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">${{ number_format($data['totals']['paid'], 2) }}</td>
                                    <td class="px-4 py-3"></td>
                                </tr>
                                <tr class="bg-amber-50 dark:bg-amber-950/30">
                                    <td colspan="6" class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-300">Outstanding Balance</td>
                                    <td colspan="3" class="px-4 py-3 text-right text-lg font-bold {{ $data['totals']['outstanding'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        ${{ number_format($data['totals']['outstanding'], 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

            </div>
        @else
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-10 text-center text-sm text-gray-400">
                Select a streamer above to generate a statement.
            </div>
        @endif

    </div>
</x-filament-panels::page>

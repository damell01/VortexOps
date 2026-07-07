<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filters --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
                    <input
                        wire:model.live="dateFrom"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
                    <input
                        wire:model.live="dateTo"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Streamers</label>
                    <select
                        wire:model.live="selectedStreamers"
                        multiple
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        @foreach($this->streamersList as $streamer)
                            <option value="{{ $streamer->id }}">{{ $streamer->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple</p>
                </div>
            </div>
        </div>

        {{-- Summary Tiles --}}
        @php
            $rows        = $this->analyticsRows;
            $totalShows  = collect($rows)->sum('show_count');
            $totalGross  = collect($rows)->sum('gross_revenue');
            $avgSph      = collect($rows)->avg('gmv_per_hour') ?? 0;
            $totalPayout = collect($rows)->sum('total_payout');
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Shows</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($totalShows) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Gross</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">${{ number_format($totalGross, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Avg SPH</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">${{ number_format($avgSph, 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Payouts</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">${{ number_format($totalPayout, 2) }}</p>
            </div>
        </div>

        {{-- Analytics Table --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Streamer Performance</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $dateFrom }} — {{ $dateTo }} · Sorted by GMV/hr desc</p>
            </div>

            @if(empty($rows))
                <div class="px-6 py-10 text-center text-sm text-gray-400">No data for the selected period.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Streamer</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Shows</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Hours</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Gross Rev</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Net Rev</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">GMV/hr (SPH)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">AOV</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Payout</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Business Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($rows as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('filament.admin.resources.streamers.edit', $row['streamer_id']) }}"
                                           class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                                            {{ $row['name'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['show_count'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['total_hours'], 1) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($row['gross_revenue'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($row['net_revenue'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">${{ number_format($row['gmv_per_hour'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($row['aov'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">${{ number_format($row['total_payout'], 2) }}</td>
                                    <td class="px-4 py-3 text-right {{ $row['business_net'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">${{ number_format($row['business_net'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 font-semibold">
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">Totals</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($totalShows) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format(collect($rows)->sum('total_hours'), 1) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format($totalGross, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format(collect($rows)->sum('net_revenue'), 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">—</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">—</td>
                                <td class="px-4 py-3 text-right text-blue-700 dark:text-blue-300">${{ number_format($totalPayout, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format(collect($rows)->sum('business_net'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>

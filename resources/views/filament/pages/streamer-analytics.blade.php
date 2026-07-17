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
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Streamers (overview)</label>
                    <select
                        wire:model.live="selectedStreamers"
                        multiple
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors h-20">
                        @foreach($this->streamersList as $streamer)
                            <option value="{{ $streamer->id }}">{{ $streamer->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Leave blank for all · Ctrl/Cmd to multi-select</p>
                </div>
            </div>
            <div class="mt-3 flex justify-end">
                <a
                    wire:click.prevent="exportCsv"
                    href="#"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition"
                >
                    <x-heroicon-o-arrow-down-tray class="h-3.5 w-3.5" />
                    Export CSV
                </a>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
            <button
                wire:click="$set('activeTab', 'overview')"
                class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors {{ $activeTab === 'overview' ? 'text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400 bg-white dark:bg-gray-900' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                Streamer Overview
            </button>
            <button
                wire:click="$set('activeTab', 'weekly')"
                class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors {{ $activeTab === 'weekly' ? 'text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400 bg-white dark:bg-gray-900' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                Weekly Breakdown
            </button>
        </div>

        @if($activeTab === 'overview')

        {{-- Summary Tiles --}}
        @php
            $rows        = $this->analyticsRows;
            $totalShows  = collect($rows)->sum('show_count');
            $totalGross  = collect($rows)->sum('gross_revenue');
            $totalNet    = collect($rows)->sum('net_revenue');
            $totalMargin = collect($rows)->sum('margin');
            $totalPayout = collect($rows)->sum('total_payout');
            $totalBal    = collect($rows)->sum('balance');
            $totalHrs    = collect($rows)->sum('total_hours');
            $avgSph      = $totalHrs > 0 ? round($totalGross / $totalHrs, 2) : 0;
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Shows</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($totalShows) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Gross</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">${{ number_format($totalGross, 2) }}</p>
                @if($totalNet > 0)
                    <p class="text-xs text-gray-400 mt-0.5">Net: ${{ number_format($totalNet, 2) }}</p>
                @endif
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Avg SPH</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">${{ number_format($avgSph, 2) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ number_format($totalHrs, 1) }}h logged</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Payouts</p>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">${{ number_format($totalPayout, 2) }}</p>
                @if($totalBal != 0)
                    <p class="text-xs {{ $totalBal > 0 ? 'text-amber-500' : 'text-gray-400' }} mt-0.5">
                        Balance: ${{ number_format(abs($totalBal), 2) }} {{ $totalBal > 0 ? 'owed' : 'overpaid' }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Streamer Performance Table --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Streamer Performance</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $dateFrom }} — {{ $dateTo }} · Ranked by SPH</p>
            </div>

            @if(empty($rows))
                <div class="px-6 py-10 text-center text-sm text-gray-400">No data for the selected period.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Streamer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Shows</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Hours</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Gross</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Net</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide cursor-help" title="Revenue minus COGS from sold items with a unit cost recorded.">Margin</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">SPH</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tips</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Payout</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($rows as $i => $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors {{ $i === 0 ? 'bg-emerald-50/50 dark:bg-emerald-950/20' : '' }}">
                                    <td class="px-4 py-3 text-gray-400 dark:text-gray-500 font-mono text-xs">
                                        @if($i === 0)
                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 font-bold text-[10px]">1</span>
                                        @else
                                            {{ $i + 1 }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        <a href="{{ route('filament.admin.resources.streamers.edit', $row['streamer_id']) }}"
                                           class="hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                                            {{ $row['name'] }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                            {{ $row['payout_type'] ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['show_count'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ $row['total_hours'] > 0 ? number_format($row['total_hours'], 1) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        ${{ number_format($row['gross_revenue'], 2) }}
                                        @if ($row['trend_gross'] !== null)
                                            <div class="text-[10px] font-medium {{ $row['trend_gross'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($row['trend_gross'] < 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400') }}">
                                                {{ $row['trend_gross'] > 0 ? '↑' : ($row['trend_gross'] < 0 ? '↓' : '→') }} {{ abs($row['trend_gross']) }}% <span class="font-normal text-gray-400">vs prior</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">
                                        {{ $row['net_revenue'] > 0 ? '$' . number_format($row['net_revenue'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $row['margin'] >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-rose-600 dark:text-rose-400' }}">
                                        ${{ number_format($row['margin'], 2) }}
                                        @if (! is_null($row['margin_pct']))
                                            <div class="text-[10px] font-normal text-gray-400">{{ $row['margin_pct'] }}%</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $row['gmv_per_hour'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                        {{ $row['gmv_per_hour'] > 0 ? '$' . number_format($row['gmv_per_hour'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">
                                        {{ $row['tips'] > 0 ? '$' . number_format($row['tips'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">${{ number_format($row['total_payout'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-medium {{ $row['balance'] > 0 ? 'text-amber-600 dark:text-amber-400' : ($row['balance'] < 0 ? 'text-red-500' : 'text-gray-400') }}">
                                        @if($row['balance'] != 0)
                                            {{ $row['balance'] > 0 ? '+' : '' }}${{ number_format(abs($row['balance']), 2) }}
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 font-semibold">
                                <td class="px-4 py-3" colspan="3">Totals</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($totalShows) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($totalHrs, 1) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format($totalGross, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format($totalNet, 2) }}</td>
                                <td class="px-4 py-3 text-right {{ $totalMargin >= 0 ? 'text-indigo-700 dark:text-indigo-300' : 'text-rose-600 dark:text-rose-400' }}">${{ number_format($totalMargin, 2) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-700 dark:text-emerald-300">${{ number_format($avgSph, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format(collect($rows)->sum('tips'), 2) }}</td>
                                <td class="px-4 py-3 text-right text-blue-700 dark:text-blue-300">${{ number_format($totalPayout, 2) }}</td>
                                <td class="px-4 py-3 text-right {{ $totalBal > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }}">
                                    ${{ number_format(abs($totalBal), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        @elseif($activeTab === 'weekly')

        {{-- Weekly breakdown --}}
        @php $weeklyRows = $this->weeklyRows; @endphp

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Weeks</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ count($weeklyRows) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Shows</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ collect($weeklyRows)->sum('show_count') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Gross</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">${{ number_format(collect($weeklyRows)->sum('gross'), 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Best Week SPH</p>
                @php $bestWeek = collect($weeklyRows)->sortByDesc('sph')->first(); @endphp
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ $bestWeek ? '$' . number_format($bestWeek['sph'], 2) : '—' }}
                </p>
                @if($bestWeek)
                    <p class="text-xs text-gray-400 mt-0.5">Week of {{ $bestWeek['week'] }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Weekly Show Performance</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $dateFrom }} — {{ $dateTo }} · SPH = Gross Revenue ÷ Hours Streamed</p>
            </div>

            @if(empty($weeklyRows))
                <div class="px-6 py-10 text-center text-sm text-gray-400">No shows in the selected date range.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Week Of</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Shows</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Hours</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Gross Revenue</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Whatnot Net</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">SPH</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($weeklyRows as $row)
                                @php
                                    $isBest = $bestWeek && $row['week'] === $bestWeek['week'];
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors {{ $isBest ? 'bg-emerald-50/50 dark:bg-emerald-950/20' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $row['week'] }}
                                        @if($isBest)
                                            <span class="ml-1.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">best</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['show_count'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ $row['hours'] > 0 ? number_format($row['hours'], 1) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($row['gross'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">
                                        {{ $row['net'] > 0 ? '$' . number_format($row['net'], 2) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $row['sph'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                        {{ $row['sph'] > 0 ? '$' . number_format($row['sph'], 2) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 font-semibold">
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">Totals</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ collect($weeklyRows)->sum('show_count') }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format(collect($weeklyRows)->sum('hours'), 1) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format(collect($weeklyRows)->sum('gross'), 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">${{ number_format(collect($weeklyRows)->sum('net'), 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">
                                    @php
                                        $totH = collect($weeklyRows)->sum('hours');
                                        $totG = collect($weeklyRows)->sum('gross');
                                    @endphp
                                    {{ $totH > 0 ? '$' . number_format($totG / $totH, 2) : '—' }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        @endif

    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    @php
        $trendClass = fn ($v) => match(true) {
            $v === null  => 'text-gray-400',
            $v > 0       => 'text-emerald-600 dark:text-emerald-400',
            $v < 0       => 'text-red-500 dark:text-red-400',
            default      => 'text-gray-400',
        };
        $trendIcon = fn ($v) => match(true) {
            $v === null  => '',
            $v > 0       => '↑',
            $v < 0       => '↓',
            default      => '→',
        };
        $pipelineColors = [
            'draft'    => ['dot' => 'bg-gray-400',    'text' => 'text-gray-600 dark:text-gray-300'],
            'approved' => ['dot' => 'bg-sky-500',     'text' => 'text-sky-600 dark:text-sky-400'],
            'paid'     => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-600 dark:text-emerald-400'],
        ];
    @endphp

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
                ['label' => 'Shows',       'value' => number_format($rev['shows']),          'trend' => $rev['trend_shows'], 'icon' => 'heroicon-o-video-camera',   'accent' => 'border-violet-500'],
                ['label' => 'Units Sold',  'value' => number_format($rev['units']),           'trend' => null,                'icon' => 'heroicon-o-shopping-bag',   'accent' => 'border-sky-500'],
                ['label' => 'Gross Rev',   'value' => '$'.number_format($rev['gross'], 0),   'trend' => $rev['trend_gross'], 'icon' => 'heroicon-o-banknotes',      'accent' => 'border-emerald-500'],
                ['label' => 'Whatnot Net', 'value' => '$'.number_format($rev['net'], 0),     'trend' => $rev['trend_net'],   'icon' => 'heroicon-o-arrow-trending-up','accent' => 'border-green-500'],
                ['label' => 'Tips',        'value' => '$'.number_format($rev['tips'], 0),    'trend' => null,                'icon' => 'heroicon-o-star',           'accent' => 'border-amber-500'],
                ['label' => 'Paper Sales', 'value' => '$'.number_format($rev['paper'], 0),   'trend' => null,                'icon' => 'heroicon-o-document-text',  'accent' => 'border-rose-500'],
            ] as $tile)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 border-t-2 {{ $tile['accent'] }}">
                    <div class="flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <x-dynamic-component :component="$tile['icon']" class="h-3.5 w-3.5" />
                        {{ $tile['label'] }}
                    </div>
                    <div class="mt-2 text-xl font-bold text-gray-900 dark:text-white sm:text-2xl">{{ $tile['value'] }}</div>
                    @if ($tile['trend'] !== null)
                        <div class="mt-1 text-xs font-medium {{ $trendClass($tile['trend']) }}">
                            {{ $trendIcon($tile['trend']) }} {{ abs($tile['trend']) }}%
                            <span class="font-normal text-gray-400">vs prior period</span>
                        </div>
                    @else
                        <div class="mt-1 text-xs text-transparent select-none">—</div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Revenue by channel --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Revenue by Channel</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-sm">
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
            @php
                $allWeeks    = $this->revenueByWeek;
                $weekDisplay = $showAllWeeks ? $allWeeks : array_slice($allWeeks, -8);
                $hasMore     = count($allWeeks) > 8;
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Revenue by Week</h3>
                    @if ($hasMore)
                        <button
                            wire:click="toggleAllWeeks"
                            class="text-xs text-primary-600 hover:underline dark:text-primary-400"
                        >
                            {{ $showAllWeeks ? 'Show recent' : 'Show all '.count($allWeeks).' weeks' }}
                        </button>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[320px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                <th class="px-4 py-3">Week of</th>
                                <th class="px-4 py-3 text-right">Shows</th>
                                <th class="px-4 py-3 text-right">Gross</th>
                                <th class="px-4 py-3 text-right">Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($weekDisplay as $row)
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
                    <table class="w-full min-w-[320px] text-sm">
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
                                        <span class="mr-1.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ $i + 1 }}</span>
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

        {{-- Payout pipeline --}}
        @php $ps = $this->payoutStatusSummary; @endphp
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Payout Pipeline</h3>
            </div>
            <div class="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-700">
                @foreach ([
                    ['key' => 'draft',    'label' => 'Draft'],
                    ['key' => 'approved', 'label' => 'Approved'],
                    ['key' => 'paid',     'label' => 'Paid'],
                ] as $col)
                    <div class="px-4 py-5 text-center sm:px-6">
                        <div class="flex items-center justify-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <span class="h-1.5 w-1.5 rounded-full {{ $pipelineColors[$col['key']]['dot'] }}"></span>
                            {{ $col['label'] }}
                        </div>
                        <div class="mt-1.5 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($ps[$col['key']]['count']) }}</div>
                        <div class="mt-0.5 text-sm font-medium {{ $pipelineColors[$col['key']]['text'] }}">
                            ${{ number_format($ps[$col['key']]['total'], 2) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</x-filament-panels::page>

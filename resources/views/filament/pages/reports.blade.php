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

        {{-- Period selector + date range + export --}}
        <div class="flex flex-wrap items-center gap-2 gap-y-2">
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

            <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>

            {{-- Custom date range --}}
            <div class="flex items-center gap-1.5">
                <input
                    type="date"
                    wire:model="dateFrom"
                    class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                />
                <span class="text-xs text-gray-400">to</span>
                <input
                    type="date"
                    wire:model="dateTo"
                    class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                />
                <button
                    wire:click="applyCustomRange"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $period === 'custom' ? 'bg-primary-600 text-white shadow-sm' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    Apply
                </button>
            </div>

            <span class="flex-1"></span>

            {{-- CSV export --}}
            <a
                wire:click.prevent="exportCsv"
                href="#"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition"
            >
                <x-heroicon-o-arrow-down-tray class="h-3.5 w-3.5" />
                Export CSV
            </a>
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

        {{-- ── Visual charts ──────────────────────────────────────────────────── --}}
        @php
            $weeks       = $this->revenueByWeek;
            $maxGross    = collect($weeks)->max('gross') ?: 1;
            $maxNet      = collect($weeks)->max('net')   ?: 1;
            $chartMax    = max($maxGross, 1);

            $streamers   = $this->topStreamersByPayout;
            $maxPayout   = collect($streamers)->max('total') ?: 1;

            $accentColors = [
                'bg-violet-500', 'bg-sky-500', 'bg-emerald-500',
                'bg-amber-500',  'bg-rose-500', 'bg-cyan-500',
                'bg-fuchsia-500','bg-lime-500', 'bg-orange-500', 'bg-teal-500',
            ];
        @endphp

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">

            {{-- Weekly revenue bar chart (3 cols) --}}
            <div class="lg:col-span-3 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Weekly Revenue</h3>
                    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-violet-500"></span> Gross</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500"></span> Net</span>
                    </div>
                </div>
                <div class="p-5">
                    @if (count($weeks) > 0)
                        <div class="relative h-44">
                            {{-- Y-axis grid lines --}}
                            @foreach ([75, 50, 25] as $pct)
                                <div class="absolute inset-x-0 border-t border-dashed border-gray-100 dark:border-gray-800"
                                     style="bottom: {{ $pct }}%">
                                </div>
                            @endforeach

                            {{-- Bars --}}
                            <div class="absolute inset-0 flex items-end gap-px">
                                @foreach ($weeks as $w)
                                    @php
                                        $grossPct = round(($w['gross'] / $chartMax) * 100, 1);
                                        $netPct   = round(($w['net']   / $chartMax) * 100, 1);
                                    @endphp
                                    <div class="group relative flex flex-1 flex-col items-center justify-end h-full gap-0.5">
                                        {{-- Tooltip --}}
                                        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 z-10 hidden group-hover:block
                                                    whitespace-nowrap rounded-md bg-gray-900 dark:bg-gray-700 px-2.5 py-1.5 shadow-lg text-white text-xs pointer-events-none">
                                            <div class="font-semibold">{{ $w['week'] }}</div>
                                            <div>Gross: ${{ number_format($w['gross'], 0) }}</div>
                                            <div>Net: ${{ number_format($w['net'], 0) }}</div>
                                            <div class="text-gray-400">{{ $w['shows'] }} show{{ $w['shows'] !== 1 ? 's' : '' }}</div>
                                        </div>
                                        {{-- Gross bar --}}
                                        <div class="w-full flex gap-0.5 items-end" style="height: {{ $grossPct }}%">
                                            <div class="flex-1 rounded-t bg-violet-500 opacity-90 group-hover:opacity-100 transition-opacity min-h-[2px]"></div>
                                            <div class="flex-1 rounded-t bg-emerald-500 opacity-80 group-hover:opacity-100 transition-opacity min-h-[2px]"
                                                 style="height: {{ $grossPct > 0 ? round(($netPct / $grossPct) * 100, 1) : 0 }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        {{-- X-axis labels --}}
                        <div class="mt-2 flex gap-px">
                            @foreach ($weeks as $w)
                                <div class="flex-1 text-center text-[10px] text-gray-400 dark:text-gray-500 truncate">
                                    {{ $w['week'] }}
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex h-44 items-center justify-center text-sm text-gray-400">No data for this period</div>
                    @endif
                </div>
            </div>

            {{-- Top streamers horizontal bars (2 cols) --}}
            <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Top Streamers</h3>
                </div>
                <div class="p-5 space-y-3">
                    @forelse (array_slice($streamers, 0, 8) as $i => $row)
                        @php $barPct = round(($row['total'] / $maxPayout) * 100, 1); @endphp
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="truncate text-xs font-medium text-gray-700 dark:text-gray-300">
                                    {{ $row['streamer'] }}
                                </span>
                                <span class="shrink-0 text-xs font-semibold text-violet-600 dark:text-violet-400">
                                    ${{ number_format($row['total'], 0) }}
                                </span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-1.5 rounded-full {{ $accentColors[$i % count($accentColors)] }} transition-all duration-500"
                                     style="width: {{ $barPct }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="flex h-32 items-center justify-center text-sm text-gray-400">No payouts in this period</div>
                    @endforelse
                </div>
            </div>
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
                        @forelse ($this->revenueByChannel as $i => $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $row['channel'] }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format($row['shows']) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format($row['units']) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-gray-900 dark:text-white">${{ number_format($row['gross'], 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-emerald-600 dark:text-emerald-400">${{ number_format($row['net'], 2) }}</td>
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
                            @forelse ($weekDisplay as $i => $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white whitespace-nowrap">{{ $row['week'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $row['shows'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium text-gray-900 dark:text-white">${{ number_format($row['gross'], 0) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-emerald-600 dark:text-emerald-400">${{ number_format($row['net'], 0) }}</td>
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
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        <span class="mr-1.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ $i + 1 }}</span>
                                        {{ $row['streamer'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $row['shows'] }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-semibold text-violet-600 dark:text-violet-400">${{ number_format($row['total'], 2) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-600 dark:text-gray-300">${{ number_format($row['avg'], 2) }}</td>
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

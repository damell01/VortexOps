<x-filament-panels::page>
@include('filament.pages.partials.reporting-workspace-styles')
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

    <div class="vx-report vx-business-report space-y-6">

        @if ($this->inventoryReportUrl())
            <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">Inventory Reports</div>
                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Value, stock health, velocity, aging, coverage and location analysis.</div>
                </div>
                <a href="{{ $this->inventoryReportUrl() }}"
                   class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-500">
                    <x-heroicon-o-cube class="h-4 w-4" />
                    Inventory Report
                </a>
            </div>
        @endif

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
            <div class="vx-custom-dates flex flex-wrap items-center gap-1.5">
                <input
                    type="date"
                    aria-label="Report start date"
                    wire:model="dateFrom"
                    class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                />
                <span class="text-xs text-gray-400">to</span>
                <input
                    type="date"
                    aria-label="Report end date"
                    wire:model="dateTo"
                    class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                />
                <button
                    wire:click="applyCustomRange"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $period === 'custom' ? 'bg-primary-600 text-white shadow-sm' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    Apply
                </button>
                @error('dateFrom')<p role="alert" class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
                @error('dateTo')<p role="alert" class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
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
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-7">
            @foreach ([
                ['label' => 'Shows',       'value' => number_format($rev['shows']),          'trend' => $rev['trend_shows'], 'icon' => 'heroicon-o-video-camera',   'accent' => 'border-violet-500'],
                ['label' => 'Units Sold',  'value' => number_format($rev['units']),           'trend' => null,                'icon' => 'heroicon-o-shopping-bag',   'accent' => 'border-sky-500'],
                ['label' => 'Gross Rev',   'value' => '$'.number_format($rev['gross'], 0),   'trend' => $rev['trend_gross'], 'icon' => 'heroicon-o-banknotes',      'accent' => 'border-emerald-500'],
                ['label' => 'Whatnot Net', 'value' => '$'.number_format($rev['net'], 0),     'trend' => $rev['trend_net'],   'icon' => 'heroicon-o-arrow-trending-up','accent' => 'border-green-500'],
                ['label' => 'Margin',      'value' => '$'.number_format($rev['margin'], 0),  'trend' => $rev['trend_margin'],'icon' => 'heroicon-o-scale',          'accent' => 'border-indigo-500', 'sub' => $rev['margin_pct'] !== null ? $rev['margin_pct'].'% of gross' : null],
                ['label' => 'Tips',        'value' => '$'.number_format($rev['tips'], 0),    'trend' => null,                'icon' => 'heroicon-o-star',           'accent' => 'border-amber-500'],
            ] as $tile)
                <div class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 border-t-2 {{ $tile['accent'] }}">
                    <div class="flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <x-dynamic-component :component="$tile['icon']" class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate" @if(($tile['label'] ?? null) === 'Margin') title="Revenue minus cost of goods sold (COGS) from sold items with a unit cost recorded. Not full business profit — payouts, shipping, and fees aren't subtracted." @endif>{{ $tile['label'] }}</span>
                    </div>
                    <div class="mt-2 truncate text-lg font-bold tabular-nums text-gray-900 dark:text-white sm:text-xl" title="{{ $tile['value'] }}">{{ $tile['value'] }}</div>
                    @if ($tile['trend'] !== null)
                        <div class="mt-1 text-xs font-medium {{ $trendClass($tile['trend']) }}">
                            {{ $trendIcon($tile['trend']) }} {{ abs($tile['trend']) }}%
                            <span class="font-normal text-gray-400">vs prior period</span>
                        </div>
                    @elseif (! empty($tile['sub']))
                        <div class="mt-1 text-xs font-medium text-gray-400">{{ $tile['sub'] }}</div>
                    @else
                        <div class="mt-1 text-xs text-transparent select-none">—</div>
                    @endif
                    @if ($tile['trend'] !== null && ! empty($tile['sub']))
                        <div class="text-[11px] text-gray-400">{{ $tile['sub'] }}</div>
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
                                    <div class="group relative flex flex-1 flex-col items-center justify-end h-full gap-2.5">
                                        {{-- Tooltip --}}
                                        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 z-10 hidden group-hover:block
                                                    whitespace-nowrap rounded-md bg-gray-900 dark:bg-gray-700 px-2.5 py-1.5 shadow-lg text-white text-xs pointer-events-none">
                                            <div class="font-semibold">{{ $w['week'] }}</div>
                                            <div>Gross: ${{ number_format($w['gross'], 0) }}</div>
                                            <div>Net: ${{ number_format($w['net'], 0) }}</div>
                                            <div class="text-gray-400">{{ $w['shows'] }} show{{ $w['shows'] !== 1 ? 's' : '' }}</div>
                                        </div>
                                        {{-- Gross bar --}}
                                        <div class="w-full flex gap-2.5 items-end" style="height: {{ $grossPct }}%">
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
                                <div class="flex-1 text-center text-xs text-gray-400 dark:text-gray-500 truncate">
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
                        @php $barPct = $maxPayout > 0 ? round(((float) $row['total'] / $maxPayout) * 100, 1) : 0; @endphp
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
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($this->revenueByChannel as $row)
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-5 py-3">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $row['channel'] }}</span>
                        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-right tabular-nums">
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($row['shows']) }} shows</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($row['units']) }} units</span>
                            <span class="font-medium text-gray-900 dark:text-white">${{ number_format($row['gross'], 2) }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400">${{ number_format($row['net'], 2) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400">No shows in this period</div>
                @endforelse
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
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($weekDisplay as $row)
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-3">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $row['week'] }}</span>
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-right tabular-nums">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['shows'] }} shows</span>
                                <span class="font-medium text-gray-900 dark:text-white">${{ number_format($row['gross'], 0) }}</span>
                                <span class="text-emerald-600 dark:text-emerald-400">${{ number_format($row['net'], 0) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-gray-400">No data</div>
                    @endforelse
                </div>
            </div>

            {{-- Top streamers --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Top Streamers by Payout</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($this->topStreamersByPayout as $i => $row)
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-4 py-3">
                            <span class="font-medium text-gray-900 dark:text-white">
                                <span class="mr-1.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500 dark:bg-gray-700 dark:text-gray-400">{{ $i + 1 }}</span>
                                {{ $row['streamer'] }}
                            </span>
                            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-right tabular-nums">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['shows'] }} shows</span>
                                <span class="font-semibold text-violet-600 dark:text-violet-400">${{ number_format($row['total'], 2) }}</span>
                                <span class="text-gray-600 dark:text-gray-300">${{ number_format($row['avg'], 2) }} avg</span>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-gray-400">No payouts in this period</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Payout pipeline --}}
        {{-- ── Compare two periods ─────────────────────────────────────── --}}
        @php
            $cmp = $this->comparison;
            $fmt = fn ($v, $money) => $v === null ? '—' : ($money ? '$'.number_format($v, 2) : number_format($v, is_float($v) && floor($v) != $v ? 1 : 0));
        @endphp
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" aria-labelledby="compare-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div>
                    <h3 id="compare-heading" class="text-sm font-semibold text-gray-800 dark:text-gray-200">Compare two periods</h3>
                    <p class="mt-0.5 text-xs text-gray-500">The period selected above against another. Profit is net plus tips, less cost of goods (approved report items) and streamer pay — the same rule as each show page.</p>
                </div>
                <div class="flex flex-wrap items-end gap-2 text-sm">
                    <label class="flex flex-col gap-1">
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Compare with</span>
                        <select wire:model.live="compareMode" class="min-h-10 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @foreach ($this->compareOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    @if ($compareMode === 'custom')
                        <input type="date" wire:model.live.debounce.500ms="compareFrom" aria-label="Compare from" class="min-h-10 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <span class="pb-2 text-gray-500">to</span>
                        <input type="date" wire:model.live.debounce.500ms="compareTo" aria-label="Compare to" class="min-h-10 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-[11px] uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Metric</th>
                            <th class="px-3 py-2 text-right">{{ $cmp['a_label'] }}</th>
                            <th class="px-3 py-2 text-right">{{ $cmp['b_label'] }}</th>
                            <th class="px-4 py-2 text-right">Change</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($cmp['rows'] as $row)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $row['label'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($row['a'], $row['money']) }}{{ $row['label'] === 'Profit margin %' && $row['a'] !== null ? '%' : '' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $fmt($row['b'], $row['money']) }}{{ $row['label'] === 'Profit margin %' && $row['b'] !== null ? '%' : '' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold {{ $row['better'] === null ? 'text-gray-500' : ($row['better'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400') }}">
                                    @if ($row['change'] === null) —
                                    @else
                                        {{ $row['change'] > 0 ? '+' : '' }}{{ $row['label'] === 'Profit margin %' ? number_format($row['change'], 1).' pts' : $fmt($row['change'], $row['money']) }}
                                        @if ($row['change_pct'] !== null)<span class="ml-1 text-xs font-normal">({{ $row['change_pct'] > 0 ? '+' : '' }}{{ number_format($row['change_pct'], 1) }}%)</span>@endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ── Channel profit ─────────────────────────────────────────────── --}}
        @php
            $cp = $this->channelProfit;
            $cpTotal = collect($cp);
        @endphp
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" aria-labelledby="channel-profit-heading">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <h3 id="channel-profit-heading" class="text-sm font-semibold text-gray-800 dark:text-gray-200">Channel profit</h3>
                <p class="mt-0.5 text-xs text-gray-500">What each channel actually made in the period above. Giveaway spend is Whatnot's figure, shown for reference — the cost of giveaway items is already in cost of goods, so it is not subtracted again.</p>
            </div>
            @if ($cp === [])
                <p class="px-4 py-8 text-center text-sm text-gray-500">No shows in this period.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-[11px] uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2">Channel</th><th class="px-3 py-2 text-right">Shows</th><th class="px-3 py-2 text-right">Gross</th>
                                <th class="px-3 py-2 text-right">Whatnot fees</th><th class="px-3 py-2 text-right">Cost of goods</th><th class="px-3 py-2 text-right">Pay</th>
                                <th class="px-3 py-2 text-right">Tips</th><th class="px-3 py-2 text-right">Profit</th><th class="px-3 py-2 text-right">Margin</th>
                                <th class="px-3 py-2 text-right">Per show</th><th class="px-4 py-2 text-right">Giveaway spend</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($cp as $row)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $row['channel'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['shows']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">${{ number_format($row['gross'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">${{ number_format($row['fees'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">${{ number_format($row['cogs'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">${{ number_format($row['pay'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">${{ number_format($row['tips'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-semibold {{ $row['profit'] < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">${{ number_format($row['profit'], 2) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['margin_pct'] === null ? '—' : number_format($row['margin_pct'], 1).'%' }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['profit_per_show'] === null ? '—' : '$'.number_format($row['profit_per_show'], 2) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-500">${{ number_format($row['giveaways'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if (count($cp) > 1)
                            <tfoot class="border-t border-gray-200 font-semibold dark:border-gray-700">
                                <tr>
                                    <td class="px-4 py-2">All channels</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($cpTotal->sum('shows')) }}</td>
                                    @foreach (['gross', 'fees', 'cogs', 'pay', 'tips', 'profit'] as $col)<td class="px-3 py-2 text-right tabular-nums">${{ number_format($cpTotal->sum($col), 2) }}</td>@endforeach
                                    <td class="px-3 py-2"></td><td class="px-3 py-2"></td>
                                    <td class="px-4 py-2 text-right tabular-nums">${{ number_format($cpTotal->sum('giveaways'), 2) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </section>

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


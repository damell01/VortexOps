<x-filament-panels::page>
    @php
        $report = $this->report();
        $range = $report ? $this->range() : null;
        $channels = $report ? collect($report['rows'])->groupBy('channel')->map(fn ($rows, $name) => [
            'name' => $name, 'credits' => $rows->sum('credits'), 'debits' => $rows->sum('debits'),
            'net' => $rows->sum('net'), 'entries' => $rows->sum('entries'),
        ])->sortByDesc('credits')->values() : collect();
        $palette = ['#8247f5', '#3583f4', '#12b890', '#f59e0b', '#ef657b', '#6366f1'];
        $credits = $report['total']['credits'] ?? 0;
        $angle = 0;
        $segments = [];
        foreach ($channels as $index => $item) {
            $next = $angle + ($credits ? $item['credits'] / $credits * 100 : 0);
            $segments[] = $palette[$index % count($palette)].' '.$angle.'% '.$next.'%';
            $angle = $next;
        }
        $daily = collect($report['days'] ?? [])->keyBy('day');
        $trend = collect();
        if ($range) {
            for ($date = $range[0]->copy(); $date->lte($range[1]); $date->addDay()) {
                $trend->push(['day' => $date->toDateString(), 'net' => $daily->get($date->toDateString())['net'] ?? 0]);
            }
        }
        $low = min(0, $trend->min('net') ?? 0);
        $span = max(1, max(0, $trend->max('net') ?? 0) - $low);
        $points = $trend->count() > 1 ? $trend->map(fn ($item, $index) =>
            round(32 + $index * 636 / ($trend->count() - 1), 1).','.round(145 - ($item['net'] - $low) * 112 / $span, 1)
        )->implode(' ') : '';
        $zeroY = round(145 - (0 - $low) * 112 / $span, 1);
    @endphp
    <div class="vxw vxt" data-vx-page="ledger-tips">
        <div class="vxw-hero">
            <div>
                <p class="vxw-sub">Review imported Whatnot tips by channel and business before deciding how to pay them.</p>
                @if ($range)<p class="vxw-meta">{{ $range[0]->format('M j, Y') }} – {{ $range[1]->format('M j, Y') }} · Ledger created dates · Live imported totals</p>@endif
            </div>
            <a class="vxw-btn" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index') }}">Open ledger <span aria-hidden="true">↗</span></a>
        </div>
        <section class="vxw-card" aria-label="Tip report filters">
            <div class="vxw-card-body vxt-filters">
                <label class="vxt-filter">Period
                    <select class="vxw-select" wire:model.live="period">
                        @foreach ($this::PERIODS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="vxt-filter">Channel
                    <select class="vxw-select" wire:model.live="channel">
                        <option value="all">All channels</option>
                        @foreach ($this->channels() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        <option value="unassigned">Unassigned channel</option>
                    </select>
                </label>
                <label class="vxt-filter">Business
                    <select class="vxw-select" wire:model.live="business">
                        <option value="all">All businesses</option>
                        @foreach ($this->businesses() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        <option value="unassigned">Unassigned business</option>
                    </select>
                </label>
                @if ($period === 'custom')
                    <label class="vxt-filter">From<input class="vxw-input" type="date" wire:model.live="from"></label>
                    <label class="vxt-filter">Through<input class="vxw-input" type="date" wire:model.live="until"></label>
                @elseif ($period === 'pay_period')
                    <label class="vxt-filter">Pay run dates
                        <select class="vxw-select" wire:model.live="payRun">
                            <option value="">Choose a pay run</option>
                            @foreach ($this->payRuns() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                @endif
                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="resetFilters">Reset</button>
            </div>
            @foreach ($this->getErrorBag()->all() as $error)<p role="alert" class="vxt-error">{{ $error }}</p>@endforeach
        </section>
        @if ($report)
            @php
                $reviewRows = collect($report['rows'])->filter(fn ($row) => $row['business'] === 'Unassigned business');
                $reviewCount = $reviewRows->sum('entries') + ($report['unassigned_total']['entries'] ?? 0);
                $reviewNet = $reviewRows->sum('net') + ($report['unassigned_total']['net'] ?? 0);
            @endphp
            @if($reviewCount > 0)
                <div class="vxw-alert vxw-alert--warn" role="status"><div>
                    <strong>{{ number_format($reviewCount) }} tip transactions need business attribution review</strong>
                    <p>Net ${{ number_format($reviewNet / 100, 2) }} is not verified for a business. These tips are not automatically allocated or paid.</p>
                </div></div>
            @endif
            <section class="vxw-card">
                <div class="vxw-card-head"><h2 class="vxw-h2">Verified tips by streamer</h2></div>
                <div class="vxw-card-body">
                    <p class="vxw-meta">Only exact ledger order matches to one show with one assigned streamer. Shared or ambiguous shows are excluded. Read-only; does not change payroll.</p>
                    @forelse ($this->streamerTotals() as $streamerTip)
                        <div class="vxt-channel">
                            <strong>{{ $streamerTip['streamer'] }}</strong>
                            <span>{{ number_format($streamerTip['entries']) }} transactions</span>
                            <b>${{ number_format($streamerTip['net'] / 100, 2) }}</b>
                        </div>
                    @empty
                        <p class="vxw-meta">No uniquely attributed streamer tips in this period.</p>
                    @endforelse
                </div>
            </section>
            <div class="vxt-stats" aria-label="Tip totals">
                @foreach ([
                    ['Tip credits', 'credits', 'Imported positive amounts', 'accent'],
                    ['Tip debits / corrections', 'debits', 'Imported negative amounts', 'info'],
                    ['Net ledger tips', 'net', 'Credits less debits', 'ok'],
                    ['Transactions', 'entries', 'All imported statuses', 'warn'],
                ] as [$label, $key, $note, $tone])
                    <div class="vxr-stat vxr-tone-{{ $tone }}">
                        <div class="vxr-stat-icon" aria-hidden="true">{{ ['credits' => '+', 'debits' => '−', 'net' => '=', 'entries' => '#'][$key] }}</div>
                        <div><div class="vxr-stat-label">{{ $label }}</div>
                            <div class="vxr-stat-value">{{ $key === 'entries' ? number_format($report['total'][$key]) : '$'.number_format($report['total'][$key] / 100, 2) }}</div>
                            <div class="vxr-stat-note">{{ $note }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <section class="vxw-card">
                <div class="vxw-card-head"><h2 class="vxw-h2">Channel totals</h2><span class="vxw-meta">For selected filters</span></div>
                <div class="vxw-card-body vxt-channels">
                    @forelse ($channels as $index => $item)
                        <div class="vxt-channel">
                            <span class="vxt-channel-mark" style="--vxt-color: {{ $palette[$index % count($palette)] }}" aria-hidden="true">{{ mb_substr($item['name'], 0, 1) }}</span>
                            <div><strong>{{ $item['name'] }}</strong><span class="vxw-meta">{{ $item['entries'] }} transactions</span>
                                <b>${{ number_format($item['net'] / 100, 2) }}</b>
                                <span class="vxw-meta">Credits ${{ number_format($item['credits'] / 100, 2) }} · Debits ${{ number_format($item['debits'] / 100, 2) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="vxw-meta">No imported tips for these filters.</p>
                    @endforelse
                </div>
            </section>
            <div class="vxt-charts">
                <section class="vxw-card">
                    <div class="vxw-card-head"><h2 class="vxw-h2">Daily trend</h2><span class="vxw-meta">Net ledger tips · selected period</span></div>
                    <div class="vxw-card-body">
                        @if ($points)
                            <div class="vxt-trend" role="img" aria-label="Daily net ledger tips from {{ $range[0]->format('M j') }} through {{ $range[1]->format('M j') }}. Exact weekly values are in the table below.">
                                <svg viewBox="0 0 700 165" preserveAspectRatio="none" aria-hidden="true">
                                    <line x1="32" x2="668" y1="33" y2="33" class="vxt-grid"/>
                                    <line x1="32" x2="668" y1="89" y2="89" class="vxt-grid"/>
                                    <line x1="32" x2="668" y1="145" y2="145" class="vxt-grid"/>
                                    <line x1="32" x2="668" y1="{{ $zeroY }}" y2="{{ $zeroY }}" class="vxt-zero"/>
                                    <polyline points="{{ $points }}" class="vxt-line"/>
                                </svg>
                            </div>
                            <div class="vxt-trend-labels"><span>{{ $range[0]->format('M j') }}</span><span>{{ $range[1]->format('M j') }}</span></div>
                        @else
                            <p class="vxw-meta">Select a range of at least two days to see the trend.</p>
                        @endif
                    </div>
                </section>
                <section class="vxw-card">
                    <div class="vxw-card-head"><h2 class="vxw-h2">Tip credits by channel</h2></div>
                    <div class="vxw-card-body vxt-share">
                        @if ($credits > 0)
                            <div class="vxt-donut" style="background: conic-gradient({{ implode(', ', $segments) }})" role="img" aria-label="Tip credit share by channel; exact amounts listed next to chart"><span>${{ number_format($credits / 100, 2) }}<small>Total credits</small></span></div>
                            <div class="vxt-legend">
                                @foreach ($channels as $index => $item)
                                    <div><i style="--vxt-color: {{ $palette[$index % count($palette)] }}" aria-hidden="true"></i><span>{{ $item['name'] }}</span><span>{{ number_format($item['credits'] / $credits * 100, 1) }}%</span><strong>${{ number_format($item['credits'] / 100, 2) }}</strong></div>
                                @endforeach
                            </div>
                        @else
                            <p class="vxw-meta">No tip credits in this period.</p>
                        @endif
                    </div>
                </section>
            </div>
            @if ($report['unassigned_total']['entries'])
                <div class="vxw-alert vxw-alert--warn"><div>
                    <strong>Unassigned business: ${{ number_format($report['unassigned_total']['net'] / 100, 2) }}</strong>
                    <p>Not included in this business's total. Review these tips before allocating them.</p>
                    @foreach ($report['unassigned'] as $row)<p>{{ $row['channel'] }} · ${{ number_format($row['net'] / 100, 2) }} · {{ $row['entries'] }} transactions</p>@endforeach
                </div></div>
            @endif
            <section class="vxw-card">
                <div class="vxw-card-head"><h2 class="vxw-h2">Weekly breakdown</h2><div class="vxw-hero-actions"><button type="button" class="vxw-btn vxw-btn--sm" wire:click="exportCsv">Export CSV</button><a class="vxw-btn vxw-btn--sm" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index') }}">View ledger</a></div></div>
                <div class="vxr-table-wrap"><table class="vxr-table">
                    <thead><tr><th scope="col">Period</th><th scope="col">Channel</th><th scope="col">Business</th><th scope="col" class="num">Tip credits</th><th scope="col" class="num">Corrections</th><th scope="col" class="num">Net</th><th scope="col" class="num">Transactions</th></tr></thead>
                    <tbody>
                        @forelse ($report['weeks'] as $row)
                            <tr><td>Week of {{ \Illuminate\Support\Carbon::parse($row['week'])->format('M j, Y') }}</td><td>{{ $row['channel'] }}</td><td>{{ $row['business'] }}</td><td class="num">${{ number_format($row['credits'] / 100, 2) }}</td><td class="num">${{ number_format($row['debits'] / 100, 2) }}</td><td class="num strong">${{ number_format($row['net'] / 100, 2) }}</td><td class="num">{{ $row['entries'] }}</td></tr>
                        @empty
                            <tr><td colspan="7" class="vxr-empty">No imported tips for these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
                <div class="vxw-card-foot vxw-meta">Weeks start {{ \App\Support\PaySchedule::startDayName() }}. First and last weeks include only dates inside the selected range. {{ $report['total']['pending'] }} pending / processing · {{ $report['total']['missing_amounts'] }} missing amounts. Totals include all imported statuses and signed corrections.</div>
            </section>
            <p class="vxw-meta">Channels are shared. A business is identified only through an exact order match to one show whose streamers all belong to that business. Other tips remain unassigned. These live totals do not allocate tips or change payroll; missing imports can make them incomplete.</p>
        @endif
    </div>
</x-filament-panels::page>

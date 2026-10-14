<x-filament-panels::page>
@php
    $rev = $this->revenueSummary;
    $money = fn ($v, $d = 2) => ($v < 0 ? '-' : '').'$'.number_format(abs((float) $v), $d);
    $vsLabel = $this->trendLabel();
    $allWeeks = $this->revenueByWeek;
    $weekDisplay = $showAllWeeks ? $allWeeks : array_slice($allWeeks, -8);
    $hasMoreWeeks = count($allWeeks) > 8;
    $metric = array_key_exists($weekMetric, $this::WEEK_METRICS) ? $weekMetric : 'gross';
    $metricIsMoney = in_array($metric, ['gross', 'net'], true);
    $streamers = $this->topStreamersByPayout;
    $sortKey = array_key_exists($streamerSort, $this::STREAMER_SORTS) ? $streamerSort : 'gross';
    $maxStreamer = max(1, (float) collect($streamers)->max($sortKey));
    $channels = $this->revenueByChannel;
    $channelOptions = $this->channelOptions();
@endphp

<style>
    .vxrep { grid-template-columns: minmax(0, 1fr); }
    /* The phone select picker (mobile-controls.js) sits inside our control; let the control be the box. */
    @media (max-width: 767px) {
        .vxrep .vxr-control { height: auto; min-height: 48px; }
        .vxrep .vxr-control .vx-picker-trigger { border: 0; background: transparent; min-height: 44px; padding: 0; font-size: 15px; flex: 1 1 auto; min-width: 0; }
    }
    .vxrep-head-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: flex-end; }
    .vxrep-head-tools > * { min-width: 0; max-width: 100%; }
    .vxrep-range input[type=date] { width: 128px; }
    .vxrep-range .vxr-btn { height: 30px; padding: 0 10px; font-size: 13px; }
    .vxrep-delta { display: flex; gap: 6px; align-items: baseline; flex-wrap: wrap; }
    .vxrep-strip-label { font-size: 13px; color: var(--vxw-muted); display: block; }
    .vxrep-strip-value { font-size: 19px; font-weight: 750; font-variant-numeric: tabular-nums; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .vxrep-strip .vxr-stat-icon { width: 42px; height: 42px; border-radius: 12px; }
    .vxrep-strip .vxr-stat-icon svg { width: 22px; height: 22px; }
    .vxrep-strip .vxr-foot-note { align-self: center; }
    .vxrep-chans { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); }
    .vxrep-chan { display: flex; gap: 14px; padding: 16px; border: 1px solid var(--vxw-border); border-radius: 12px; background: var(--vxw-surface); min-width: 0; }
    .vxrep-chan b { display: block; font-size: 15.5px; font-weight: 700; margin-bottom: 6px; overflow-wrap: anywhere; }
    .vxrep-chan .vxr-dl > div { padding: 4px 0; border: 0; font-size: 13.5px; }
    .vxrep-chan .vxr-stat-icon { width: 46px; height: 46px; }
    .vxrep-tile-row { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
    .vxrep-tile-row .vxr-linkrow b { font-size: 14.5px; }
    .vxrep-tile-row .vxr-linkrow span { font-size: 12.5px; display: block; line-height: 1.35; }
    .vxrep-wide-table th, .vxrep-wide-table td { white-space: nowrap; }
    .vxrep-pipe { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    .vxrep-pipe > div { padding: 14px 16px; border: 1px solid var(--vxw-border); border-radius: 12px; }
    .vxrep-pipe b { display: block; font-size: 22px; font-weight: 750; font-variant-numeric: tabular-nums; }
    .vxrep-bar-sub { display: block; font-size: 12px; color: var(--vxw-muted); white-space: normal; }
    .vxrep .vxr-bar { grid-template-columns: minmax(120px, 230px) minmax(0, 1fr) auto; }
    @media (max-width: 640px) {
        .vxrep .vxr-head > * { flex: 1 1 100%; min-width: 0; }
        .vxrep-head-tools { justify-content: flex-start; }
        .vxrep-range input[type=date] { width: auto; flex: 1 1 110px; }
        .vxrep-head-tools .vxr-seg { width: 100%; overflow-x: auto; }
        .vxrep-range { flex-wrap: wrap; height: auto; padding: 6px 10px; }
        .vxrep-chan-select, .vxrep-chan-select select { width: 100%; }
        .vxr-bar { grid-template-columns: minmax(0, 1fr) auto; }
        .vxr-bar .vxr-bar-track { grid-column: 1 / -1; grid-row: 2; }
    }
</style>

<div class="vxr vxrep">
    <div wire:loading.delay wire:target="setPeriod,applyCustomRange,channel" class="vxr-banner vxr-banner--accent" style="position:fixed;top:84px;left:50%;transform:translateX(-50%);z-index:50;box-shadow:var(--vxw-shadow-pop)">
        Updating report…
    </div>

    <div class="vxr-head">
        <div>
            <h1 class="vxr-title">Whatnot Performance</h1>
            <p class="vxr-subtitle">Show revenue, channel performance and streamer activity.</p>
        </div>
        <div class="vxrep-head-tools" id="show-performance">
            <div class="vxr-seg" role="group" aria-label="Period">
                @foreach ($this->getPeriodOptions() as $days => $label)
                    <button type="button" wire:click="setPeriod('{{ $days }}')" class="{{ $period === (string) $days ? 'is-active' : '' }}"
                        aria-pressed="{{ $period === (string) $days ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="vxr-control vxrep-range {{ $period === 'custom' ? 'vxr-tone-accent' : '' }}" @if($period === 'custom') style="border-color:var(--vxw-accent-line)" @endif>
                <x-filament::icon icon="heroicon-o-calendar-days" />
                <input type="date" aria-label="Report start date" wire:model="dateFrom">
                <span class="vxr-val-muted">–</span>
                <input type="date" aria-label="Report end date" wire:model="dateTo">
                <button type="button" class="vxr-btn vxr-btn--outline" wire:click="applyCustomRange">Apply</button>
            </div>
            <label class="vxr-control vxrep-chan-select">
                <x-filament::icon icon="heroicon-o-signal" />
                <select wire:model.live="channel" aria-label="Channel">
                    <option value="">{{ \App\Support\ChannelContext::isScoped() ? \App\Support\ChannelContext::current()?->name.' (top bar)' : 'All channels' }}</option>
                    @foreach ($channelOptions as $id => $name)
                        @continue(\App\Support\ChannelContext::currentId() === (int) $id)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <button type="button" class="vxr-btn vxr-btn--primary" wire:click="exportCsv">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" /> Export CSV
            </button>
        </div>
    </div>

    @error('dateFrom')<div role="alert" class="vxr-banner vxr-banner--bad">{{ $message }}</div>@enderror
    @error('dateTo')<div role="alert" class="vxr-banner vxr-banner--bad">{{ $message }}</div>@enderror

    <p class="vxr-foot-note" style="margin-top:-8px">
        <x-filament::icon icon="heroicon-o-calendar" />
        {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M j, Y') }}
        @if ($this->channelId()) · {{ $channelOptions[$this->channelId()] ?? \App\Support\ChannelContext::current()?->name }} @endif
        · cancelled shows excluded
    </p>

    {{-- Headline figures with the change against the period before. --}}
    <div class="vxr-stats">
        @foreach ([
            ['Shows', number_format($rev['shows']), $rev['trend_shows'], 'heroicon-o-video-camera', 'accent'],
            ['Units Sold', number_format($rev['units']), $rev['trend_units'] ?? null, 'heroicon-o-cube', 'info'],
            ['Gross Revenue', $money($rev['gross']), $rev['trend_gross'], 'heroicon-o-currency-dollar', 'ok'],
            ['Whatnot Est. Earnings', $money($rev['net']), $rev['trend_net'], 'heroicon-o-banknotes', 'accent'],
        ] as [$label, $value, $trend, $icon, $tone])
            <x-vxr.stat :label="$label" :value="$value" :icon="$icon" :tone="$tone">
                <span class="vxrep-delta">
                    @if ($trend === null)
                        <span class="vxr-val-muted">No earlier figure to compare</span>
                    @else
                        <span class="vxr-delta {{ $trend > 0 ? 'vxr-delta--up' : ($trend < 0 ? 'vxr-delta--down' : '') }}">{{ $trend > 0 ? '▲' : ($trend < 0 ? '▼' : '■') }} {{ number_format(abs($trend), 1) }}%</span>
                        <span>{{ $vsLabel }}</span>
                    @endif
                </span>
            </x-vxr.stat>
        @endforeach
    </div>

    {{-- Costs: only what has been recorded, never a guessed zero. --}}
    <div class="vxr-strip vxrep-strip">
        <div>
            <span class="vxr-stat-icon vxr-tone-warn"><x-filament::icon icon="heroicon-o-document-text" /></span>
            <span style="min-width:0">
                <span class="vxrep-strip-label">Recorded COGS</span>
                <span class="vxrep-strip-value">{{ $rev['cogs'] > 0 ? $money($rev['cogs']) : 'Not recorded' }}</span>
            </span>
        </div>
        <div title="Revenue minus cost of goods sold (COGS) from sold items with a unit cost recorded. Not full business profit — payouts, shipping, and fees are not included.">
            <span class="vxr-stat-icon vxr-tone-accent"><x-filament::icon icon="heroicon-o-calculator" /></span>
            <span style="min-width:0">
                <span class="vxrep-strip-label">Gross minus recorded COGS</span>
                <span class="vxrep-strip-value">
                    @if ($rev['cogs'] > 0)
                        {{ $money($rev['margin']) }}
                        @if ($rev['margin_pct'] !== null)<span class="vxr-val-muted" style="font-size:13px;font-weight:600">{{ number_format($rev['margin_pct'], 1) }}%</span>@endif
                        <span class="vxr-pill vxr-pill--warn">Provisional</span>
                    @else
                        Provisional <span class="vxr-val-muted" style="font-size:12.5px;font-weight:500">no costs recorded yet</span>
                    @endif
                </span>
            </span>
        </div>
        <div>
            <span class="vxr-stat-icon vxr-tone-bad"><x-filament::icon icon="heroicon-o-gift" /></span>
            <span style="min-width:0">
                <span class="vxrep-strip-label">Tips</span>
                <span class="vxrep-strip-value">{{ $money($rev['tips']) }}</span>
            </span>
        </div>
        <div>
            <p class="vxr-foot-note" style="margin:0">
                <x-filament::icon icon="heroicon-o-information-circle" />
                Missing costs are not treated as verified zero costs.
            </p>
        </div>
    </div>

    <div class="vxr-grid vxr-grid--2">
        <section class="vxr-card">
            <div class="vxr-card-head">
                <h3 class="vxr-card-title"><x-filament::icon icon="heroicon-o-chart-bar" /> Revenue by Week</h3>
                <div class="vxr-actions">
                    @if ($hasMoreWeeks)
                        <button type="button" class="vxr-btn vxr-btn--sm" wire:click="toggleAllWeeks">
                            {{ $showAllWeeks ? 'Show recent' : 'Show all '.count($allWeeks).' weeks' }}
                        </button>
                    @endif
                    <select class="vxr-select" style="height:34px" wire:model.live="weekMetric" aria-label="Chart metric">
                        @foreach ($this::WEEK_METRICS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="vxr-card-body">
                @if ($weekDisplay === [])
                    <div class="vxr-empty"><b>No shows in this period</b>Pick a longer period or another channel.</div>
                @else
                    @php
                        $points = array_map(fn ($w) => [
                            'label' => $w['week']."\n".\Illuminate\Support\Carbon::parse($w['week_start'])->format('Y'),
                            'value' => $w[$metric],
                            'title' => 'Week of '.$w['week'].': '.$w['shows'].' '.Str::plural('show', $w['shows']).', '.$money($w['gross'], 0).' gross, '.$money($w['net'], 0).' est. earnings, '.number_format($w['units']).' units',
                        ], $weekDisplay);
                    @endphp
                    <x-vxr.chart :points="$points" :type="count($points) > 1 ? 'area' : 'bar'" :money="$metricIsMoney" :height="250" aria-label="{{ $this::WEEK_METRICS[$metric] }} by week" />
                    <p class="vxr-hint">Weeks start on Monday. Hover a point for that week's shows, revenue and units.</p>
                @endif
            </div>
        </section>

        <section class="vxr-card">
            <div class="vxr-card-head">
                <h3 class="vxr-card-title"><x-filament::icon icon="heroicon-o-user-group" /> Top Streamers</h3>
                <select class="vxr-select" style="height:34px" wire:model.live="streamerSort" aria-label="Rank streamers">
                    @foreach ($this::STREAMER_SORTS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="vxr-card-body">
                @if ($streamers === [])
                    <div class="vxr-empty"><b>No assigned streamer revenue in this period</b>Only a show's primary streamer is counted.</div>
                @else
                    <div class="vxr-bars">
                        @foreach (array_slice($streamers, 0, 8) as $row)
                            @php $v = (float) $row[$sortKey]; @endphp
                            <div class="vxr-bar">
                                <span class="vxr-bar-name" title="{{ $row['streamer'] }}">
                                    {{ $row['streamer'] }}
                                    <span class="vxrep-bar-sub">{{ $row['shows'] }} {{ Str::plural('show', $row['shows']) }} · {{ $money($row['gross'], 0) }} gross · {{ $money($row['net'], 0) }} est.</span>
                                </span>
                                <span class="vxr-bar-track"><span class="vxr-bar-fill" style="display:block;width:{{ max(0, round($v / $maxStreamer * 100, 1)) }}%"></span></span>
                                <span class="vxr-bar-val">{{ $sortKey === 'shows' ? number_format($v).' '.Str::plural('show', (int) $v) : $money($v, 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="vxr-hint" style="margin-top:14px">Primary streamer per show; cancelled shows excluded.</p>
                @endif
            </div>
        </section>
    </div>

    <section class="vxr-card">
        <div class="vxr-card-head">
            <h3 class="vxr-card-title"><x-filament::icon icon="heroicon-o-square-3-stack-3d" /> Channel Performance</h3>
        </div>
        <div class="vxr-card-body">
            @if ($channels === [])
                <div class="vxr-empty"><b>No shows in this period</b></div>
            @else
                <div class="vxrep-chans">
                    @foreach ($channels as $row)
                        <div class="vxrep-chan">
                            <span class="vxr-stat-icon vxr-tone-accent"><x-filament::icon icon="heroicon-o-signal" /></span>
                            <div style="flex:1;min-width:0">
                                <b>{{ $row['channel'] }}</b>
                                <dl class="vxr-dl">
                                    <div><dt>Shows</dt><dd>{{ number_format($row['shows']) }}</dd></div>
                                    <div><dt>Units</dt><dd>{{ number_format($row['units']) }}</dd></div>
                                    <div><dt>Gross Revenue</dt><dd>{{ $money($row['gross']) }}</dd></div>
                                    <div><dt>Est. Earnings</dt><dd>{{ $money($row['net']) }}</dd></div>
                                </dl>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Compare two periods --}}
    @php
        $cmp = $this->comparison;
        $fmt = fn ($v, $isMoney) => $v === null ? '—' : ($isMoney ? $money($v) : number_format($v, is_float($v) && floor($v) != $v ? 1 : 0));
    @endphp
    <section class="vxr-card" aria-labelledby="compare-heading">
        <div class="vxr-card-head">
            <div>
                <h3 id="compare-heading" class="vxr-card-title"><x-filament::icon icon="heroicon-o-arrows-right-left" /> Compare two periods</h3>
                <p class="vxr-card-sub">The period selected above against another. Profit is net plus tips, less cost of goods (approved report items) and streamer pay — the same rule as each show page.</p>
            </div>
            <div class="vxr-toolbar">
                <label class="vxr-control">
                    <span class="vxr-val-muted" style="font-size:13px">Compare with</span>
                    <select wire:model.live="compareMode" aria-label="Compare with">
                        @foreach ($this->compareOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                @if ($compareMode === 'custom')
                    <label class="vxr-control"><input type="date" wire:model.live.debounce.500ms="compareFrom" aria-label="Compare from"></label>
                    <span class="vxr-val-muted">to</span>
                    <label class="vxr-control"><input type="date" wire:model.live.debounce.500ms="compareTo" aria-label="Compare to"></label>
                @endif
            </div>
        </div>
        <div class="vxr-table-wrap">
            <table class="vxr-table vxrep-wide-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th class="num">{{ $cmp['a_label'] }}</th>
                        <th class="num">{{ $cmp['b_label'] }}</th>
                        <th class="num">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cmp['rows'] as $row)
                        <tr>
                            <td class="strong">{{ $row['label'] }}</td>
                            <td class="num">{{ $fmt($row['a'], $row['money']) }}{{ $row['label'] === 'Profit margin %' && $row['a'] !== null ? '%' : '' }}</td>
                            <td class="num">{{ $fmt($row['b'], $row['money']) }}{{ $row['label'] === 'Profit margin %' && $row['b'] !== null ? '%' : '' }}</td>
                            <td class="num {{ $row['better'] === true ? 'vxr-val-ok' : ($row['better'] === false ? 'vxr-val-bad' : '') }}">
                                @if ($row['change'] === null) —
                                @else
                                    {{ $row['change'] > 0 ? '+' : '' }}{{ $row['label'] === 'Profit margin %' ? number_format($row['change'], 1).' pts' : $fmt($row['change'], $row['money']) }}
                                    @if ($row['change_pct'] !== null)<span class="vxr-val-muted">({{ $row['change_pct'] > 0 ? '+' : '' }}{{ number_format($row['change_pct'], 1) }}%)</span>@endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Channel profit --}}
    @php $cp = $this->channelProfit; $cpTotal = collect($cp); @endphp
    <section class="vxr-card" aria-labelledby="channel-profit-heading">
        <div class="vxr-card-head">
            <div>
                <h3 id="channel-profit-heading" class="vxr-card-title"><x-filament::icon icon="heroicon-o-currency-dollar" /> Channel profit</h3>
                <p class="vxr-card-sub">What each channel actually made in the period above. Giveaway spend is Whatnot's figure, shown for reference — the cost of giveaway items is already in cost of goods, so it is not subtracted again.</p>
            </div>
        </div>
        @if ($cp === [])
            <div class="vxr-empty">No shows in this period.</div>
        @else
            <div class="vxr-table-wrap">
                <table class="vxr-table vxrep-wide-table">
                    <thead>
                        <tr>
                            <th>Channel</th><th class="num">Shows</th><th class="num">Gross</th>
                            <th class="num">Whatnot fees</th><th class="num">Cost of goods</th><th class="num">Pay</th>
                            <th class="num">Tips</th><th class="num">Profit</th><th class="num">Margin</th>
                            <th class="num">Per show</th><th class="num">Giveaway spend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cp as $row)
                            <tr>
                                <td class="strong">{{ $row['channel'] }}</td>
                                <td class="num">{{ number_format($row['shows']) }}</td>
                                <td class="num">{{ $money($row['gross']) }}</td>
                                <td class="num">{{ $money($row['fees']) }}</td>
                                <td class="num">{{ $money($row['cogs']) }}</td>
                                <td class="num">{{ $money($row['pay']) }}</td>
                                <td class="num">{{ $money($row['tips']) }}</td>
                                <td class="num strong {{ $row['profit'] < 0 ? 'vxr-val-bad' : '' }}">{{ $money($row['profit']) }}</td>
                                <td class="num">{{ $row['margin_pct'] === null ? '—' : number_format($row['margin_pct'], 1).'%' }}</td>
                                <td class="num">{{ $row['profit_per_show'] === null ? '—' : $money($row['profit_per_show']) }}</td>
                                <td class="num">{{ $money($row['giveaways']) }}</td>
                            </tr>
                        @endforeach
                        @if (count($cp) > 1)
                            <tr>
                                <td class="strong">All channels</td>
                                <td class="num strong">{{ number_format($cpTotal->sum('shows')) }}</td>
                                @foreach (['gross', 'fees', 'cogs', 'pay', 'tips', 'profit'] as $col)<td class="num strong">{{ $money($cpTotal->sum($col)) }}</td>@endforeach
                                <td></td><td></td>
                                <td class="num strong">{{ $money($cpTotal->sum('giveaways')) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Payout pipeline --}}
    @php $ps = $this->payoutStatusSummary; @endphp
    <section class="vxr-card">
        <div class="vxr-card-head">
            <h3 class="vxr-card-title"><x-filament::icon icon="heroicon-o-banknotes" /> Payout Pipeline</h3>
        </div>
        <div class="vxr-card-body">
            <div class="vxrep-pipe">
                @foreach (['draft' => ['Draft', ''], 'approved' => ['Approved', 'info'], 'paid' => ['Paid', 'ok']] as $key => [$label, $tone])
                    <div>
                        <span class="vxr-pill {{ $tone ? 'vxr-pill--'.$tone : '' }}">{{ $label }}</span>
                        <b style="margin-top:8px">{{ number_format($ps[$key]['count']) }}</b>
                        <span class="vxr-val-muted" style="font-size:13px">{{ $money($ps[$key]['total']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @php $jump = $this->jumpLinks(); @endphp
    @if ($jump !== [])
        <section>
            <h3 class="vxr-card-title" style="margin-bottom:12px"><x-filament::icon icon="heroicon-o-bolt" /> Jump to Report</h3>
            <div class="vxrep-tile-row">
                @foreach ($jump as $link)
                    <a href="{{ $link['url'] }}" class="vxr-linkrow">
                        <span class="vxr-linkrow-icon"><x-filament::icon :icon="$link['icon']" /></span>
                        <span style="min-width:0;color:inherit"><b>{{ $link['label'] }}</b><span>{{ $link['text'] }}</span></span>
                        <x-filament::icon icon="heroicon-m-chevron-right" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
</x-filament-panels::page>

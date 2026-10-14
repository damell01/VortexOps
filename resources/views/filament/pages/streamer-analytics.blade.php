<x-filament-panels::page>
@php
    $rows        = $this->analyticsRows;
    $all         = collect($rows);
    $totalShows  = $all->sum('show_count');
    $totalGross  = $all->sum('gross_revenue');
    $totalNet    = $all->sum('net_revenue');
    $totalMargin = $all->sum('margin');
    $totalPayout = $all->sum('total_payout');
    $totalBal    = $all->sum('balance');
    $totalHrs    = $all->sum('total_hours');
    $avgSph      = $totalHrs > 0 ? round($totalGross / $totalHrs, 2) : 0;
    $streamers   = $this->streamersList;
    $statementsUrl = $this->statementsUrl();
    $initials = function (string $name): string {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $out = count($words) > 1
            ? mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1)
            : mb_substr($words[0] ?? '?', 0, 2);
        return mb_strtoupper($out);
    };
    $avatarTones = ['accent', 'info', 'ok', 'bad', 'warn'];
@endphp

<style>
    .vxsa-page > * { min-width: 0; }
    .vxsa-range { gap: 6px; }
    .vxsa-range input[type=date] { min-width: 0; }
    .vxsa-range input[type=date] { width: 128px; }
    .vxsa-range span.sep { color: var(--vxw-faint); }
    .vxsa-picker { position: relative; min-width: 0; }
    .vxsa-picker-label { display: block; font-size: 12.5px; color: var(--vxw-muted); margin: 0 0 4px; }
    .vxsa-picker-btn { width: 240px; max-width: 100%; justify-content: space-between; cursor: pointer; }
    .vxsa-picker-btn > span { flex: 1; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vxsa-pop { position: absolute; z-index: 40; right: 0; top: calc(100% + 6px); width: 280px; max-width: calc(100vw - 32px); background: var(--vxw-surface); border: 1px solid var(--vxw-border); border-radius: 12px; box-shadow: var(--vxw-shadow-pop); padding: 10px; display: grid; gap: 8px; }
    .vxsa-pop-list { max-height: 260px; overflow-y: auto; }
    .vxsa-pop-foot { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 12.5px; color: var(--vxw-muted); border-top: 1px solid var(--vxw-border); padding-top: 8px; }
    .vxsa-cards { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(215px, 1fr)); }
    .vxsa-card { display: flex; flex-direction: column; gap: 4px; padding: 16px; border: 1px solid var(--vxw-border); border-radius: 12px; background: var(--vxw-surface); min-width: 0; }
    .vxsa-card-top { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; min-width: 0; }
    .vxsa-card-top b { font-size: 15px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vxsa-avatar { flex: none; width: 44px; height: 44px; border-radius: 999px; display: grid; place-items: center; font-weight: 750; font-size: 14px; background: var(--vxr-tone-soft, var(--vxw-accent-soft)); color: var(--vxr-tone, var(--vxw-accent)); }
    .vxsa-card .vxr-dl > div { padding: 8px 0; font-size: 13px; align-items: center; }
    .vxsa-card .vxr-dl dt { line-height: 1.3; }
    .vxsa-card .vxr-link { margin-top: 8px; font-size: 14px; }
    .vxsa-pages { display: flex; flex-wrap: wrap; gap: 6px; }
    .vxsa-pages .vxr-btn { min-width: 36px; padding: 0 10px; }
    .vxsa-pages .is-current { background: var(--vxw-accent); border-color: var(--vxw-accent); color: #fff; }
    .vxsa-grid-head { gap: 10px 14px; }
    .vxsa-grid-head .vxr-search { max-width: 380px; }
    @media (max-width: 640px) {
        /* On phones the shared mobile styles give date inputs their own 48px box,
           so the range drops its wrapper and shows two side-by-side fields. */
        .vxsa-range { width: 100%; height: auto; padding: 0; border: 0; background: none; }
        .vxsa-range > svg { display: none; }
        .vxsa-range input[type=date] { flex: 1 1 0; width: 0; }
        .vxsa-page .vxr-bar { grid-template-columns: minmax(0, 1fr) auto; gap: 6px 12px; }
        .vxsa-page .vxr-bar-track { grid-column: 1 / -1; grid-row: 2; }
        .vxsa-sort { width: 100%; margin-left: 0 !important; }
        .vxsa-sort select { flex: 1; }
        .vxsa-picker, .vxsa-picker-btn { width: 100%; }
        .vxr-actions { width: 100%; }
        .vxsa-grid-head .vxr-search { max-width: none; }
    }
</style>

<div class="vxr vxsa-page">

    {{-- Header: title + filters + actions --}}
    <header class="vxr-head">
        <div>
            <h1 class="vxr-title">Streamer Analytics</h1>
            <p class="vxr-subtitle">{{ $this->subtitle() }}</p>
        </div>

        <div class="vxr-actions" style="align-items:flex-end">
            <label class="vxr-control vxsa-range" title="Date range">
                <x-filament::icon icon="heroicon-o-calendar-days" />
                <input type="date" wire:model.live="dateFrom" aria-label="Date from">
                <span class="sep">–</span>
                <input type="date" wire:model.live="dateTo" aria-label="Date to">
            </label>

            <div class="vxsa-picker" x-data="{ open: false, q: '' }" @click.outside="open = false" @keydown.escape.window="open = false">
                <span class="vxsa-picker-label">Compare streamers</span>
                <button type="button" class="vxr-control vxsa-picker-btn" @click="open = ! open" :aria-expanded="open">
                    <x-filament::icon icon="heroicon-o-users" />
                    <span>
                        @if (count($selectedStreamers) === 0)
                            Full team
                        @elseif (count($selectedStreamers) === 1)
                            {{ $streamers->firstWhere('id', (int) $selectedStreamers[0])?->name ?? '1 selected' }}
                        @else
                            {{ count($selectedStreamers) }} selected
                        @endif
                    </span>
                    <x-filament::icon icon="heroicon-m-chevron-down" />
                </button>
                <div class="vxsa-pop" x-show="open" x-cloak x-transition.opacity>
                    <label class="vxr-control vxr-search">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" />
                        <input type="search" x-model="q" placeholder="Find a streamer…" aria-label="Find a streamer">
                    </label>
                    <div class="vxsa-pop-list">
                        @forelse ($streamers as $s)
                            <label class="vxr-check" wire:key="pick-{{ $s->id }}" x-show="q === '' || @js(mb_strtolower($s->name)).includes(q.toLowerCase())">
                                <input type="checkbox" wire:model.live="selectedStreamers" value="{{ $s->id }}">
                                <span>{{ $s->name }}</span>
                            </label>
                        @empty
                            <p class="vxr-hint">No streamers available.</p>
                        @endforelse
                    </div>
                    <div class="vxsa-pop-foot">
                        <span>Pick people to compare, or none for the full team.</span>
                        @if (count($selectedStreamers))
                            <button type="button" class="vxr-btn vxr-btn--sm" wire:click="clearSelectedStreamers">Full team</button>
                        @endif
                    </div>
                </div>
            </div>

            @if ($statementsUrl)
                <a href="{{ $statementsUrl }}" class="vxr-btn">
                    <x-filament::icon icon="heroicon-o-document-text" /> Streamer Statements
                </a>
            @endif

            <button type="button" wire:click="exportCsv" class="vxr-btn vxr-btn--primary">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" /> Export CSV
            </button>
        </div>
    </header>

    {{-- Tabs --}}
    <nav class="vxr-tabs" role="tablist">
        <button type="button" role="tab" wire:click="$set('activeTab', 'overview')" class="vxr-tab {{ $activeTab === 'overview' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'overview' ? 'true' : 'false' }}">Streamer Overview</button>
        <button type="button" role="tab" wire:click="$set('activeTab', 'weekly')" class="vxr-tab {{ $activeTab === 'weekly' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'weekly' ? 'true' : 'false' }}">Weekly Breakdown</button>
    </nav>

    @if ($activeTab === 'overview')

        <div class="vxr-stats">
            <x-vxr.stat label="Total Shows" :value="number_format($totalShows)" icon="heroicon-o-video-camera"
                :note="count($rows) . ' ' . \Illuminate\Support\Str::plural('streamer', count($rows)) . ' in view'" />
            <x-vxr.stat label="Gross Revenue" :value="'$' . number_format($totalGross, 2)" icon="heroicon-o-currency-dollar"
                :note="$totalNet > 0 ? 'Whatnot est. earnings $' . number_format($totalNet, 2) : null" />
            <x-vxr.stat label="Revenue per Show Hour" :value="'$' . number_format($avgSph, 2)" icon="heroicon-o-chart-bar"
                :note="number_format($totalHrs, 1) . ' show hours'" />
            <x-vxr.stat label="Recorded Payouts" :value="'$' . number_format($totalPayout, 2)" icon="heroicon-o-wallet"
                :note="$totalBal != 0 ? 'Balance $' . number_format(abs($totalBal), 2) . ($totalBal > 0 ? ' owed' : ' overpaid') : null" />
        </div>

        <div class="vxr-banner vxr-banner--accent">
            <x-filament::icon icon="heroicon-o-information-circle" />
            <span>Show hours measure broadcast duration, not hours worked for payroll.</span>
        </div>

        {{-- Top streamers by gross --}}
        @php
            $top = $this->topByGross;
            $topMax = max(1, (float) collect($top)->max('gross_revenue'));
        @endphp
        <section class="vxr-card">
            <div class="vxr-card-head">
                <h2 class="vxr-card-title">Top Streamers by Gross Revenue</h2>
            </div>
            <div class="vxr-card-body">
                @if (empty($top))
                    <div class="vxr-empty"><b>No revenue in this range</b>Pick a wider date range or another streamer.</div>
                @else
                    <div class="vxr-bars">
                        @foreach ($top as $i => $row)
                            <div class="vxr-bar">
                                <span class="vxr-bar-name" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                                <div class="vxr-bar-track"><div class="vxr-bar-fill" style="width: {{ max(2, $row['gross_revenue'] / $topMax * 100) }}%; opacity: {{ 1 - $i * 0.13 }}"></div></div>
                                <span class="vxr-bar-val">${{ number_format($row['gross_revenue'], 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Streamer cards --}}
        @php $cards = $this->cardGrid; @endphp
        <section class="vxr-card">
            <div class="vxr-card-head vxsa-grid-head">
                <h2 class="vxr-card-title">Streamers ({{ count($rows) }})</h2>
                <label class="vxr-control vxr-search">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" />
                    <input type="search" wire:model.live.debounce.300ms="cardSearch" placeholder="Search streamers…" aria-label="Search streamers">
                </label>
                <label class="vxr-toolbar vxsa-sort" style="margin-left:auto;gap:8px;font-size:13.5px;color:var(--vxw-muted);flex-wrap:nowrap;white-space:nowrap">
                    Sort by
                    <select wire:model.live="cardSort" class="vxr-select" aria-label="Sort streamers by">
                        @foreach (\App\Filament\Pages\StreamerAnalytics::CARD_SORTS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="vxr-card-body">
                @if ($cards['total'] === 0)
                    <div class="vxr-empty">
                        @if (empty($rows))
                            <b>No streamers in this view</b>Nobody active matches the current filters.
                        @else
                            <b>No streamer matches “{{ $cardSearch }}”</b>Try another name.
                        @endif
                    </div>
                @else
                    <div class="vxsa-cards">
                        @foreach ($cards['rows'] as $row)
                            <article class="vxsa-card" wire:key="card-{{ $row['streamer_id'] }}">
                                <div class="vxsa-card-top">
                                    <span class="vxsa-avatar vxr-tone-{{ $avatarTones[$row['streamer_id'] % count($avatarTones)] }}">{{ $initials($row['name']) }}</span>
                                    <div style="min-width:0">
                                        <b style="display:block">{{ $row['name'] }}</b>
                                        @if ($row['trend_gross'] !== null)
                                            <span class="vxr-delta {{ $row['trend_gross'] >= 0 ? 'vxr-delta--up' : 'vxr-delta--down' }}" title="Gross revenue vs the same-length period before">
                                                {{ $row['trend_gross'] >= 0 ? '↑' : '↓' }} {{ abs($row['trend_gross']) }}% vs prior
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <dl class="vxr-dl">
                                    <div><dt>Total Shows</dt><dd>{{ number_format($row['show_count']) }}</dd></div>
                                    <div><dt>Gross Revenue</dt><dd>${{ number_format($row['gross_revenue'], 2) }}</dd></div>
                                    <div><dt>Whatnot Estimated Earnings</dt><dd>${{ number_format($row['net_revenue'], 2) }}</dd></div>
                                    <div><dt>Show Hours</dt><dd>{{ $row['total_hours'] > 0 ? number_format($row['total_hours'], 1) : '—' }}</dd></div>
                                    <div><dt>Revenue / Show Hour</dt><dd>{{ $row['gmv_per_hour'] > 0 ? '$' . number_format($row['gmv_per_hour'], 2) : '—' }}</dd></div>
                                </dl>
                                <a class="vxr-link" href="{{ route('filament.admin.resources.streamers.edit', $row['streamer_id']) }}">
                                    View Details <x-filament::icon icon="heroicon-m-arrow-right" />
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
            @if ($cards['total'] > 0)
                <div class="vxr-card-foot">
                    <span>Showing {{ $cards['from'] }} – {{ $cards['to'] }} of {{ $cards['total'] }}</span>
                    @if ($cards['pages'] > 1)
                        <nav class="vxsa-pages" aria-label="Streamer pages">
                            <button type="button" class="vxr-btn vxr-btn--sm" wire:click="goToCardPage({{ $cards['page'] - 1 }})" @disabled($cards['page'] <= 1)>
                                <x-filament::icon icon="heroicon-m-chevron-left" /> Previous
                            </button>
                            @for ($p = 1; $p <= $cards['pages']; $p++)
                                <button type="button" class="vxr-btn vxr-btn--sm {{ $p === $cards['page'] ? 'is-current' : '' }}" wire:click="goToCardPage({{ $p }})" @if($p === $cards['page']) aria-current="page" @endif>{{ $p }}</button>
                            @endfor
                            <button type="button" class="vxr-btn vxr-btn--sm" wire:click="goToCardPage({{ $cards['page'] + 1 }})" @disabled($cards['page'] >= $cards['pages'])>
                                Next <x-filament::icon icon="heroicon-m-chevron-right" />
                            </button>
                        </nav>
                    @endif
                </div>
            @endif
        </section>

        {{-- Full comparison table: every figure the page computes --}}
        <section class="vxr-card">
            <div class="vxr-card-head">
                <div>
                    <h2 class="vxr-card-title">Full Comparison</h2>
                    <p class="vxr-card-sub">{{ $dateFrom }} — {{ $dateTo }} · Ranked by revenue per show hour</p>
                </div>
            </div>
            @if (empty($rows))
                <div class="vxr-empty">No data for the selected period.</div>
            @else
                <div class="vxr-table-wrap">
                    <table class="vxr-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Streamer</th>
                                <th>Type</th>
                                <th class="num">Shows</th>
                                <th class="num">Hours</th>
                                <th class="num">Gross</th>
                                <th class="num">Net</th>
                                <th class="num" title="Revenue minus COGS from sold items with a unit cost recorded.">Margin</th>
                                <th class="num">SPH</th>
                                <th class="num">Tips</th>
                                <th class="num">Payout</th>
                                <th class="num">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                <tr @class(['is-selected' => $i === 0])>
                                    <td class="vxr-val-muted">{{ $i + 1 }}</td>
                                    <td class="strong">
                                        <a class="vxr-link" href="{{ route('filament.admin.resources.streamers.edit', $row['streamer_id']) }}">{{ $row['name'] }}</a>
                                    </td>
                                    <td><span class="vxr-pill">{{ $row['payout_type'] ?? '—' }}</span></td>
                                    <td class="num">{{ $row['show_count'] }}</td>
                                    <td class="num">{{ $row['total_hours'] > 0 ? number_format($row['total_hours'], 1) : '—' }}</td>
                                    <td class="num">
                                        ${{ number_format($row['gross_revenue'], 2) }}
                                        @if ($row['trend_gross'] !== null)
                                            <span class="sub {{ $row['trend_gross'] > 0 ? 'vxr-delta--up' : ($row['trend_gross'] < 0 ? 'vxr-delta--down' : '') }}">
                                                {{ $row['trend_gross'] > 0 ? '↑' : ($row['trend_gross'] < 0 ? '↓' : '→') }} {{ abs($row['trend_gross']) }}% vs prior
                                            </span>
                                        @endif
                                    </td>
                                    <td class="num">{{ $row['net_revenue'] > 0 ? '$' . number_format($row['net_revenue'], 2) : '—' }}</td>
                                    <td class="num strong {{ $row['margin'] < 0 ? 'vxr-val-bad' : '' }}">
                                        ${{ number_format($row['margin'], 2) }}
                                        @if (! is_null($row['margin_pct']))
                                            <span class="sub">{{ $row['margin_pct'] }}%</span>
                                        @endif
                                    </td>
                                    <td class="num strong {{ $row['gmv_per_hour'] > 0 ? 'vxr-val-ok' : 'vxr-val-muted' }}">{{ $row['gmv_per_hour'] > 0 ? '$' . number_format($row['gmv_per_hour'], 2) : '—' }}</td>
                                    <td class="num">{{ $row['tips'] > 0 ? '$' . number_format($row['tips'], 2) : '—' }}</td>
                                    <td class="num">${{ number_format($row['total_payout'], 2) }}</td>
                                    <td class="num {{ $row['balance'] > 0 ? 'vxr-val-warn' : ($row['balance'] < 0 ? 'vxr-val-bad' : 'vxr-val-muted') }}">
                                        @if ($row['balance'] != 0)
                                            {{ $row['balance'] > 0 ? '+' : '-' }}${{ number_format(abs($row['balance']), 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="strong">
                                <td colspan="3" class="strong">Totals</td>
                                <td class="num strong">{{ number_format($totalShows) }}</td>
                                <td class="num strong">{{ number_format($totalHrs, 1) }}</td>
                                <td class="num strong">${{ number_format($totalGross, 2) }}</td>
                                <td class="num strong">${{ number_format($totalNet, 2) }}</td>
                                <td class="num strong {{ $totalMargin < 0 ? 'vxr-val-bad' : '' }}">${{ number_format($totalMargin, 2) }}</td>
                                <td class="num strong">${{ number_format($avgSph, 2) }}</td>
                                <td class="num strong">${{ number_format($all->sum('tips'), 2) }}</td>
                                <td class="num strong">${{ number_format($totalPayout, 2) }}</td>
                                <td class="num strong {{ $totalBal > 0 ? 'vxr-val-warn' : '' }}">${{ number_format(abs($totalBal), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>

    @elseif ($activeTab === 'weekly')

        @php
            $weeklyRows = $this->weeklyRows;
            $weekly     = collect($weeklyRows);
            $bestWeek   = $weekly->sortByDesc('sph')->first();
            $totH       = $weekly->sum('hours');
            $totG       = $weekly->sum('gross');
        @endphp

        <div class="vxr-stats">
            <x-vxr.stat label="Weeks" :value="number_format(count($weeklyRows))" icon="heroicon-o-calendar" />
            <x-vxr.stat label="Total Shows" :value="number_format($weekly->sum('show_count'))" icon="heroicon-o-video-camera" />
            <x-vxr.stat label="Gross Revenue" :value="'$' . number_format($totG, 2)" icon="heroicon-o-currency-dollar" />
            <x-vxr.stat label="Best Week Revenue / Show Hour" :value="$bestWeek ? '$' . number_format($bestWeek['sph'], 2) : '—'" icon="heroicon-o-trophy" tone="ok"
                :note="$bestWeek ? 'Week of ' . $bestWeek['week'] : null" />
        </div>

        <div class="vxr-banner vxr-banner--accent">
            <x-filament::icon icon="heroicon-o-information-circle" />
            <span>Show hours measure broadcast duration, not hours worked for payroll.</span>
        </div>

        @if (! empty($weeklyRows))
            <section class="vxr-card">
                <div class="vxr-card-head">
                    <div>
                        <h2 class="vxr-card-title">Gross Revenue by Week</h2>
                        <p class="vxr-card-sub">Week starting Monday</p>
                    </div>
                </div>
                <div class="vxr-card-body">
                    <x-vxr.chart type="bar" :money="true" :height="240" :points="$weekly->map(fn ($w) => [
                        'label' => $w['week'],
                        'value' => round($w['gross'], 2),
                        'title' => 'Week of ' . $w['week'] . ': $' . number_format($w['gross'], 2) . ' · ' . $w['show_count'] . ' shows',
                    ])->all()" />
                </div>
            </section>
        @endif

        <section class="vxr-card">
            <div class="vxr-card-head">
                <div>
                    <h2 class="vxr-card-title">Weekly Show Performance</h2>
                    <p class="vxr-card-sub">{{ $dateFrom }} — {{ $dateTo }} · SPH = Gross Revenue ÷ Show Hours</p>
                </div>
            </div>
            @if (empty($weeklyRows))
                <div class="vxr-empty">No shows in the selected date range.</div>
            @else
                <div class="vxr-table-wrap">
                    <table class="vxr-table">
                        <thead>
                            <tr>
                                <th>Week Of</th>
                                <th class="num">Shows</th>
                                <th class="num">Hours</th>
                                <th class="num">Gross Revenue</th>
                                <th class="num">Whatnot Net</th>
                                <th class="num">SPH</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($weeklyRows as $row)
                                @php $isBest = $bestWeek && $row['week'] === $bestWeek['week']; @endphp
                                <tr @class(['is-selected' => $isBest])>
                                    <td class="strong">
                                        {{ $row['week'] }}
                                        @if ($isBest) <span class="vxr-pill vxr-pill--ok">best</span> @endif
                                    </td>
                                    <td class="num">{{ $row['show_count'] }}</td>
                                    <td class="num">{{ $row['hours'] > 0 ? number_format($row['hours'], 1) : '—' }}</td>
                                    <td class="num">${{ number_format($row['gross'], 2) }}</td>
                                    <td class="num">{{ $row['net'] > 0 ? '$' . number_format($row['net'], 2) : '—' }}</td>
                                    <td class="num strong {{ $row['sph'] > 0 ? 'vxr-val-ok' : 'vxr-val-muted' }}">{{ $row['sph'] > 0 ? '$' . number_format($row['sph'], 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="strong">Totals</td>
                                <td class="num strong">{{ $weekly->sum('show_count') }}</td>
                                <td class="num strong">{{ number_format($totH, 1) }}</td>
                                <td class="num strong">${{ number_format($totG, 2) }}</td>
                                <td class="num strong">${{ number_format($weekly->sum('net'), 2) }}</td>
                                <td class="num strong">{{ $totH > 0 ? '$' . number_format($totG / $totH, 2) : '—' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>

    @endif

</div>
</x-filament-panels::page>

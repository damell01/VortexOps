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
    .vxsa-page .vxr-title { font-size: clamp(26px, 3vw, 34px); letter-spacing: -.02em; }
    .vxsa-pop { position: absolute; z-index: 40; right: 0; top: calc(100% + 6px); width: 300px; max-width: calc(100vw - 32px); background: var(--vxw-surface); border: 1px solid var(--vxw-border); border-radius: 12px; box-shadow: var(--vxw-shadow-pop); padding: 10px; display: grid; gap: 8px; }
    .vxsa-picker { position: relative; min-width: 0; }
    .vxsa-picker-btn { width: 220px; max-width: 100%; justify-content: space-between; cursor: pointer; }
    .vxsa-picker-btn > span { flex: 1; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vxsa-pop-list { max-height: 260px; overflow-y: auto; }
    .vxsa-pop-foot { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 12.5px; color: var(--vxw-muted); border-top: 1px solid var(--vxw-border); padding-top: 8px; }
    .vxsa-presets { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .vxsa-presets button { height: 34px; border-radius: 8px; border: 1px solid var(--vxw-border); background: var(--vxw-surface); font-size: 13px; color: var(--vxw-text); cursor: pointer; }
    .vxsa-presets button:hover { border-color: var(--vxw-accent-line, var(--vxw-accent)); color: var(--vxw-accent); }
    .vxsa-dates { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .vxsa-dates label { display: grid; gap: 3px; font-size: 12px; color: var(--vxw-muted); }
    .vxsa-dates input { height: 36px; border: 1px solid var(--vxw-border); border-radius: 8px; padding: 0 8px; background: var(--vxw-surface); color: var(--vxw-text); font-size: 13px; min-width: 0; }

    .vxsa-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); background: var(--vxw-surface); border: 1px solid var(--vxw-border); border-radius: 14px; box-shadow: var(--vxw-shadow); }
    .vxsa-kpi { display: flex; align-items: center; gap: 16px; padding: 18px 22px; min-width: 0; }
    .vxsa-kpi + .vxsa-kpi { border-left: 1px solid var(--vxw-border); }
    .vxsa-kpi-icon { flex: none; width: 50px; height: 50px; border-radius: 999px; display: grid; place-items: center; background: var(--vxr-tone-soft, var(--vxw-accent-soft)); color: var(--vxr-tone, var(--vxw-accent)); }
    .vxsa-kpi-icon svg { width: 24px; height: 24px; }
    .vxsa-kpi > div > span { display: block; font-size: 13.5px; color: var(--vxw-muted); }
    .vxsa-kpi > div > b { display: block; font-size: clamp(20px, 2vw, 26px); font-weight: 750; letter-spacing: -.01em; font-variant-numeric: tabular-nums; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .vxsa-split { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; }
    .vxsa-seg { display: inline-flex; padding: 3px; border: 1px solid var(--vxw-border); border-radius: 10px; background: var(--vxw-surface); }
    .vxsa-seg button { height: 30px; padding: 0 16px; border-radius: 7px; font-size: 12.5px; color: var(--vxw-muted); cursor: pointer; }
    .vxsa-seg button[aria-pressed=true] { background: var(--vxw-accent); color: #fff; font-weight: 650; }
    .vxsa-topbars { display: grid; gap: 14px; }
    .vxsa-topbar { display: grid; grid-template-columns: minmax(70px, 110px) minmax(0, 1fr) auto; align-items: center; gap: 14px; font-size: 14px; }
    .vxsa-topbar > span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vxsa-topbar-track { height: 16px; border-radius: 6px; background: var(--vxw-sunken); overflow: hidden; }
    .vxsa-topbar-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, var(--vxw-accent), color-mix(in srgb, var(--vxw-accent) 75%, #fff)); transition: width .4s var(--vxw-ease, ease); }
    .vxsa-topbar b { font-weight: 650; font-variant-numeric: tabular-nums; }
    .vxsa-legend { display: flex; flex-wrap: wrap; gap: 6px 18px; font-size: 13.5px; color: var(--vxw-muted); }
    .vxsa-legend i { display: inline-block; width: 11px; height: 11px; border-radius: 999px; margin-right: 6px; vertical-align: -1px; }
    .vxsa-trend { width: 100%; height: auto; display: block; overflow: visible; }
    .vxsa-trend text { font-size: 12px; fill: var(--vxw-muted); }
    .vxsa-trend .grid { stroke: var(--vxw-border); stroke-dasharray: 3 4; }

    .vxsa-perf-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; }
    .vxsa-perf-head h2 { font-size: 20px; font-weight: 750; margin-right: auto; }
    .vxsa-perf-head .vxr-search { width: 260px; max-width: 100%; }
    .vxsa-focus { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 6px 0 12px; border-radius: 999px; background: var(--vxw-accent-soft); color: var(--vxw-accent); font-size: 13px; font-weight: 600; }
    .vxsa-focus button { width: 22px; height: 22px; border-radius: 999px; display: grid; place-items: center; cursor: pointer; }
    .vxsa-focus button:hover { background: color-mix(in srgb, var(--vxw-accent) 16%, transparent); }
    .vxsa-cards { display: grid; gap: 16px; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .vxsa-card { position: relative; display: flex; flex-direction: column; gap: 12px; padding: 16px 18px; border: 1px solid var(--vxw-border); border-radius: 14px; background: var(--vxw-surface); box-shadow: var(--vxw-shadow); min-width: 0; transition: border-color .2s, box-shadow .2s; }
    .vxsa-card:hover { border-color: var(--vxw-accent-line, var(--vxw-border-strong)); }
    .vxsa-card-top { display: flex; align-items: center; gap: 12px; min-width: 0; padding-right: 64px; }
    .vxsa-card-top > div { min-width: 0; }
    .vxsa-card-top b { display: block; font-size: 15.5px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vxsa-card-top small { display: block; font-size: 12.5px; color: var(--vxw-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .vxsa-rank { flex: none; width: 30px; height: 30px; display: grid; place-items: center; font-size: 13px; font-weight: 800; border-radius: 999px; background: var(--vxw-accent-soft); color: var(--vxw-accent); }
    .vxsa-rank--1, .vxsa-rank--2, .vxsa-rank--3 { border-radius: 0; clip-path: polygon(50% 0, 93% 25%, 93% 75%, 50% 100%, 7% 75%, 7% 25%); }
    .vxsa-rank--1 { background: linear-gradient(160deg, #fde68a, #f5b82e); color: #7a4b00; }
    .vxsa-rank--2 { background: linear-gradient(160deg, #e7e9f3, #b9bfd6); color: #3f4663; }
    .vxsa-rank--3 { background: linear-gradient(160deg, #f6d3c2, #d98f6c); color: #6b2f12; }
    .vxsa-avatar { flex: none; width: 42px; height: 42px; border-radius: 999px; display: grid; place-items: center; font-weight: 750; font-size: 14px; background: var(--vxr-tone-soft, var(--vxw-accent-soft)); color: var(--vxr-tone, var(--vxw-accent)); }
    .vxsa-buddy { position: absolute; top: 6px; right: 10px; width: 64px; height: 64px; object-fit: contain; image-rendering: auto; pointer-events: none; filter: drop-shadow(0 3px 4px rgb(0 0 0 / .12)); }
    .vxsa-card-body { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 14px; align-items: end; }
    .vxsa-net span, .vxsa-facts dt { font-size: 12.5px; color: var(--vxw-muted); }
    .vxsa-net b { display: block; font-size: clamp(22px, 2.2vw, 27px); font-weight: 780; letter-spacing: -.01em; font-variant-numeric: tabular-nums; margin: 2px 0 6px; }
    .vxsa-spark { width: 100%; height: 34px; display: block; }
    .vxsa-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 12px; border-left: 1px solid var(--vxw-border); padding-left: 14px; margin: 0; }
    .vxsa-facts dd { margin: 0; font-size: 14px; font-weight: 650; font-variant-numeric: tabular-nums; }
    .vxsa-card-link { align-self: flex-end; display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; font-weight: 650; color: var(--vxw-accent); cursor: pointer; }
    .vxsa-card-link svg { width: 16px; height: 16px; transition: transform .2s; }
    .vxsa-card-link:hover svg { transform: translateX(3px); }
    .vxsa-foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; font-size: 13.5px; color: var(--vxw-muted); }
    .vxsa-pages { display: flex; flex-wrap: wrap; gap: 6px; }
    .vxsa-pages .vxr-btn { min-width: 36px; padding: 0 10px; }
    .vxsa-pages .is-current { background: var(--vxw-accent); border-color: var(--vxw-accent); color: #fff; }

    @media (max-width: 1180px) { .vxsa-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 1024px) {
        .vxsa-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .vxsa-kpi:nth-child(3) { border-left: 0; }
        .vxsa-kpi:nth-child(n+3) { border-top: 1px solid var(--vxw-border); }
        .vxsa-split { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 640px) {
        .vxsa-cards { grid-template-columns: minmax(0, 1fr); }
        .vxsa-card-body { grid-template-columns: minmax(0, 1fr); gap: 10px; }
        .vxsa-facts { border-left: 0; padding-left: 0; border-top: 1px solid var(--vxw-border); padding-top: 10px; }
        .vxsa-trend text { font-size: 20px; }
        .vxsa-kpi { padding: 14px; gap: 10px; }
        .vxsa-kpi-icon { width: 38px; height: 38px; }
        .vxsa-kpi-icon svg { width: 19px; height: 19px; }
        .vxr-actions, .vxsa-picker, .vxsa-picker-btn { width: 100%; }
        .vxsa-perf-head h2 { width: 100%; }
        .vxsa-perf-head .vxr-search { width: 100%; }
        .vxsa-sort { width: 100%; }
        .vxsa-sort select { flex: 1; }
        .vxsa-topbar { grid-template-columns: minmax(0, 1fr) auto; gap: 6px 12px; }
        .vxsa-topbar-track { grid-column: 1 / -1; grid-row: 2; }
    }
</style>

<div class="vxr vxsa-page">

    {{-- Header: title + filters + actions --}}
    <header class="vxr-head">
        <div>
            <h1 class="vxr-title">Streamer analytics</h1>
            <p class="vxr-subtitle">{{ $this->subtitle() }}</p>
        </div>

        <div class="vxr-actions" style="align-items:center">
            <div class="vxsa-picker" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" class="vxr-control vxsa-picker-btn" @click="open = ! open" :aria-expanded="open" aria-label="Date range">
                    <x-filament::icon icon="heroicon-o-calendar-days" />
                    <span>{{ $this->rangeLabel() }}</span>
                    <x-filament::icon icon="heroicon-m-chevron-down" />
                </button>
                <div class="vxsa-pop" x-show="open" x-cloak x-transition.opacity>
                    <div class="vxsa-presets">
                        @foreach (\App\Filament\Pages\StreamerAnalytics::RANGES as $key => $label)
                            <button type="button" wire:click="setRange('{{ $key }}')" @click="open = false">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="vxsa-dates">
                        <label>From<input type="date" wire:model.live="dateFrom" aria-label="Date from"></label>
                        <label>To<input type="date" wire:model.live="dateTo" aria-label="Date to"></label>
                    </div>
                </div>
            </div>

            <div class="vxsa-picker" x-data="{ open: false, q: '' }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" class="vxr-control vxsa-picker-btn" @click="open = ! open" :aria-expanded="open" aria-label="Compare streamers">
                    <x-filament::icon icon="heroicon-o-users" />
                    <span>
                        @if (count($selectedStreamers) === 0)
                            All streamers
                        @elseif (count($selectedStreamers) === 1)
                            {{ $streamers->firstWhere('id', (int) $selectedStreamers[0])?->name ?? '1 selected' }}
                        @else
                            {{ count($selectedStreamers) }} streamers
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
                        <span>Compare streamers, or pick none for everyone.</span>
                        @if (count($selectedStreamers))
                            <button type="button" class="vxr-btn vxr-btn--sm" wire:click="clearSelectedStreamers">Everyone</button>
                        @endif
                    </div>
                </div>
            </div>

            @if ($statementsUrl)
                <a href="{{ $statementsUrl }}" class="vxr-btn">
                    <x-filament::icon icon="heroicon-o-document-text" /> Statements
                </a>
            @endif

            <button type="button" wire:click="exportCsv" class="vxr-btn vxr-btn--primary" title="Export CSV">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" /> Export
            </button>
        </div>
    </header>

    {{-- Tabs --}}
    <nav class="vxr-tabs" role="tablist">
        <button type="button" role="tab" wire:click="$set('activeTab', 'overview')" class="vxr-tab {{ $activeTab === 'overview' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'overview' ? 'true' : 'false' }}">Overview</button>
        <button type="button" role="tab" wire:click="$set('activeTab', 'weekly')" class="vxr-tab {{ $activeTab === 'weekly' ? 'is-active' : '' }}" aria-selected="{{ $activeTab === 'weekly' ? 'true' : 'false' }}">Weekly breakdown</button>
    </nav>

    @if ($activeTab === 'overview')

        <div class="vxsa-kpis">
            @foreach ([
                ['Total shows', number_format($totalShows), 'heroicon-o-calendar', 'accent'],
                ['Gross sales', '$' . number_format($totalGross), 'heroicon-o-currency-dollar', 'ok'],
                ['Whatnot net', '$' . number_format($totalNet), 'heroicon-o-chart-bar', 'accent'],
                ['Revenue / show hour', '$' . number_format($avgSph), 'heroicon-o-clock', 'accent'],
            ] as [$label, $value, $icon, $tone])
                <div class="vxsa-kpi">
                    <span class="vxsa-kpi-icon vxr-tone-{{ $tone }}"><x-filament::icon :icon="$icon" /></span>
                    <div style="min-width:0"><span>{{ $label }}</span><b>{{ $value }}</b></div>
                </div>
            @endforeach
        </div>

        <div class="vxr-banner vxr-banner--accent">
            <x-filament::icon icon="heroicon-o-information-circle" />
            <span>Show hours reflect broadcast duration, not hours worked for payroll.</span>
        </div>

        @php
            $top = $this->topStreamers;
            $topMax = max(1, (float) collect($top)->max('top_value'));
            $topFmt = fn ($v) => '$' . number_format($v) . ($topMetric === 'sph' ? ' / hr' : '');
            // Sales & net trend: one point per week, gross and Whatnot net.
            $trend = array_values($this->weeklyRows);
            $tw = 640; $th = 250; $tl = 52; $tr = 16; $tt = 14; $tb = 34;
            $tMax = max(1, (float) collect($trend)->max(fn ($w) => max($w['gross'], $w['net'])));
            $mag = pow(10, floor(log10($tMax))); $tStep = ceil($tMax / 4 / $mag * 2) / 2 * $mag; $tTop = $tStep * 4;
            $tn = count($trend);
            $tx = fn ($i) => $tl + ($tn > 1 ? $i * ($tw - $tl - $tr) / ($tn - 1) : ($tw - $tl - $tr) / 2);
            $ty = fn ($v) => $tt + (1 - $v / $tTop) * ($th - $tt - $tb);
            $tLine = fn ($key) => collect($trend)->map(fn ($w, $i) => round($tx($i), 1) . ',' . round($ty(max(0, $w[$key])), 1))->implode(' ');
            $tShort = fn ($v) => $v >= 1000 ? '$' . rtrim(rtrim(number_format($v / 1000, 1), '0'), '.') . 'K' : '$' . number_format($v);
            $tEvery = max(1, (int) ceil($tn / 6));
        @endphp
        <div class="vxsa-split">
            <section class="vxr-card">
                <div class="vxr-card-head">
                    <h2 class="vxr-card-title">Top streamers</h2>
                    <div class="vxsa-seg" role="group" aria-label="Rank top streamers by" data-vx-keep-label>
                        @foreach (\App\Filament\Pages\StreamerAnalytics::TOP_METRICS as $key => $label)
                            <button type="button" wire:click="$set('topMetric', '{{ $key }}')" aria-pressed="{{ $topMetric === $key ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="vxr-card-body">
                    @if (empty($top))
                        <div class="vxr-empty"><b>Nothing in this range</b>Pick a wider date range or another streamer.</div>
                    @else
                        <div class="vxsa-topbars">
                            @foreach ($top as $i => $row)
                                <div class="vxsa-topbar" wire:key="top-{{ $topMetric }}-{{ $row['streamer_id'] }}">
                                    <span title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                                    <div class="vxsa-topbar-track"><div class="vxsa-topbar-fill" style="width: {{ max(2, $row['top_value'] / $topMax * 100) }}%"></div></div>
                                    <b>{{ $topFmt($row['top_value']) }}</b>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="vxr-card">
                <div class="vxr-card-head">
                    <h2 class="vxr-card-title">Sales &amp; net trend</h2>
                    <div class="vxsa-legend"><span><i style="background:var(--vxw-accent)"></i>Gross sales</span><span><i style="background:var(--vxw-ok)"></i>Whatnot net</span></div>
                </div>
                <div class="vxr-card-body">
                    @if ($tn === 0)
                        <div class="vxr-empty"><b>No shows in this range</b>The trend fills in week by week.</div>
                    @else
                        <svg class="vxsa-trend" viewBox="0 0 {{ $tw }} {{ $th }}" role="img" aria-label="Gross sales and Whatnot net by week">
                            <defs><linearGradient id="vxsa-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--vxw-accent)" stop-opacity=".16"/><stop offset="1" stop-color="var(--vxw-accent)" stop-opacity="0"/></linearGradient></defs>
                            @for ($g = 0; $g <= 4; $g++)
                                <line class="grid" x1="{{ $tl }}" x2="{{ $tw - $tr }}" y1="{{ $ty($tStep * $g) }}" y2="{{ $ty($tStep * $g) }}" />
                                <text x="{{ $tl - 8 }}" y="{{ $ty($tStep * $g) + 4 }}" text-anchor="end">{{ $tShort($tStep * $g) }}</text>
                            @endfor
                            @if ($tn > 1)
                                <polygon points="{{ $tLine('gross') }} {{ round($tx($tn - 1), 1) }},{{ $ty(0) }} {{ round($tx(0), 1) }},{{ $ty(0) }}" fill="url(#vxsa-fill)" />
                            @endif
                            <polyline points="{{ $tLine('gross') }}" fill="none" stroke="var(--vxw-accent)" stroke-width="2.5" stroke-linejoin="round" />
                            <polyline points="{{ $tLine('net') }}" fill="none" stroke="var(--vxw-ok)" stroke-width="2.5" stroke-linejoin="round" />
                            @foreach ($trend as $i => $w)
                                <circle cx="{{ $tx($i) }}" cy="{{ $ty(max(0, $w['gross'])) }}" r="{{ $tn > 16 ? 2.5 : 4.5 }}" fill="var(--vxw-accent)"><title>Week of {{ $w['week'] }}: gross ${{ number_format($w['gross'], 2) }}</title></circle>
                                <circle cx="{{ $tx($i) }}" cy="{{ $ty(max(0, $w['net'])) }}" r="{{ $tn > 16 ? 2.5 : 4.5 }}" fill="var(--vxw-ok)"><title>Week of {{ $w['week'] }}: Whatnot net ${{ number_format($w['net'], 2) }}</title></circle>
                                @if ($i % $tEvery === 0 || $i === $tn - 1)
                                    <text x="{{ $tx($i) }}" y="{{ $th - 10 }}" text-anchor="{{ $tn > 1 && $i === 0 ? 'start' : ($tn > 1 && $i === $tn - 1 ? 'end' : 'middle') }}">{{ $w['week'] }}</text>
                                @endif
                            @endforeach
                        </svg>
                    @endif
                </div>
            </section>
        </div>

        {{-- Streamer performance cards --}}
        @php
            $cards = $this->cardGrid;
            $focused = count($selectedStreamers) === 1 ? $streamers->firstWhere('id', (int) $selectedStreamers[0]) : null;
            $spark = function (array $vals): array {
                $n = count($vals); $max = max(array_merge([0.01], $vals)); $min = min(array_merge([0], $vals));
                $pts = []; foreach ($vals as $i => $v) { $pts[] = round($n > 1 ? $i * 160 / ($n - 1) : 80, 1) . ',' . round(30 - ($v - $min) / max(0.01, $max - $min) * 26, 1); }
                return [implode(' ', $pts), implode(' ', $pts) . ' 160,34 0,34'];
            };
        @endphp
        <section style="display:grid;gap:14px">
            <div class="vxsa-perf-head">
                <h2>Streamer performance</h2>
                @if ($focused)
                    <span class="vxsa-focus">{{ $focused->name }} <button type="button" wire:click="clearSelectedStreamers" aria-label="Show every streamer"><x-filament::icon icon="heroicon-m-x-mark" style="width:14px;height:14px" /></button></span>
                @endif
                <label class="vxr-control vxr-search">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" />
                    <input type="search" wire:model.live.debounce.300ms="cardSearch" placeholder="Search streamers…" aria-label="Search streamers">
                </label>
                <label class="vxr-toolbar vxsa-sort" style="gap:8px;font-size:13.5px;color:var(--vxw-muted);flex-wrap:nowrap;white-space:nowrap">
                    Sort by
                    <select wire:model.live="cardSort" class="vxr-select" aria-label="Sort streamers by">
                        @foreach (\App\Filament\Pages\StreamerAnalytics::CARD_SORTS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="vxr-btn" wire:click="$toggle('showComparison')" aria-expanded="{{ $showComparison ? 'true' : 'false' }}" style="color:var(--vxw-accent);border-color:var(--vxw-accent-line, var(--vxw-accent))">
                    <x-filament::icon icon="heroicon-o-chart-bar" /> {{ $showComparison ? 'Hide full comparison' : 'Open full comparison' }}
                </button>
            </div>

            @if ($cards['total'] === 0)
                <div class="vxr-card"><div class="vxr-empty">
                    @if (empty($rows))
                        <b>No streamers in this view</b>Nobody active matches the current filters.
                    @else
                        <b>No streamer matches “{{ $cardSearch }}”</b>Try another name.
                    @endif
                </div></div>
            @else
                <div class="vxsa-cards">
                    @foreach ($cards['rows'] as $row)
                        @php [$sLine, $sArea] = $spark($row['spark'] ?? []); @endphp
                        <article class="vxsa-card" wire:key="card-{{ $row['streamer_id'] }}">
                            <img class="vxsa-buddy" src="/img/pokemon3d/{{ $row['buddy'] }}.gif" alt="" aria-hidden="true" loading="lazy" width="64" height="64">
                            <div class="vxsa-card-top">
                                @if ($row['rank'])
                                    <span class="vxsa-rank vxsa-rank--{{ min($row['rank'], 4) }}" title="Rank by {{ mb_strtolower(\App\Filament\Pages\StreamerAnalytics::CARD_SORTS[$cardSort] ?? '') }}">{{ $row['rank'] }}</span>
                                @endif
                                <span class="vxsa-avatar vxr-tone-{{ $avatarTones[$row['streamer_id'] % count($avatarTones)] }}">{{ $initials($row['name']) }}</span>
                                <div>
                                    <b>{{ $row['name'] }}</b>
                                    <small>{{ $row['business'] ?: 'No business' }}</small>
                                </div>
                            </div>
                            <div class="vxsa-card-body">
                                <div class="vxsa-net">
                                    <span>Whatnot net</span>
                                    <b>${{ number_format($row['net_revenue']) }}</b>
                                    <svg class="vxsa-spark" viewBox="0 0 160 34" preserveAspectRatio="none" aria-hidden="true">
                                        <polygon points="{{ $sArea }}" fill="var(--vxw-accent)" opacity=".1" />
                                        <polyline points="{{ $sLine }}" fill="none" stroke="var(--vxw-accent)" stroke-width="1.8" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                                    </svg>
                                </div>
                                <dl class="vxsa-facts">
                                    <div><dt>Gross sales</dt><dd>${{ number_format($row['gross_revenue']) }}</dd></div>
                                    <div><dt>Shows</dt><dd>{{ number_format($row['show_count']) }}</dd></div>
                                    <div><dt>Show hours</dt><dd>{{ $row['total_hours'] > 0 ? number_format($row['total_hours'], 1) : '—' }}</dd></div>
                                    <div><dt>Sales / hour</dt><dd>{{ $row['gmv_per_hour'] > 0 ? '$' . number_format($row['gmv_per_hour']) : '—' }}</dd></div>
                                </dl>
                            </div>
                            @if (count($selectedStreamers) === 1 && (int) $selectedStreamers[0] === $row['streamer_id'])
                                <a class="vxsa-card-link" href="{{ route('filament.admin.resources.streamers.edit', $row['streamer_id']) }}">Open profile <x-filament::icon icon="heroicon-m-arrow-right" /></a>
                            @else
                                <button type="button" class="vxsa-card-link" wire:click="focusStreamer({{ $row['streamer_id'] }})">View analytics <x-filament::icon icon="heroicon-m-arrow-right" /></button>
                            @endif
                        </article>
                    @endforeach
                </div>
                <div class="vxsa-foot">
                    <span>Showing {{ $cards['from'] }}–{{ $cards['to'] }} of {{ $cards['total'] }} {{ \Illuminate\Support\Str::plural('streamer', $cards['total']) }}</span>
                    @if ($cards['pages'] > 1)
                        @php $nums = collect([1, $cards['page'] - 1, $cards['page'], $cards['page'] + 1, $cards['pages']])->filter(fn ($n) => $n >= 1 && $n <= $cards['pages'])->unique()->sort()->values(); @endphp
                        <nav class="vxsa-pages" aria-label="Streamer pages">
                            @if ($cards['page'] > 1)
                                <button type="button" class="vxr-btn vxr-btn--sm" wire:click="goToCardPage({{ $cards['page'] - 1 }})"><x-filament::icon icon="heroicon-m-arrow-left" /> Previous</button>
                            @endif
                            @foreach ($nums as $i => $p)
                                @if ($i > 0 && $p - $nums[$i - 1] > 1)<span style="align-self:center">…</span>@endif
                                <button type="button" class="vxr-btn vxr-btn--sm {{ $p === $cards['page'] ? 'is-current' : '' }}" wire:click="goToCardPage({{ $p }})" @if($p === $cards['page']) aria-current="page" @endif>{{ $p }}</button>
                            @endforeach
                            @if ($cards['page'] < $cards['pages'])
                                <button type="button" class="vxr-btn vxr-btn--sm" wire:click="goToCardPage({{ $cards['page'] + 1 }})">Next <x-filament::icon icon="heroicon-m-arrow-right" /></button>
                            @endif
                        </nav>
                    @endif
                </div>
            @endif
        </section>

        @if ($showComparison)
            {{-- Full comparison table: every figure the page computes --}}
            <section class="vxr-card" id="vxsa-comparison" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                <div class="vxr-card-head">
                    <div>
                        <h2 class="vxr-card-title">Full comparison</h2>
                        <p class="vxr-card-sub">{{ $dateFrom }} — {{ $dateTo }} · Ranked by revenue per show hour</p>
                    </div>
                    <button type="button" class="vxr-btn vxr-btn--sm" wire:click="$set('showComparison', false)">Close</button>
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
        @endif

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

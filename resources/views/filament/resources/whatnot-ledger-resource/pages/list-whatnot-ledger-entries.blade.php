<x-filament-panels::page>
@php
    $stats = $this->stats;
    $entries = $this->entries;
    $total = $stats['count'];
    $selected = $this->selected;
    $chips = $this->channelChips;
    $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
    $firstRow = $total ? ($this->pageNo - 1) * $perPage + 1 : 0;
    $lastRow = min($total, $this->pageNo * $perPage);
    $money = fn ($v) => ($v < 0 ? '-' : '').'$'.number_format(abs((float) $v), 2);
    $signed = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '-' : '')).'$'.number_format(abs((float) $v), 2);
    $canDelete = $this->canDelete();
    $sortIcon = fn ($col) => $sort === $col ? ($dir === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down') : 'heroicon-m-chevron-up-down';
    // Numbered pager: up to five pages around the current one.
    $start = max(1, min($this->pageNo - 2, $lastPage - 4));
    $pages = range($start, min($lastPage, $start + 4));
@endphp

<style>
    .vxled { grid-template-columns: minmax(0, 1fr); }
    .vxled-visuals { display:grid; grid-template-columns:minmax(0,1.65fr) minmax(260px,1fr); gap:12px; margin:12px 0; }
    .vxled-visual { min-width:0; padding:16px; border:1px solid var(--vxw-border); border-radius:16px; background:var(--vxw-surface); }
    .vxled-visual h2 { font-weight:750; font-size:15px; color:var(--vxw-text); }
    .vxled-trend-legend { display:flex; gap:12px; flex-wrap:wrap; font-size:12px; color:var(--vxw-muted); }
    .vxled-legend-dot { display:inline-block; height:9px; width:9px; border-radius:100%; margin-right:5px; }
    .vxled-donut { width:150px; height:150px; flex:none; border-radius:50%; display:grid; place-items:center; }
    .vxled-donut-hole { width:102px; height:102px; border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; background:var(--vxw-surface); color:var(--vxw-text); }
    .vxled-category-list { min-width:0; display:grid; gap:8px; flex:1; }
    .vxled-category-row { display:flex; justify-content:space-between; gap:8px; font-size:12px; }
    .vxled-category-row span:first-child { min-width:0; overflow-wrap:anywhere; }
    @media (max-width:960px) { .vxled-visuals { grid-template-columns:minmax(0,1fr); } }
    @media (max-width:480px) { .vxled-visual { padding:12px; } .vxled-category-layout { flex-direction:column; align-items:center; } }

    /* Ledger workspace: compact, responsive controls with Vortex purple accents. */
    .vxled .vxr-head { padding: 18px 20px; border: 1px solid var(--vxw-border); border-radius: 18px; background: linear-gradient(110deg,rgba(95,39,180,.14),rgba(128,71,245,.04) 65%,transparent); }
    .vxled .vxr-stats { margin-block: 12px; }
    .vxled-workspace { display: grid; grid-template-columns: minmax(0,1fr) minmax(240px,300px); gap: 12px; margin-bottom: 14px; }
    .vxled-workspace-card { min-width: 0; border: 1px solid var(--vxw-border); background: var(--vxw-surface); border-radius: 16px; padding: 16px; }
    .vxled-workspace-title { font-size: 15px; font-weight: 750; margin-bottom: 10px; color: var(--vxw-text); }
    .vxled-actions { display: grid; gap: 8px; }
    .vxled-action { display: flex; justify-content: space-between; align-items: center; gap: 8px; min-height: 40px; width: 100%; padding: 8px 12px; border: 1px solid var(--vxw-border); border-radius: 10px; background: var(--vxw-surface); font-weight: 650; font-size: 13px; color: var(--vxw-text); text-align: left; }
    .vxled-action:hover, .vxled-action:focus-visible { border-color: var(--vxw-accent); outline-offset: 2px; }
    .vxled-action-count { font-variant-numeric: tabular-nums; border-radius: 999px; padding: 2px 8px; background: var(--vxw-warn-soft); color: var(--vxw-warn); }
    .vxled-review-tabs { display: flex; gap: 8px; overflow-x: auto; padding: 3px 0 10px; scrollbar-width: thin; }
    .vxled-review-tab { flex: none; padding: 9px 13px; min-height: 40px; border-radius: 11px; border: 1px solid var(--vxw-border); background: var(--vxw-surface); color: var(--vxw-text); font-size: 13px; font-weight: 650; }
    .vxled-review-tab[aria-pressed="true"] { background: var(--vxw-accent); color: white; border-color: var(--vxw-accent); }
    @media (max-width: 900px) { .vxled-workspace { grid-template-columns: minmax(0,1fr); } }
    @media (max-width: 640px) { .vxled .vxr-head { padding: 14px; } .vxled-workspace-card { padding: 12px; } .vxled-review-tab { padding-inline: 10px; } }

    /* The phone select picker (mobile-controls.js) sits inside our control; let the control be the box. */
    @media (max-width: 767px) {
        .vxled .vxr-control { height: auto; min-height: 48px; }
        .vxled .vxr-control .vx-picker-trigger { border: 0; background: transparent; min-height: 44px; padding: 0; font-size: 15px; flex: 1 1 auto; min-width: 0; }
    }
    .vxled-chips { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
    .vxled-chip { flex: none; display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px; border-radius: 10px; border: 1px solid var(--vxw-border); background: var(--vxw-surface); color: var(--vxw-text); font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .vxled-chip:hover { border-color: var(--vxw-accent-line); }
    .vxled-chip.is-active { background: var(--vxw-accent); border-color: var(--vxw-accent); color: #fff; }
    .vxled-chip .vxr-count { background: var(--vxw-warn-soft); color: var(--vxw-warn); }
    .vxled-toolbar > .vxr-control { flex: 1 1 200px; }
    .vxled-toolbar > .vxr-control select, .vxled-toolbar > .vxr-control input[type=date] { width: 100%; }
    .vxled-toolbar > .vxr-search { flex: 2 1 260px; }
    .vxled-amt { font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .vxled-details { max-width: 320px; overflow-wrap: anywhere; }
    .vxled-table .vxled-date { white-space: nowrap; }
    .vxled-table .vxled-details { min-width: 160px; }
    .vxled-table .vxled-check { width: 1%; padding-right: 0; }
    .vxled-pages { display: inline-flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .vxled-foot-tools { display: inline-flex; gap: 10px; align-items: center; flex-wrap: wrap; min-width: 0; max-width: 100%; }
    .vxled-pages button { min-width: 34px; height: 34px; padding: 0 8px; border-radius: 8px; border: 1px solid var(--vxw-border); background: var(--vxw-surface); color: var(--vxw-text); font-size: 13.5px; font-weight: 600; cursor: pointer; display: inline-grid; place-items: center; }
    .vxled-pages button.is-active { background: var(--vxw-accent); border-color: var(--vxw-accent); color: #fff; }
    .vxled-pages button[disabled] { opacity: .45; cursor: default; }
    .vxled-pages svg { width: 16px; height: 16px; }
    /* Right-hand review drawer: preserve the ledger table behind it, give the
       transaction a full-height reading area, and keep scrolling inside it. */
    .vxled-panel {
        position: fixed; z-index: 1000; inset: 0 0 0 auto;
        top: 0; left: auto; right: 0; transform: none;
        width: min(860px, 90vw); height: 100dvh; max-height: 100dvh;
        overflow-y: auto; overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        border-radius: 20px 0 0 20px;
        box-shadow: -20px 0 70px -20px rgb(10 10 30 / .45);
    }
    .vxled-panel .vxr-card-body { padding: 0 24px 32px; }
    .vxled-panel .vxr-drawer-head {
        position: sticky; top: 0; z-index: 2;
        background: var(--vxw-surface); border-bottom: 1px solid var(--vxw-border);
    }
    @media (max-width: 767px) {
        .vxled-panel { width: 100vw; max-width: 100vw; border-radius: 0; }
        .vxled-panel .vxr-card-body { padding: 0 16px 32px; }
    }
    .vxled-panel .vxr-drawer-head { padding: 18px 20px 12px; border-bottom: 0; }
    .vxled-panel-amt { font-size: 30px; font-weight: 750; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .vxled-panel .vxr-dl dd { text-align: right; overflow-wrap: anywhere; min-width: 0; }
    .vxled-panel .vxr-dl dd.vxled-left { text-align: left; }
    .vxled-copy { display: inline-flex; align-items: center; gap: 10px; }


    /* Financial workspace: put transaction review first and keep secondary analytics optional. */
    .vxled-analytics > summary{list-style-position:inside;padding:6px 4px}
    .vxled-analytics[open] > summary{margin-bottom:8px}
    .vxled .vxr-head{padding:12px 16px}
    .vxled-quick-actions{margin:8px 0;padding:10px 12px}
    .vxled-more-filters > summary{list-style-position:inside}
    .vxled-more-filters .vxled-toolbar{border:0;padding:0;box-shadow:none}
    .vxled-quick-actions .vxled-actions{grid-template-columns:repeat(4,minmax(0,1fr))}
    @media(max-width:900px){.vxled-quick-actions .vxled-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:540px){.vxled-quick-actions .vxled-actions{grid-template-columns:minmax(0,1fr)}}
    .vxled .vxr-stats{margin-block:8px}
    .vxled .vxr-head{background:linear-gradient(115deg,rgba(109,40,217,.11),rgba(167,139,250,.035));border-radius:18px}
    .vxled-coverage > div{box-shadow:0 2px 7px rgba(38,15,83,.055);border-radius:14px;padding:10px 12px}
    .vxled-coverage > div > div:last-child{font-size:clamp(18px,1.55vw,24px);font-weight:800;letter-spacing:-.025em}
    .vxled .vxr-stats{gap:8px}
    .vxled .vxr-toolbar{gap:8px}
    .vxled .vxr-table th,.vxled .vxr-table td{padding-top:9px;padding-bottom:9px}
    .vxled-review-tabs{margin:8px 0 4px}
    .vxled-review-tab[aria-pressed="true"]{box-shadow:0 3px 9px rgba(109,40,217,.22)}
    .vxled-quick-actions{padding:8px 12px}
    .vxled-quick-actions > summary{margin:0}
    .vxled-quick-actions[open] > summary{margin-bottom:10px}
    @media(min-width:1280px){.vxled-coverage{grid-template-columns:repeat(6,minmax(0,1fr))}}
    @media(max-width:640px){.vxled-coverage > div{padding:9px}.vxled .vxr-table th,.vxled .vxr-table td{padding:9px 10px}}
    .vxled .vxled-workspace{gap:8px;margin-bottom:8px}
    .vxled .vxled-visuals{margin:8px 0}
    .vxled .vxr-table-wrap{overflow-x:auto}
    .vxled .vxled-table thead th{position:sticky;top:0;z-index:1}
    @media(max-width:767px){.vxled .vxled-workspace{grid-template-columns:minmax(0,1fr)}.vxled .vxled-visuals{grid-template-columns:minmax(0,1fr)}}
    /* Compact ledger inspector: a narrow right rail, not a page-covering modal.
       Use !important to beat shared .vxr-card / .vxr-drawer modal rules. */
    .vxled-portal .vxled-panel {
        position: fixed !important;
        inset: 182px 0 0 auto !important;
        top: 182px !important;
        right: 0 !important;
        bottom: 0 !important;
        left: auto !important;
        transform: none !important;
        width: min(440px, 38vw) !important;
        min-width: 360px;
        max-width: calc(100vw - 24px) !important;
        height: calc(100dvh - 182px) !important;
        max-height: calc(100dvh - 182px) !important;
        border-radius: 16px 0 0 0 !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        box-shadow: -12px 0 38px rgba(24, 12, 55, .18) !important;
        z-index: 1001 !important;
    }
    .vxled-portal .vxled-backdrop {
        position: fixed;
        inset: 182px 0 0 0;
        background: rgba(17, 12, 37, .13) !important;
        z-index: 1000;
    }
    .vxled-portal .vxled-panel .vxr-drawer-head {
        position: sticky; top: 0; z-index: 3;
        padding: 12px 16px !important;
        border-bottom: 1px solid var(--vxw-border) !important;
        background: var(--vxw-surface) !important;
    }
    .vxled-portal .vxled-panel .vxr-card-body { padding: 12px 16px 28px !important; gap: 10px !important; }
    .vxled-portal .vxled-panel .vxled-panel-amt { font-size: 25px; }
    .vxled-portal .vxled-panel .vxr-dl > div { gap: 8px; }
    .vxled-portal .vxled-panel .vxr-dl dd { max-width: 65%; }
    @media (max-width: 767px) {
        .vxled-portal .vxled-panel {
            inset: 0 0 0 auto !important; top: 0 !important; bottom: 0 !important;
            width: min(440px, 100vw) !important; min-width: 0;
            max-width: 100vw !important; height: 100dvh !important;
            max-height: 100dvh !important; border-radius: 0 !important;
        }
        .vxled-portal .vxled-backdrop { inset: 0; }
    }
    .vxled-portal { position: relative; z-index: 1100; }
    .vxled-portal .vxled-panel { margin: 0 !important; }
</style>

<div class="vxr vxled">
    <div class="mb-3" style="display:flex;justify-content:flex-end">
        <a href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('tips') }}" class="vxr-btn vxr-btn--outline">Streamer tip totals &amp; show breakdown</a>
    </div>
    @if($review === 'tips_needs_business')
        <section class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-gray-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-gray-100" aria-label="Tip business attribution review">
            <h2 class="font-bold">Tips awaiting business verification</h2>
            <p class="mt-1">Only tip transactions without an approved business assignment are shown. Open a row to inspect its source order, any verified show suggestion, and the attribution approval controls. A channel alone does not prove business ownership.</p>
            <p class="mt-1 text-xs">Submitting an attribution requires a reason and a separate administrator's approval. This review does not post payroll.</p>
            <button type="button" class="mt-2 rounded-lg border border-amber-400 px-3 py-1.5 font-semibold hover:bg-amber-100 dark:hover:bg-amber-900/40" wire:click="$set('review', 'all')">Show all ledger entries</button>
        </section>
    @endif
    <div class="vxr-head">
        <div>
            <h1 class="vxr-title">Whatnot Ledger</h1>
            <p class="vxr-subtitle">Reconcile credits, debits and transaction status by channel.</p>
        </div>
        <div class="vxr-actions">
            <a class="vxr-btn vxr-btn--outline" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('tips') }}">Tip totals</a>
            <a class="vxr-btn vxr-btn--outline" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('charges') }}">Charges</a>
            @if ($canDelete && count($checked))
                <button type="button" class="vxr-btn" style="color:var(--vxw-bad)" wire:click="deleteChecked"
                    wire:confirm="Delete {{ count($checked) }} ledger {{ Str::plural('entry', count($checked)) }}? This cannot be undone.">
                    <x-filament::icon icon="heroicon-o-trash" /> Delete selected ({{ count($checked) }})
                </button>
            @endif
            <button type="button" class="vxr-btn vxr-btn--outline" wire:click="exportCsv" title="Download every row the filters select (up to {{ number_format($this::CSV_ROW_LIMIT) }})">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" /> Export CSV
            </button>
        </div>
    </div>

    @php $allocation = $loadReports ? $this->attributionSummary : []; @endphp
    @if (! $loadReports)
        <div class="mb-3 text-sm" style="color:var(--vxw-muted)">Reconciliation totals and analytics are paused to keep the ledger responsive. <button type="button" class="vxr-btn vxr-btn--outline" wire:click="loadReports">Load reconciliation reports</button></div>
    @else
    <div class="vxled-coverage mb-3 grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6" aria-label="Ledger attribution coverage">
        @php
            $coverageMetrics = [
                ['label'=>'Unmatched', 'key'=>'unmatched'],
                ['label'=>'Pending review', 'key'=>'pending'],
                ['label'=>'Rejected', 'key'=>'rejected'],
                ['label'=>'Assigned to shows', 'key'=>'shows'],
                ['label'=>'Assigned to streamers', 'key'=>'streamers'],
                ['label'=>'House / business', 'key'=>'house'],
            ];
        @endphp
        @foreach ($coverageMetrics as $metric)
            <div class="rounded-xl border p-3" style="border-color:var(--vxw-border);background:var(--vxw-surface)">
                <div class="text-xs" style="color:var(--vxw-muted)">{{ $metric['label'] }}</div>
                <div class="mt-1 text-xl font-bold tabular-nums">{{ number_format($allocation[$metric['key']] ?? 0) }}</div>
            </div>
        @endforeach
    </div>
    @php $unallocated = $this->unallocatedAmounts; @endphp
    @if ($unallocated['entries'] > 0)
        <div class="rounded-xl border p-3 mb-3" style="border-color:var(--vxw-border);background:var(--vxw-surface)" role="status">
            <strong>Unallocated ledger value: {{ $signed($unallocated['net']) }}</strong>
            <span class="text-sm"> across {{ number_format($unallocated['entries']) }} transactions in the selected filters. This is a signed net amount, not a loss estimate; pending and rejected allocations are included until approved.</span>
        </div>
    @endif

    @endif

    <div class="vxr-stats">
        <x-vxr.stat label="Credits" :value="$money($stats['credits'])" icon="heroicon-o-arrow-up" tone="ok" />
        <x-vxr.stat label="Debits" :value="$money($stats['debits'])" icon="heroicon-o-arrow-down" tone="bad" />
        <x-vxr.stat label="Net Activity" :value="$money($stats['net'])" icon="heroicon-o-equals" tone="accent"
            :value-class="$stats['net'] < 0 ? 'vxr-val-bad' : ''" />
        <x-vxr.stat label="Transactions" :value="number_format($stats['count'])"
            icon="heroicon-o-rectangle-stack" tone="accent" :note="number_format($stats['processing']).' processing or pending'" />
    </div>

    @if (($allocation['unmatched'] ?? 0) + ($allocation['pending'] ?? 0) + ($allocation['rejected'] ?? 0) > 0)
        <div class="vxw-alert vxw-alert--warn" role="status">
            <div>
                <strong>Ledger entries awaiting allocation review</strong>
                <p>{{ number_format($allocation['unmatched'] ?? 0) }} unmatched · {{ number_format($allocation['pending'] ?? 0) }} pending approval · {{ number_format($allocation['rejected'] ?? 0) }} rejected. Assign to a show, streamer, or house as appropriate; house expenses do not require a show.</p>
                <button type="button" class="vxr-link" wire:click="$set('review', 'needs_attribution')">Review entries</button>
            </div>
        </div>
    @endif

        <div class="vxled-chips" role="tablist" aria-label="Channel">
        @foreach ($chips as $chip)
            <button type="button" role="tab" wire:key="chip-{{ $chip['key'] }}"
                class="vxled-chip {{ $channel === $chip['key'] ? 'is-active' : '' }}"
                aria-selected="{{ $channel === $chip['key'] ? 'true' : 'false' }}"
                wire:click="setChannel('{{ $chip['key'] }}')">
                {{ $chip['label'] }}
                @if ($chip['count'])
                    <span class="vxr-count">{{ $chip['count'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <nav class="vxled-review-tabs" aria-label="Ledger transaction review filters">
        @foreach ([
            'all' => 'All transactions',
            'needs_attribution' => 'Needs review',
            'unverified_tips' => 'Unverified tips',
            'pending_approval' => 'Pending approval',
            'rejected_attribution' => 'Rejected',
            'tips_missing_streamer' => 'Missing streamer',
            'tips_missing_order' => 'Missing order',
        ] as $reviewKey => $reviewLabel)
            <button type="button" class="vxled-review-tab" aria-pressed="{{ $review === $reviewKey ? 'true' : 'false' }}" wire:click="$set('review', '{{ $reviewKey }}')">{{ $reviewLabel }}</button>
        @endforeach
    </nav>
    <div class="vxr-toolbar vxr-toolbar--card vxled-toolbar">
        <label class="vxr-control">
            <x-filament::icon icon="heroicon-o-calendar-days" />
            <select wire:model.live="preset" aria-label="Date range">
                @foreach ($this::PRESETS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($preset === 'custom')
            <label class="vxr-control" title="From">
                <span class="vxr-val-muted" style="font-size:13px">From</span>
                <input type="date" wire:model.live="from" aria-label="From date">
            </label>
            <label class="vxr-control" title="Until">
                <span class="vxr-val-muted" style="font-size:13px">To</span>
                <input type="date" wire:model.live="until" aria-label="Until date">
            </label>
        @endif
        <label class="vxr-control vxr-search">
            <x-filament::icon icon="heroicon-o-magnifying-glass" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search order or details…" aria-label="Search order or details">
        </label>
    </div>
    <details class="vxled-more-filters mb-3 rounded-xl border p-3" style="border-color:var(--vxw-border);background:var(--vxw-surface)">
        <summary class="cursor-pointer text-sm font-semibold">More filters — show, streamer, destination, transaction type and status</summary>
        <div class="vxr-toolbar vxled-toolbar mt-3">
        <label class="vxr-control">
            <x-filament::icon icon="heroicon-o-video-camera" />
            <select wire:model.live="showFilter" aria-label="Filter ledger by show">
                <option value="">All shows (approved attribution)</option>
                @foreach ($this->showFilterOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label class="vxr-control">
            <x-filament::icon icon="heroicon-o-user" />
            <select wire:model.live="streamerFilter" aria-label="Filter ledger by streamer">
                <option value="">All streamers (approved attribution)</option>
                @foreach ($this->streamerFilterOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label class="vxr-control">
            <select wire:model.live="categoryFilter" aria-label="Ledger category">
                <option value="">All categories</option>
                @foreach (\App\Services\LedgerAttributionService::CATEGORIES as $category)
                    <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                @endforeach
            </select>
        </label>
        <label class="vxr-control">
            <select wire:model.live="destinationFilter" aria-label="Ledger allocation destination">
                <option value="">All destinations</option>
                <option value="show">Show</option>
                <option value="streamer">Streamer</option>
                <option value="house">House</option>
                <option value="unassigned">Explicitly unassigned</option>
            </select>
        </label>
        <label class="vxr-control">
            <x-filament::icon icon="heroicon-o-list-bullet" />
            <select wire:model.live="type" aria-label="Transaction type">
                <option value="">All transaction types</option>
                @foreach ($this->typeOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        <label class="vxr-control">
            <x-filament::icon icon="heroicon-o-check-circle" />
            <select wire:model.live="status" aria-label="Status">
                <option value="">All statuses</option>
                @foreach ($this->statusOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        </div>
    </details>

    <details class="vxled-workspace-card vxled-quick-actions">
            <summary class="vxled-workspace-title cursor-pointer">Quick actions &amp; review shortcuts</summary>
            <div class="vxled-actions">
                <button type="button" class="vxled-action" wire:click="$set('review', 'needs_attribution')"><span>Review unallocated</span><span class="vxled-action-count">{{ number_format(($allocation['unmatched'] ?? 0) + ($allocation['pending'] ?? 0) + ($allocation['rejected'] ?? 0)) }}</span></button>
                <button type="button" class="vxled-action" wire:click="$set('review', 'unverified_tips')"><span>Unverified show / streamer tips</span><span aria-hidden="true">›</span></button>
                <button type="button" class="vxled-action" wire:click="$set('review', 'tips_missing_streamer')"><span>Tips missing streamer</span><span aria-hidden="true">›</span></button>
                <a class="vxled-action" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('tips') }}"><span>Tip totals &amp; show breakdown</span><span aria-hidden="true">›</span></a>
                <a class="vxled-action" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('charges') }}"><span>Ledger charges (chargebacks) by streamer</span><span aria-hidden="true">›</span></a>
            </div>
        </details>
    <div class="vxr-grid">
        <div class="vxr-card">
            <div class="vxr-table-wrap">
                <table class="vxr-table vxled-table">
                    <thead>
                        <tr>
                            @if ($canDelete)
                                <th class="vxled-check"><span class="sr-only">Select</span></th>
                            @endif
                            <th><button type="button" wire:click="sortBy('created_date')">Date <x-filament::icon :icon="$sortIcon('created_date')" class="h-4 w-4" /></button></th>
                            <th class="num"><button type="button" wire:click="sortBy('amount')">Amount <x-filament::icon :icon="$sortIcon('amount')" class="h-4 w-4" /></button></th>
                            <th><button type="button" wire:click="sortBy('transaction_type')">Type <x-filament::icon :icon="$sortIcon('transaction_type')" class="h-4 w-4" /></button></th>
                            <th><button type="button" wire:click="sortBy('status')">Status <x-filament::icon :icon="$sortIcon('status')" class="h-4 w-4" /></button></th>
                            <th><button type="button" wire:click="sortBy('whatnot_order_id')">Order ID <x-filament::icon :icon="$sortIcon('whatnot_order_id')" class="h-4 w-4" /></button></th>
                            <th>Details</th>
                            <th><button type="button" wire:click="sortBy('channel')">Channel <x-filament::icon :icon="$sortIcon('channel')" class="h-4 w-4" /></button></th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($entries->isNotEmpty())
                        @foreach ($entries as $entry)
                            @php $amt = (float) $entry->amount; @endphp
                            <tr wire:key="led-{{ $entry->id }}" class="is-click {{ $selectedId === $entry->id ? 'is-selected' : '' }}"
                                wire:click="select({{ $entry->id }})">
                                @if ($canDelete)
                                    <td class="vxled-check" x-on:click.stop>
                                        <input type="checkbox" value="{{ $entry->id }}" wire:model.live="checked" aria-label="Select entry {{ $entry->id }}">
                                    </td>
                                @endif
                                <td class="vxled-date">
                                    @if ($entry->created_date)
                                        {{ $entry->created_date->format('M j, Y') }}
                                        <span class="sub">{{ $entry->created_date->format('g:i A') }}</span>
                                    @else
                                        <span class="vxr-val-muted">—</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($entry->amount === null)
                                        <span class="vxr-val-muted">—</span>
                                    @else
                                        <span class="vxled-amt {{ $amt < 0 ? 'vxr-val-bad' : 'vxr-val-ok' }}">{{ $signed($amt) }}</span>
                                    @endif
                                </td>
                                <td>{{ $entry->transaction_type ?: '—' }}</td>
                                <td>
                                    @if ($entry->status)
                                        <span class="vxr-pill {{ ($t = $this::statusTone($entry->status)) ? 'vxr-pill--'.$t : '' }}">{{ $entry->status }}</span>
                                    @else
                                        <span class="vxr-val-muted">—</span>
                                    @endif
                                </td>
                                <td style="font-variant-numeric:tabular-nums">{{ $entry->whatnot_order_id ?: '—' }}</td>
                                <td><div class="vxled-details" title="{{ $entry->message }}">{{ Str::limit((string) $entry->message, 120) ?: '—' }}</div></td>
                                <td style="white-space:nowrap">{{ $entry->channel?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                        @else

                            <tr>
                                <td colspan="{{ $canDelete ? 8 : 7 }}">
                                    <div class="vxr-empty">
                                        <b>No Whatnot ledger entries</b>
                                        @if ($preset !== 'all' || $type !== '' || $status !== '' || $showFilter !== '' || $streamerFilter !== '' || $categoryFilter !== '' || $destinationFilter !== '' || trim($search) !== '')
                                            Nothing matches these filters.
                                            <button type="button" class="vxr-link" wire:click="clearFilters">Clear filters</button>
                                        @else
                                            Entries arrive via the nightly ledger import from Whatnot.
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="vxr-card-foot">
                <span>
                    @if ($total)
                        Showing {{ number_format($firstRow) }}–{{ number_format($lastRow) }} of {{ number_format($total) }} {{ Str::plural('transaction', $total) }}
                    @else
                        No transactions
                    @endif
                </span>
                <span class="vxled-foot-tools">
                    <select class="vxr-select" style="height:34px" wire:model.live="perPage" aria-label="Rows per page">
                        @foreach ([10, 25, 50] as $n)
                            <option value="{{ $n }}">{{ $n }} per page</option>
                        @endforeach
                        @if (! in_array($perPage, [10, 25, 50], true))
                            <option value="{{ $perPage }}">{{ $perPage }} per page</option>
                        @endif
                    </select>
                    @if ($lastPage > 1)
                        <nav class="vxled-pages" aria-label="Pages">
                            <button type="button" wire:click="goToPage({{ $this->pageNo - 1 }})" @disabled($this->pageNo <= 1) aria-label="Previous page"><x-filament::icon icon="heroicon-m-chevron-left" /></button>
                            @foreach ($pages as $p)
                                <button type="button" wire:click="goToPage({{ $p }})" class="{{ $p === $this->pageNo ? 'is-active' : '' }}" aria-current="{{ $p === $this->pageNo ? 'page' : 'false' }}">{{ $p }}</button>
                            @endforeach
                            <button type="button" wire:click="goToPage({{ $this->pageNo + 1 }})" @disabled($this->pageNo >= $lastPage) aria-label="Next page"><x-filament::icon icon="heroicon-m-chevron-right" /></button>
                        </nav>
                    @endif
                </span>
            </div>
        </div>

        @if ($selected)
            @php
                $selAmt = (float) $selected->amount;
                $orderUrl = $this::orderUrl($selected);
                $tone = $this::statusTone($selected->status);
            @endphp
            <template x-teleport="body">
              <div class="vxled-portal">
            <div class="vxr-drawer-backdrop vxled-backdrop" wire:click="select(null)"></div>
            <aside class="vxr-card vxled-panel" aria-label="Transaction details" wire:key="panel-{{ $selected->id }}"
                x-data="{ copied: false, copy(v) { navigator.clipboard?.writeText(v); this.copied = true; setTimeout(() => this.copied = false, 1500) } }"
                x-on:keydown.escape.window="$wire.select(null)">
                <div class="vxr-drawer-head">
                    <h2 class="vxr-drawer-title">Transaction Details</h2>
                    <button type="button" class="vxr-x" wire:click="select(null)" aria-label="Close details"><x-filament::icon icon="heroicon-m-x-mark" /></button>
                </div>
                <div class="vxr-card-body" style="padding-top:0;display:grid;gap:14px">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                        <span class="vxled-panel-amt {{ $selected->amount === null ? 'vxr-val-muted' : ($selAmt < 0 ? 'vxr-val-bad' : 'vxr-val-ok') }}">
                            {{ $selected->amount === null ? '—' : $signed($selAmt) }}
                        </span>
                        @if ($selected->status)
                            <span class="vxr-pill {{ $tone ? 'vxr-pill--'.$tone : '' }}">{{ $selected->status }}</span>
                        @endif
                    </div>
                    @php
                        $attribution = $this->selectedAttribution();
                        $linkedShow = $this->selectedShowDetails();
                    @endphp
                    @php $evidence = $this->selectedReviewEvidence(); @endphp
                    <section class="rounded-xl border border-violet-200 bg-violet-50 p-3 text-sm dark:border-violet-800 dark:bg-violet-950/30" aria-label="Attribution evidence">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-bold">Business verification</h3>
                            <span class="rounded-full border border-violet-300 px-2 py-1 text-xs font-semibold dark:border-violet-700">{{ $evidence['status'] ?? 'Needs review' }}</span>
                        </div>
                        <p class="mt-2">{{ $evidence['reason'] ?? 'Inspect this transaction before assigning it.' }}</p>
                        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div><dt class="text-xs font-semibold text-gray-500">Source order</dt><dd class="break-all">{{ $evidence['order'] ?? 'Not provided' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-gray-500">Candidate show</dt><dd>{{ $evidence['show']['title'] ?? 'No verified suggestion' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-gray-500">Business assignment</dt><dd>{{ !empty($evidence['business_approved']) ? 'Approved' : 'Not yet approved' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-gray-500">Review policy</dt><dd>Separate administrator approval required</dd></div>
                        </dl>
                    </section>
                    <section class="rounded-xl border p-3" style="border-color:var(--vxw-border);background:var(--vxw-surface)">
                        <h3 style="font-weight:700;margin-bottom:10px">Show & allocation</h3>
                        <dl class="vxr-dl">
                            <div><dt>Linked show</dt><dd>
                                @if ($linkedShow)
                                    <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $linkedShow['id']]) }}" class="vxr-link" target="_blank" rel="noopener">{{ $linkedShow['title'] }} ↗</a>
                                    <span style="display:block;font-size:12px;color:var(--vxw-muted)">{{ $linkedShow['date'] }} · {{ $linkedShow['source'] }}</span>
                                @else
                                    <span>Not linked — no verified show match</span>
                                @endif
                            </dd></div>
                            <div><dt>Allocation status</dt><dd>{{ ucfirst($attribution->status ?? 'Unassigned') }}</dd></div>
                            <div><dt>Category</dt><dd>{{ ucfirst($attribution->category ?? $this->attributionCategory) }}</dd></div>
                            <div><dt>Destination</dt><dd>{{ ucfirst($attribution->target ?? 'Unassigned') }}</dd></div>
                            @if ($attribution?->streamer_id)
                                <div><dt>Streamer</dt><dd>{{ \App\Models\Streamer::find($attribution->streamer_id)?->name ?? 'Unknown' }}</dd></div>
                            @endif
                            @if ($attribution?->business_id)
                                <div><dt>Business</dt><dd>{{ \App\Models\Business::find($attribution->business_id)?->name ?? 'Unknown' }}</dd></div>
                            @endif
                        </dl>
                        @if ($this->canReconcile())
                            <button type="button" class="vxr-btn vxr-btn--outline" style="margin-top:12px" wire:click="toggleAttributionEditor">{{ $editingAttribution ? 'Hide allocation editor' : 'Edit allocation' }}</button>
                        @endif
                    </section>
                    @if ($this->canReconcile() && $editingAttribution)
                        @php $attribution = $this->selectedAttribution(); @endphp
                        <section class="vxr-card" style="padding:16px;display:grid;gap:12px">
                            <h3 style="font-weight:700">Categorize & assign transaction</h3>
                            <p class="vxr-subtitle">Corrections require approval. Imported amounts and finalized payroll are never edited here.</p>
                            @if($attribution)
                                <p class="vxr-pill">Current proposal: {{ ucfirst($attribution->status) }} · {{ ucfirst($attribution->category) }} → {{ ucfirst($attribution->target) }}</p>
                                @if($attribution->streamer_id)
                                    <p>Attributed streamer: {{ \App\Models\Streamer::find($attribution->streamer_id)?->name ?? 'Unknown' }}</p>
                                @endif
                            @endif
                            <label>Category
                                <select class="vxr-control" wire:model="attributionCategory">
                                    @foreach(\App\Services\LedgerAttributionService::CATEGORIES as $category)
                                        <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Assign to
                                <select class="vxr-control" wire:model.live="attributionTarget">
                                    <option value="unassigned">Unassigned / needs review</option>
                                    <option value="show">Show (uses primary streamer)</option>
                                    <option value="streamer">Streamer directly</option>
                                    <option value="house">House / business</option>
                                </select>
                            </label>
                            @if($attributionTarget === 'show')
                                <label>Show
                                    <select class="vxr-control" wire:model="attributionShowId">
                                        <option value="">Select show</option>
                                        @foreach($this->attributionShows() as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @elseif($attributionTarget === 'streamer')
                                <label>Streamer
                                    <select class="vxr-control" wire:model="attributionStreamerId">
                                        <option value="">Select streamer</option>
                                        @foreach($this->attributionStreamers() as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @elseif($attributionTarget === 'house')
                                <label>Business (optional)
                                    <select class="vxr-control" wire:model="attributionBusinessId">
                                        <option value="">General house</option>
                                        @foreach($this->attributionBusinesses() as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            <label>Reason for correction
                                <textarea class="vxr-control" rows="2" wire:model="attributionReason" placeholder="Why is this the correct category or assignment?"></textarea>
                            </label>
                            @error('attribution')<p style="color:var(--vxw-bad)">{{ $message }}</p>@enderror
                            <button type="button" class="vxr-btn" wire:click="submitAttribution">Submit for approval</button>
                            @if($attribution && $attribution->status === 'pending' && $attribution->submitted_by !== auth()->id())
                                <div style="display:flex;gap:8px">
                                    <button type="button" class="vxr-btn" wire:click="reviewAttribution(true)">Approve</button>
                                    <button type="button" class="vxr-btn vxr-btn--outline" wire:click="reviewAttribution(false)">Reject</button>
                                </div>
                            @endif
                        </section>
                    @endif
                    <dl class="vxr-dl">
                        <div><dt>Created</dt><dd>{{ $selected->created_date?->format('M j, Y \a\t g:i A') ?? '—' }}</dd></div>
                        <div><dt>Completed</dt><dd>{{ $selected->completed_date?->format('M j, Y \a\t g:i A') ?? '—' }}</dd></div>
                        <div>
                            <dt>Order ID</dt>
                            <dd class="vxled-copy">
                                {{ $selected->whatnot_order_id ?: '—' }}
                                @if ($selected->whatnot_order_id)
                                    <button type="button" class="vxr-btn vxr-btn--outline vxr-btn--sm" x-on:click="copy(@js($selected->whatnot_order_id))">
                                        <x-filament::icon icon="heroicon-o-document-duplicate" /> <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                                    </button>
                                @endif
                            </dd>
                        </div>
                        <div><dt>Channel</dt><dd>{{ $selected->channel?->name ?? 'Unassigned' }}</dd></div>
                        <div><dt>Type</dt><dd>{{ $selected->transaction_type ?: '—' }}</dd></div>
                        @if ($selected->listing_id)
                            <div><dt>Listing ID</dt><dd>{{ $selected->listing_id }}</dd></div>
                        @endif
                        <div style="display:grid;gap:4px"><dt>Details</dt><dd class="vxled-left" style="font-weight:500">{{ $selected->message ?: '—' }}</dd></div>
                    </dl>
                </div>
                @if ($orderUrl || $selected->whatnot_order_id)
                    <div class="vxr-drawer-foot" style="justify-content:stretch">
                        @if ($orderUrl)
                            <a href="{{ $orderUrl }}" target="_blank" rel="noopener" class="vxr-btn vxr-btn--outline" style="flex:1">
                                <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" /> View Order
                            </a>
                        @endif
                        @if ($selected->whatnot_order_id)
                            <button type="button" class="vxr-btn vxr-btn--primary" style="flex:1" x-on:click="copy(@js($selected->whatnot_order_id))">
                                <x-filament::icon icon="heroicon-o-document-duplicate" /> <span x-text="copied ? 'Copied' : 'Copy Order ID'">Copy Order ID</span>
                            </button>
                        @endif
                    </div>
                @endif
            </aside>
              </div>
            </template>
        @endif
    </div>
    {{-- The inspector does not need report aggregates. Avoid expensive joins on each row click. --}}
    @if ($selectedId === null && $loadReports)
    @php $byStreamer = $this->streamerTotals; @endphp
    @if ($byStreamer)
        <details class="mb-4 rounded-xl border p-3" style="border-color:var(--vxw-border);background:var(--vxw-surface)">
            <summary class="cursor-pointer text-sm font-semibold">By streamer ({{ count($byStreamer) }}) — for the current filters</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-sm">
                    <thead class="text-xs" style="color:var(--vxw-muted)"><tr><th class="py-1 pr-3">Streamer</th><th class="py-1 pr-3 text-right">Tips</th><th class="py-1 pr-3 text-right">Sales</th><th class="py-1 pr-3 text-right">Refunds</th><th class="py-1 pr-3 text-right">Shipping</th><th class="py-1 pr-3 text-right">Corrections</th><th class="py-1 pr-3 text-right">Bonus</th><th class="py-1 pr-3 text-right">Other</th><th class="py-1 text-right">Net</th></tr></thead>
                    <tbody>
                        @foreach ($byStreamer as $row)
                            <tr class="border-t" style="border-color:var(--vxw-border)">
                                <td class="py-1.5 pr-3 font-medium">{{ $row['streamer'] }} <span class="text-xs" style="color:var(--vxw-muted)">· {{ $row['entries'] }}</span></td>
                                @foreach (['tips', 'sales', 'refunds', 'shipping', 'correction', 'bonus', 'other', 'net'] as $col)
                                    <td class="py-1.5 pr-3 text-right tabular-nums {{ $row[$col] < 0 ? 'vxr-val-bad' : '' }}">${{ number_format($row[$col], 2) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs" style="color:var(--vxw-muted)">Approved matches only. Entries still unmatched or pending review are counted above, not here.</p>
        </details>
    @endif

    @php
        $ledgerCategoryHelp = [
            'tips' => 'Tips paid by buyers during a livestream. Attribute to the verified show, which determines the primary streamer; never House.',
            'sales' => 'Sales and order earnings from Whatnot transactions.',
            'refunds' => 'Refunds and chargebacks that reverse or reduce previous earnings.',
            'fees' => 'Whatnot commissions, processing fees, and other platform fees.',
            'shipping' => 'Postage, shipping charges, carrier adjustments, and shipping refunds.',
            'giveaways' => 'Giveaway-related transactions and charges.',
            'bonus' => 'Bonuses or incentive payments.',
            'marketing' => 'Promotion, advertising, or marketing-related charges.',
            'correction' => 'Adjustments and corrections to earlier ledger activity.',
            'payouts' => 'Transfers or withdrawals of funds. These are not necessarily new sales revenue.',
            'other' => 'Fallback for entries whose source transaction type does not clearly fit another category. Review the original transaction details before reclassifying.',
        ];
    @endphp
    <details class="rounded-xl border border-violet-200 bg-violet-50/70 px-4 py-3 text-sm text-gray-800 shadow-sm dark:border-violet-800 dark:bg-violet-950/20 dark:text-gray-100">
        <summary class="cursor-pointer font-semibold text-violet-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-500 dark:text-violet-200">What do the Ledger categories mean? <span class="font-normal text-gray-600 dark:text-gray-300">— Includes Other</span></summary>
        <p class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-300">Categories describe the transaction type. Attribution separately identifies the show, streamer, business, or house account. An Other entry is not automatically an unmatched or incorrect entry. Category suggestions come from Whatnot transaction types; review supporting evidence before changing them.</p>
        <dl class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (\App\Services\LedgerAttributionService::CATEGORIES as $category)
                <div class="rounded-lg border border-violet-100 bg-white/80 p-3 dark:border-violet-900 dark:bg-gray-900/60">
                    <dt class="font-semibold text-gray-950 dark:text-white">{{ ucfirst($category) }}</dt>
                    <dd class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ $ledgerCategoryHelp[$category] ?? 'Review the transaction details to determine its meaning.' }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="mt-3 rounded-lg border border-violet-200 bg-white/70 p-3 dark:border-violet-800 dark:bg-gray-900/60">
            <p class="text-xs font-semibold text-gray-950 dark:text-white">Where does a transaction belong?</p>
            <p class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-300"><strong>Show:</strong> a verified show, usually with its primary streamer. <strong>Streamer:</strong> directly attributed to a streamer when no show applies. <strong>House:</strong> belongs to the business, such as general marketing, company bonuses or operating fees, rather than a particular show or streamer. <strong>Unassigned:</strong> ownership has not been established. A pending assignment still needs approval.</p>
            <p class="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-300">House is for non-tip business transactions. For example, <strong>House + Marketing</strong> is a business advertising expense; <strong>House + Bonus</strong> is a company-level incentive. House is not a separate transaction type. <strong>Buyer tips must never go to House:</strong> they belong to the verified show and its primary streamer. If the show is not verified, leave the tip unassigned for review.</p>
        </div>
        <button type="button" class="mt-3 inline-flex min-h-10 items-center rounded-lg border border-violet-300 bg-white px-3 text-xs font-semibold text-violet-800 hover:bg-violet-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-500 dark:border-violet-700 dark:bg-gray-900 dark:text-violet-200" wire:click="$set('categoryFilter', 'other')">Review transactions categorized Other</button>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">The category filter searches approved attributions within your current date and channel filters. Entries awaiting attribution appear in the review queue.</p>
        <p class="mt-3 text-xs text-gray-600 dark:text-gray-300">Changing a category does not automatically change payroll or approve a show or streamer attribution.</p>
    </details>

    <details class="vxled-analytics mb-3 rounded-xl border border-violet-200 bg-white/80 p-3 dark:border-violet-800 dark:bg-gray-900/70" aria-label="Ledger analytics and category breakdown">
        <summary class="cursor-pointer text-sm font-semibold text-violet-800 dark:text-violet-200">Analytics &amp; Category Breakdown <span class="ml-2 text-xs font-normal opacity-75">Ledger trend and approved category mix</span></summary>
        @php
        $trend = $this->ledgerTrend;
        $categoryRows = collect($this->categoryBreakdown)->groupBy('category')->map(fn ($items, $category) => ['label' => ucfirst((string) $category), 'count' => $items->sum('entries')])->sortByDesc('count')->values();
        $categoryCount = $categoryRows->sum('count');
        $chartColors = ['#8b5cf6','#38bdf8','#10b981','#f59e0b','#f472b6','#6366f1','#a3e635','#f97316'];
        $donutParts = [];
        $position = 0;
        foreach ($categoryRows as $index => $category) {
            $next = $position + ($categoryCount ? $category['count'] / $categoryCount * 100 : 0);
            $donutParts[] = $chartColors[$index % count($chartColors)].' '.$position.'% '.$next.'%';
            $position = $next;
        }
        $chartWidth = 640; $chartHeight = 180;
        $plotTop = 12; $plotBottom = 148;
        $trendMax = max(1, ...array_merge([1], collect($trend)->flatMap(fn ($row) => [$row['credits'], $row['debits'], abs($row['net'])])->all()));
        $trendPoints = function ($key) use ($trend, $trendMax, $plotTop, $plotBottom) {
            $count = count($trend);
            return collect($trend)->map(function ($row, $i) use ($key, $count, $trendMax, $plotTop, $plotBottom) {
                $x = 34 + ($count <= 1 ? 286 : $i * (572 / ($count - 1)));
                $y = $plotBottom - (max(0, (float) $row[$key]) / $trendMax) * ($plotBottom - $plotTop);
                return round($x, 1).','.round($y, 1);
            })->implode(' ');
        };
    @endphp
    <div class="vxled-visuals">
        <section class="vxled-visual" aria-label="Monthly ledger trend">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2>Ledger trend</h2>
                <div class="vxled-trend-legend">
                    <span><i class="vxled-legend-dot" style="background:#8b5cf6"></i>Credits</span>
                    <span><i class="vxled-legend-dot" style="background:#f472b6"></i>Debits</span>
                    <span><i class="vxled-legend-dot" style="background:#10b981"></i>Positive net</span>
                </div>
            </div>
            @if (count($trend))
                <svg viewBox="0 0 640 180" class="mt-3 w-full" role="img" aria-label="Monthly ledger credits, debits and positive net for up to twelve months">
                    @foreach ([0, .5, 1] as $fraction)
                        <line x1="34" y1="{{ $plotBottom - $fraction * ($plotBottom - $plotTop) }}" x2="606" y2="{{ $plotBottom - $fraction * ($plotBottom - $plotTop) }}" stroke="currentColor" opacity=".12" />
                    @endforeach
                    <polyline points="{{ $trendPoints('credits') }}" fill="none" stroke="#8b5cf6" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline points="{{ $trendPoints('debits') }}" fill="none" stroke="#f472b6" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
                    <polyline points="{{ $trendPoints('net') }}" fill="none" stroke="#10b981" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
                    @foreach ($trend as $i => $point)
                        @if ($i % max(1, (int) ceil(count($trend) / 6)) === 0 || $i === count($trend) - 1)
                            <text x="{{ 34 + (count($trend) <= 1 ? 286 : $i * (572 / (count($trend) - 1))) }}" y="169" text-anchor="middle" font-size="11" fill="currentColor">{{ substr($point['label'], 2) }}</text>
                        @endif
                    @endforeach
                </svg>
                <p class="mt-1 text-xs" style="color:var(--vxw-muted)">Monthly totals · up to 12 latest months · negative net is shown at the baseline, not as a positive amount. Uses current ledger filters.</p>
                <details class="mt-2 text-xs">
                    <summary class="cursor-pointer font-semibold">View exact monthly figures</summary>
                    <div class="mt-2 overflow-x-auto"><table class="w-full text-left"><thead><tr><th class="p-2">Month</th><th class="p-2">Credits</th><th class="p-2">Debits</th><th class="p-2">Net</th></tr></thead><tbody>
                        @foreach ($trend as $point)
                            <tr class="border-t" style="border-color:var(--vxw-border)"><td class="p-2">{{ $point['label'] }}</td><td class="p-2">{{ $money($point['credits']) }}</td><td class="p-2">{{ $money($point['debits']) }}</td><td class="p-2">{{ $signed($point['net']) }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                </details>
            @else
                <p class="mt-4 text-sm" style="color:var(--vxw-muted)">No transactions match the selected filters.</p>
            @endif
        </section>
        <section class="vxled-visual" aria-label="Approved allocations by category">
            <h2>Approved category mix</h2>
            @if ($categoryCount)
                <div class="vxled-category-layout mt-4 flex items-center gap-4">
                    <div class="vxled-donut" style="background:conic-gradient({{ implode(',', $donutParts) }})" role="img" aria-label="Approved transactions grouped by category">
                        <div class="vxled-donut-hole"><strong class="text-xl tabular-nums">{{ number_format($categoryCount) }}</strong><span class="text-xs">Approved</span></div>
                    </div>
                    <div class="vxled-category-list">
                        @foreach ($categoryRows as $index => $category)
                            <div class="vxled-category-row">
                                <span><i class="vxled-legend-dot" style="background:{{ $chartColors[$index % count($chartColors)] }}"></i>{{ $category['label'] }}</span>
                                <strong class="tabular-nums">{{ number_format($category['count']) }} <span style="color:var(--vxw-muted)">({{ number_format($category['count'] / $categoryCount * 100, 1) }}%)</span></strong>
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="mt-3 text-xs" style="color:var(--vxw-muted)">Approved allocations only. Unmatched and pending items are excluded.</p>
            @else
                <p class="mt-4 text-sm" style="color:var(--vxw-muted)">No approved allocations in these filters.</p>
            @endif
        </section>
    </div>
    </details>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-violet-200 bg-violet-50/60 px-3 py-2 dark:border-violet-800 dark:bg-violet-950/20">
        <div class="text-sm">
            <strong>Reconciliation queue</strong>
            <span class="ml-2 text-xs opacity-80">{{ number_format(($allocation['unmatched'] ?? 0) + ($allocation['pending'] ?? 0) + ($allocation['rejected'] ?? 0)) }} entries need attention in these filters</span>
        </div>
        <button type="button" class="vxr-btn vxr-btn--outline" wire:click="$set('review', 'needs_attribution')">Review unassigned &amp; pending</button>
    </div>
    <details class="vxr-card" style="margin-bottom:1rem">
        <summary class="vxr-card-head cursor-pointer"><strong>Approved allocations by category</strong><span class="text-xs" style="color:var(--vxw-muted)">Expand breakdown ▾</span></summary>
        <div class="vxr-table-wrap"><table class="vxr-table">
            <thead><tr><th>Category</th><th>Assigned to</th><th class="num">Transactions</th><th class="num">Net amount</th></tr></thead>
            <tbody>
                @forelse ($this->categoryBreakdown as $group)
                    <tr><td>{{ ucfirst($group->category) }}</td><td>{{ ucfirst($group->target) }}</td><td class="num">{{ number_format($group->entries) }}</td><td class="num">{{ $signed($group->net) }}</td></tr>
                @empty
                    <tr><td colspan="4">No approved allocations in the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </details>

    @endif

</div>
</x-filament-panels::page>

<x-filament-panels::page>
@php
    $data = $this->getViewerData();
    $summary = $data['summary'];
    $items = collect($data['items']);
    $trend = collect($data['trend']);
    $locations = collect($data['locations']);
    $categories = collect($data['category_breakdown']);
    $allRows = $this->getTabRows();
    $pageSize = 25;
    $total = $allRows->count();
    $pageCount = max(1, (int) ceil($total / $pageSize));
    $page = min(max(1, $reportPage), $pageCount);
    $visible = $allRows->slice(($page - 1) * $pageSize, $pageSize)->values();
    $from = $total ? ($page - 1) * $pageSize + 1 : 0;
    $to = min($total, $page * $pageSize);
    $idle = $items->where('quantity', '>', 0);
    $periodLabel = $this->periodStart()->format('M j').' – '.$this->periodEnd()->format('M j, Y');
    $itemUrl = fn ($id, $page = 'view') => \App\Filament\Resources\InventoryItemResource::getUrl($page, ['record' => $id]);
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $itemTabs = ! in_array($activeTab, ['recent', 'moving', 'valuation', 'locations'], true);
    $rowNoun = match ($activeTab) { 'recent' => 'movements', 'valuation' => 'categories', 'locations' => 'locations', default => 'items' };
    $tabCounts = ['low-stock' => $summary['low_stock'], 'out-stock' => $summary['out_stock']];
    $sortHead = function (string $key, string $label, bool $num = false) use ($reportSort) {
        $on = $reportSort === $key;
        return '<button type="button" wire:click="sortBy(\''.$key.'\')" class="'.($on ? 'invr-sorted' : '').'">'.e($label).' <span aria-hidden="true">'.($on ? '↓' : '↕').'</span></button>';
    };
@endphp

<style>
    .invr-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .invr-filters .vxr-control { flex: 0 1 260px; }
    .invr-filters .vxr-control select { flex: 1; width: 100%; }
    .invr-filters .invr-period { flex: 0 1 300px; }
    /* On phones every select becomes a picker button; inside a control the control is the box. */
    .invr-filters .vxr-control .vx-picker-trigger, .invr-tools .vxr-control .vx-picker-trigger { flex: 1; min-width: 0; min-height: 0; height: 36px; padding: 0; border: 0; background: transparent; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    @media (max-width: 767px) { .invr-filters .vxr-control:has(.vx-picker-trigger), .invr-tools .vxr-control:has(.vx-picker-trigger) { flex: 1 1 100%; } }
    .invr-filters .vxr-control select, .invr-tools .vxr-control select { border: 0 !important; background-color: transparent !important; box-shadow: none !important; min-height: 0 !important; height: 36px !important; padding: 0 26px 0 0 !important; font-size: 14px !important; width: 100%; }
    .invr-filters .invr-period select { min-width: 120px; }
    .invr-period-label { font-size: 14px; color: var(--vxw-muted); padding: 0 4px 0 2px; white-space: nowrap; }
    .invr-filters .vxr-foot-note { margin-left: auto; }
    .invr-more { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); padding-top: 12px; margin-top: 12px; border-top: 1px solid var(--vxw-border); width: 100%; }
    .invr-more label { display: grid; gap: 6px; font-size: 13px; color: var(--vxw-muted); font-weight: 600; }
    .invr-range { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .invr-range input, .invr-dates input { height: 40px; border: 1px solid var(--vxw-border); border-radius: 10px; background: var(--vxw-surface); color: var(--vxw-text); font-size: 14px; padding: 0 10px; min-width: 0; width: 100%; }
    .invr-dates { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 13px; color: var(--vxw-muted); }
    .invr-dates input { width: auto; }
    .invr-err { color: var(--vxw-bad); font-size: 12.5px; }
    .invr-strip button { background: none; border: 0; cursor: pointer; text-align: left; color: inherit; justify-content: center; }
    .invr-strip button:hover { background: var(--vxw-surface-2); }
    .invr-strip .vxr-stat-icon { width: 44px; height: 44px; }
    .invr-strip .vxr-stat-icon svg { width: 23px; height: 23px; }
    .invr-strip b { display: block; font-size: 22px; font-weight: 750; font-variant-numeric: tabular-nums; }
    .invr-strip small { font-size: 13.5px; color: var(--vxw-muted); }
    .invr-buckets { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0; }
    .invr-bucket { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; padding: 4px 14px; min-width: 0; }
    .invr-bucket:first-child { padding-left: 0; }
    .invr-bucket + .invr-bucket { border-left: 1px solid var(--vxw-border); }
    .invr-bucket .vxr-stat-icon { width: 44px; height: 44px; }
    .invr-bucket .vxr-stat-icon svg { width: 22px; height: 22px; }
    .invr-bucket small { display: block; font-size: 13px; color: var(--vxw-muted); white-space: nowrap; }
    .invr-main { display: grid; gap: 18px; }
    @media (min-width: 1024px) { .invr-main { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); align-items: stretch; } }
    .invr-bucket b { display: block; font-size: 20px; font-weight: 750; font-variant-numeric: tabular-nums; }
    @media (max-width: 640px) { .invr-buckets { grid-template-columns: 1fr; } .invr-bucket { flex-direction: row; align-items: center; padding: 0; } .invr-bucket + .invr-bucket { border-left: 0; border-top: 1px solid var(--vxw-border); padding-top: 12px; margin-top: 12px; } }
    .invr-tabbar { padding: 0 12px; }
    .invr-card .vxr-table { min-width: 820px; }
    .invr-tools { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; padding: 14px 20px; }
    .invr-tools .vxr-search { max-width: 480px; }
    .invr-item { display: flex; align-items: center; gap: 12px; min-width: 220px; }
    .invr-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; flex: none; background: var(--vxw-sunken); }
    .invr-thumb--none { display: grid; place-items: center; color: var(--vxw-faint); }
    .invr-thumb--none svg { width: 20px; height: 20px; }
    .invr-item a { color: inherit; font-weight: 650; text-decoration: none; }
    .invr-item a:hover { color: var(--vxw-accent-ink); text-decoration: underline; text-underline-offset: 3px; }
    .vxr-table th button.invr-sorted { color: var(--vxw-accent-ink); }
    .invr-menu-btn { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; background: none; border: 0; color: var(--vxw-muted); cursor: pointer; }
    .invr-menu-btn:hover { background: var(--vxw-sunken); }
    .invr-menu-btn svg { width: 20px; height: 20px; }
    .invr-menu { position: fixed; z-index: 1000; min-width: 190px; padding: 6px; background: var(--vxw-surface); border: 1px solid var(--vxw-border); border-radius: 12px; box-shadow: var(--vxw-shadow-pop); }
    .invr-menu a, .invr-menu button { display: flex; width: 100%; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 8px; font-size: 14px; color: var(--vxw-text); text-decoration: none; background: none; border: 0; cursor: pointer; text-align: left; }
    .invr-menu a:hover, .invr-menu button:hover { background: var(--vxw-surface-2); }
    .invr-menu svg { width: 17px; height: 17px; color: var(--vxw-muted); }
    .invr-export { position: relative; }
    .invr-export .invr-menu { position: absolute; right: 0; top: calc(100% + 6px); min-width: 220px; }
    .invr-share { display: flex; align-items: center; gap: 10px; min-width: 140px; }
    .invr-share .vxr-bar-track { flex: 1; height: 8px; }
    .invr-share span { font-size: 12.5px; color: var(--vxw-muted); width: 44px; text-align: right; font-variant-numeric: tabular-nums; }
    .invr-flow { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); border-top: 1px solid var(--vxw-border); }
    .invr-flow > div { padding: 14px 20px; }
    .invr-flow > div + div { border-left: 1px solid var(--vxw-border); }
    .invr-flow small { display: block; font-size: 13px; color: var(--vxw-muted); }
    .invr-flow b { display: block; font-size: 20px; font-weight: 750; font-variant-numeric: tabular-nums; }
    .invr-flow i { font-style: normal; font-size: 12px; color: var(--vxw-faint); }
    @media (max-width: 700px) { .invr-flow > div + div { border-left: 0; border-top: 1px solid var(--vxw-border); } .invr-filters .vxr-control { flex: 1 1 100%; } .invr-filters .vxr-foot-note { margin-left: 0; } }
    @media print { .invr-export, .invr-tools, .vxr-pager, .invr-menu-btn { display: none !important; } }
</style>

<div class="vxr">
    {{-- Header --}}
    <div class="vxr-head">
        <div>
            <h1 class="vxr-title">Inventory Report &amp; Analytics</h1>
            <p class="vxr-subtitle">Stock health, movement and money tied up in inventory.</p>
        </div>
        <div class="vxr-actions">
            <select wire:model.live="reportStock" class="vxr-select" aria-label="Inventory scope">
                <option value="">All Inventory</option>
                <option value="in">In stock only</option>
                <option value="low">Low stock</option>
                <option value="out">Out of stock</option>
            </select>
            <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}" class="vxr-btn">
                <x-filament::icon icon="heroicon-o-squares-2x2" /> Item list
            </a>
            <div x-data="{ open: false }" class="invr-export">
                <button type="button" @click="open = !open" class="vxr-btn vxr-btn--primary">
                    <x-filament::icon icon="heroicon-o-arrow-down-tray" /> Export
                </button>
                <div x-cloak x-show="open" @click.outside="open = false" class="invr-menu">
                    @if($itemTabs)
                        <button type="button" wire:click="exportPdf" @click="open = false"><x-filament::icon icon="heroicon-o-document-text" /> Export to PDF</button>
                    @endif
                    <button type="button" wire:click="exportCsv" @click="open = false"><x-filament::icon icon="heroicon-o-table-cells" /> Export this tab to CSV</button>
                    <button type="button" onclick="window.print()" @click="open = false"><x-filament::icon icon="heroicon-o-printer" /> Print report</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="vxr-toolbar--card">
        <div class="invr-filters">
            <label class="vxr-control invr-period" for="report-period">
                <span class="invr-period-label">Activity period</span>
                <x-filament::icon icon="heroicon-o-calendar-days" />
                <select id="report-period" wire:model.live="reportDays">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="custom">Custom dates</option>
                </select>
            </label>
            <label class="vxr-control">
                <x-filament::icon icon="heroicon-o-map-pin" />
                <select wire:model.live="reportLocation" aria-label="Location">
                    <option value="">All locations</option>
                    @foreach($data['location_options'] as $locationName)<option>{{ $locationName }}</option>@endforeach
                </select>
            </label>
            <label class="vxr-control">
                <x-filament::icon icon="heroicon-o-tag" />
                <select wire:model.live="reportCategory" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach($data['categories'] as $category)<option>{{ $category }}</option>@endforeach
                    @if(($data['summary']['uncategorized'] ?? 0) > 0 || $reportCategory === 'Uncategorized')<option value="Uncategorized">Uncategorized</option>@endif
                </select>
            </label>
            <button type="button" wire:click="toggleReportFilters" class="vxr-btn">
                <x-filament::icon icon="heroicon-o-adjustments-horizontal" /> {{ $showReportFilters ? 'Hide filters' : 'More filters' }}
            </button>
            <span class="vxr-foot-note"><x-filament::icon icon="heroicon-o-information-circle" /> Stock values reflect current inventory; activity uses selected period.</span>

            @if($reportDays === 'custom')
                <div class="invr-dates" style="width:100%">
                    <label>From <input type="date" wire:model.live="reportStart" /></label>
                    <label>To <input type="date" wire:model.live="reportEnd" /></label>
                    @error('reportStart')<span class="invr-err">{{ $message }}</span>@enderror
                    @error('reportEnd')<span class="invr-err">{{ $message }}</span>@enderror
                </div>
            @endif

            @if($showReportFilters)
                <div class="invr-more">
                    <label>Average cost per unit
                        <span class="invr-range">
                            <input wire:model.live.debounce.350ms="reportPriceMin" type="number" min="0" step=".01" placeholder="$ Min" />
                            <input wire:model.live.debounce.350ms="reportPriceMax" type="number" min="0" step=".01" placeholder="$ Max" />
                        </span>
                    </label>
                    <label>Total value
                        <span class="invr-range">
                            <input wire:model.live.debounce.350ms="reportValueMin" type="number" min="0" step=".01" placeholder="$ Min" />
                            <input wire:model.live.debounce.350ms="reportValueMax" type="number" min="0" step=".01" placeholder="$ Max" />
                        </span>
                    </label>
                    <label>Channel
                        <span style="font-weight:500;color:var(--vxw-text);line-height:40px">{{ \App\Support\ChannelContext::current()?->name ?? 'All channels' }}</span>
                    </label>
                    <div style="display:flex;align-items:flex-end;gap:8px">
                        <button type="button" wire:click="resetReportFilters" class="vxr-btn">Reset all filters</button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Headline figures --}}
    <div class="vxr-stats">
        <x-vxr.stat label="Inventory Value" :value="$money($summary['value'])" icon="heroicon-o-cube" tone="accent" note="Current on-hand valuation" wire:click="setTab('top-value')" style="cursor:pointer" />
        <x-vxr.stat label="Total Items" :value="number_format($summary['items'])" icon="heroicon-o-archive-box" tone="ok" :note="number_format($summary['in_stock_items']).' in stock'" wire:click="setTab('items')" style="cursor:pointer" />
        <x-vxr.stat label="Units On Hand" :value="number_format($summary['quantity'])" icon="heroicon-o-squares-plus" tone="info" note="Units currently in stock" />
        <x-vxr.stat label="Avg Cost per Unit" :value="$money($summary['avg_cost'])" icon="heroicon-o-banknotes" tone="warn" note="Value ÷ units on hand" />
    </div>

    <div class="vxr-strip invr-strip">
        <button type="button" wire:click="setTab('low-stock')">
            <span class="vxr-stat-icon vxr-tone-warn"><x-filament::icon icon="heroicon-o-exclamation-triangle" /></span>
            <span><small>Low Stock</small><b>{{ number_format($summary['low_stock']) }}</b></span>
        </button>
        <button type="button" wire:click="setTab('out-stock')">
            <span class="vxr-stat-icon vxr-tone-bad"><x-filament::icon icon="heroicon-o-x-circle" /></span>
            <span><small>Out of Stock</small><b>{{ number_format($summary['out_stock']) }}</b></span>
        </button>
        <button type="button" wire:click="showUncategorized">
            <span class="vxr-stat-icon vxr-tone-info"><x-filament::icon icon="heroicon-o-tag" /></span>
            <span><small>Uncategorized Items</small><b>{{ number_format($summary['uncategorized'] ?? 0) }}</b></span>
        </button>
    </div>

    {{-- Trend + idle --}}
    <div class="invr-main">
        <div class="vxr-card">
            <div class="vxr-card-head">
                <div>
                    <h3 class="vxr-card-title">Inventory Value Trend</h3>
                    <p class="vxr-card-sub">Nightly snapshot of total inventory value · {{ $periodLabel }} · all inventory (filters don't apply)</p>
                </div>
            </div>
            <div class="vxr-card-body">
                @if($trend->isNotEmpty())
                    <x-vxr.chart type="bar" :money="true" :height="230"
                        :points="$trend->map(fn ($p) => ['label' => $p['date'], 'value' => $p['value'], 'title' => $p['date'].' · $'.number_format($p['value'], 2)])->all()" />
                @else
                    <div class="vxr-empty"><b>No snapshots in this period yet</b>A snapshot of total value is saved every night; the trend fills in as they accumulate.</div>
                @endif
            </div>
        </div>

        <div class="vxr-card">
            <div class="vxr-card-head">
                <h3 class="vxr-card-title">Idle Inventory
                    <span title="Current stock grouped by days since its last recorded movement (or since it was created, if it never moved). This measures inactivity, not lot age." style="display:inline-flex"><x-filament::icon icon="heroicon-o-information-circle" style="width:18px;height:18px;color:var(--vxw-faint)" /></span>
                </h3>
            </div>
            <div class="vxr-card-body" style="display:grid;gap:16px">
                <div class="invr-buckets">
                    @foreach([[30, 59, '30 – 59 days', 'info'], [60, 89, '60 – 89 days', 'warn'], [90, null, '90+ days', 'bad']] as [$min, $max, $label, $tone])
                        @php $bucket = $idle->filter(fn ($row) => $row['idle_days'] >= $min && ($max === null || $row['idle_days'] <= $max)); @endphp
                        <div class="invr-bucket">
                            <span class="vxr-stat-icon vxr-tone-{{ $tone }}"><x-filament::icon icon="heroicon-o-clock" /></span>
                            <span style="min-width:0"><small>{{ $label }}</small><b>{{ $money($bucket->sum('total_value')) }}</b><small>{{ $bucket->count() }} {{ \Illuminate\Support\Str::plural('item', $bucket->count()) }}</small><small>{{ number_format($bucket->sum('quantity')) }} units</small></span>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="setTab('aging')" class="vxr-btn vxr-btn--outline vxr-btn--block">View Idle Inventory</button>
            </div>
        </div>
    </div>

    {{-- Tabbed table --}}
    <div class="vxr-card invr-card">
        <div class="vxr-tabs invr-tabbar" role="tablist">
            @foreach(\App\Filament\Pages\InventoryReport::TABS as $key => $label)
                <button type="button" role="tab" wire:click="setTab('{{ $key }}')" class="vxr-tab {{ $activeTab === $key ? 'is-active' : '' }}" aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}">
                    {{ $label }}@if(($tabCounts[$key] ?? 0) > 0)<span class="vxr-count">{{ number_format($tabCounts[$key]) }}</span>@endif
                </button>
            @endforeach
        </div>

        <div class="invr-tools">
            <label class="vxr-control vxr-search">
                <x-filament::icon icon="heroicon-o-magnifying-glass" />
                <input wire:model.live.debounce.350ms="reportSearch" placeholder="Search inventory..." aria-label="Search inventory" />
            </label>
            @if($itemTabs)
                <label class="vxr-control">
                    <x-filament::icon icon="heroicon-o-arrows-up-down" />
                    <select wire:model.live="reportSort" aria-label="Sort">
                        <option value="value">Highest value</option>
                        <option value="qty">Most units</option>
                        <option value="name">Name A–Z</option>
                        <option value="updated">Recently updated</option>
                    </select>
                </label>
            @endif
        </div>

        @if($activeTab === 'recent')
            @php $flow = $this->getMovementSummary(); @endphp
            <div class="invr-flow">
                @foreach(['received' => 'Received', 'removed' => 'Removed', 'adjustments' => 'Adjustments', 'transfers' => 'Internal transfers', 'net' => 'Net change'] as $k => $label)
                    <div><small>{{ $label }}</small><b>{{ ($k === 'adjustments' || $k === 'net') && $flow['current'][$k] > 0 ? '+' : '' }}{{ number_format($flow['current'][$k], 2) }}</b><i>units · previous period {{ number_format($flow['previous'][$k], 2) }}</i></div>
                @endforeach
            </div>
        @endif

        <div class="vxr-table-wrap">
            @if($itemTabs)
                <table class="vxr-table">
                    <thead><tr>
                        <th>{!! $sortHead('name', 'Item') !!}</th>
                        <th>SKU</th>
                        <th>Location</th>
                        <th class="num">{!! $sortHead('qty', 'Quantity') !!}</th>
                        <th class="num">Avg Cost</th>
                        <th class="num">{!! $sortHead('value', 'Total Value') !!}</th>
                        <th>{!! $sortHead('updated', 'Last Updated') !!}</th>
                        <th aria-label="Actions"></th>
                    </tr></thead>
                    <tbody>
                    @forelse($visible as $item)
                        <tr wire:key="inv-row-{{ $item['id'] }}">
                            <td>
                                <div class="invr-item">
                                    @if($item['has_image'] ?? false)
                                        <img src="{{ $item['image_url'] }}" alt="" loading="lazy" class="invr-thumb" />
                                    @else
                                        <span class="invr-thumb invr-thumb--none"><x-filament::icon icon="heroicon-o-cube" /></span>
                                    @endif
                                    <div style="min-width:0">
                                        <a href="{{ $itemUrl($item['id']) }}">{{ $item['name'] }}</a> <x-vx.rarity :cost="$item['unit_cost'] ?? null" />
                                        <span class="sub">{{ $item['category'] }}@if($activeTab === 'aging') · <span class="vxr-val-warn">{{ $item['idle_days'] }} days without movement</span>@endif</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $item['sku'] }}</td>
                            <td>{{ $item['locations'] }}</td>
                            <td class="num">
                                @if($item['is_out_stock'])<span class="vxr-pill vxr-pill--bad">0</span>
                                @elseif($item['is_low_stock'])<span class="vxr-pill vxr-pill--warn">{{ number_format($item['quantity']) }}</span>
                                @else{{ number_format($item['quantity']) }}@endif
                                @if($activeTab === 'low-stock' && $item['reorder_level'] > 0)<span class="sub">reorder at {{ number_format($item['reorder_level']) }}</span>@endif
                            </td>
                            <td class="num">{{ $money($item['unit_cost']) }}</td>
                            <td class="num strong">{{ $money($item['total_value']) }}</td>
                            <td style="white-space:nowrap">{{ $item['updated_at']?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="num">
                                <div x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false" @scroll.window="open = false">
                                    <button type="button" class="invr-menu-btn" aria-label="Actions for {{ $item['name'] }}"
                                        @click="const r = $el.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 190); open = !open">
                                        <x-filament::icon icon="heroicon-m-ellipsis-horizontal" />
                                    </button>
                                    <div x-cloak x-show="open" @click.outside="open = false" class="invr-menu" :style="`top:${top}px;left:${left}px`">
                                        <a href="{{ $itemUrl($item['id']) }}"><x-filament::icon icon="heroicon-o-eye" /> View item</a>
                                        <a href="{{ $itemUrl($item['id'], 'edit') }}"><x-filament::icon icon="heroicon-o-pencil-square" /> Edit item</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="vxr-empty"><b>No inventory matches this view</b>Try another tab, or clear the search and filters.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            @elseif($activeTab === 'valuation')
                <table class="vxr-table">
                    <thead><tr><th>Category</th><th class="num">Items in stock</th><th class="num">Units</th><th class="num">Total Value</th><th>Share of value</th></tr></thead>
                    <tbody>
                    @forelse($visible as $row)
                        @php $share = $summary['value'] > 0 ? $row['value'] / $summary['value'] * 100 : 0; @endphp
                        <tr>
                            <td class="strong"><button type="button" wire:click="$set('reportCategory', @js($row['name']))" class="vxr-link" style="background:none;border:0;padding:0;cursor:pointer">{{ $row['name'] }}</button></td>
                            <td class="num">{{ number_format($row['items'] ?? 0) }}</td>
                            <td class="num">{{ number_format($row['quantity']) }}</td>
                            <td class="num strong">{{ $money($row['value']) }}</td>
                            <td><div class="invr-share"><div class="vxr-bar-track"><div class="vxr-bar-fill" style="width:{{ $share }}%"></div></div><span>{{ number_format($share, 1) }}%</span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="vxr-empty"><b>Nothing in stock to value</b>Categories appear here once items have stock on hand.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            @elseif($activeTab === 'locations')
                <table class="vxr-table">
                    <thead><tr><th>Location</th><th class="num">Items</th><th class="num">Units</th><th class="num">Total Value</th><th>Share of value</th></tr></thead>
                    <tbody>
                    @php $locTotal = max(0.0001, (float) $locations->sum('value')); @endphp
                    @forelse($visible as $row)
                        @php $share = $row['value'] / $locTotal * 100; @endphp
                        <tr>
                            <td class="strong"><button type="button" wire:click="$set('reportLocation', @js($row['name']))" class="vxr-link" style="background:none;border:0;padding:0;cursor:pointer">{{ $row['name'] }}</button></td>
                            <td class="num">{{ number_format($row['items'] ?? 0) }}</td>
                            <td class="num">{{ number_format($row['quantity']) }}</td>
                            <td class="num strong">{{ $money($row['value']) }}</td>
                            <td><div class="invr-share"><div class="vxr-bar-track"><div class="vxr-bar-fill" style="width:{{ $share }}%"></div></div><span>{{ number_format($share, 1) }}%</span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="vxr-empty"><b>No stock at any location</b>Locations appear here once they hold stock.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            @elseif($activeTab === 'recent')
                <table class="vxr-table">
                    <thead><tr><th>Item</th><th>SKU</th><th>Activity</th><th>Location</th><th class="num">Change</th><th>When / By</th></tr></thead>
                    <tbody>
                    @forelse($visible as $row)
                        <tr>
                            <td class="strong">{{ $row['name'] }}</td>
                            <td>{{ $row['sku'] }}</td>
                            <td><span class="vxr-pill">{{ $row['type'] }}</span></td>
                            <td>{{ $row['location'] }}</td>
                            <td class="num strong">{{ $row['change'] }}</td>
                            <td style="white-space:nowrap">{{ $row['updated_at']?->format('M j, Y g:i A') }}<span class="sub">{{ $row['actor'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="vxr-empty"><b>No inventory activity in this period</b>Pick a longer activity period to see more.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            @elseif($activeTab === 'moving')
                <table class="vxr-table">
                    <thead><tr><th>Item</th><th>SKU</th><th class="num">Units Sold</th><th>Period</th></tr></thead>
                    <tbody>
                    @forelse($visible as $row)
                        <tr>
                            <td class="strong"><a href="{{ $itemUrl($row['id']) }}" class="vxr-link">{{ $row['name'] }}</a></td>
                            <td>{{ $row['sku'] }}</td>
                            <td class="num strong">{{ number_format($row['moved_units']) }}</td>
                            <td>{{ $periodLabel }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="vxr-empty"><b>No sales recorded in this period</b>Sales deductions and show sales are counted here.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        <div class="vxr-card-foot">
            <span>Showing {{ number_format($from) }}–{{ number_format($to) }} of {{ number_format($total) }} {{ $rowNoun }}</span>
            <div class="vxr-pager">
                <button type="button" wire:click="changeReportPage({{ $page - 1 }})" @disabled($page <= 1) aria-label="Previous page"><x-filament::icon icon="heroicon-m-chevron-left" /></button>
                <span>Page {{ $page }} of {{ $pageCount }}</span>
                <button type="button" wire:click="changeReportPage({{ $page + 1 }})" @disabled($page >= $pageCount) aria-label="Next page"><x-filament::icon icon="heroicon-m-chevron-right" /></button>
            </div>
        </div>
    </div>
</div>
</x-filament-panels::page>

<x-filament-panels::page>
@include('filament.pages.partials.ivx-styles')
@php
    $s = $this->inventorySnapshot;
    $change = $this->valueChange();
    $trend = $this->valueTrend;
    $maxV = max(1, (float) ($trend->max('value') ?? 0));
    $total = max(1, $s['in'] + $s['low'] + $s['out']);
@endphp
<style>
.ivx-desktop-only{}
@media(max-width:767px){.fi-header .ivx-desktop-only{display:none!important}}
.ivx .ivx-chart{display:grid;grid-template-columns:44px minmax(0,1fr);gap:6px;height:170px;margin-top:8px}
.ivx .ivx-chart .y{display:flex;flex-direction:column;justify-content:space-between;font-size:.62rem;color:var(--faint);text-align:right;padding-bottom:18px}
.ivx .ivx-chart .plot{position:relative;display:flex;align-items:flex-end;gap:3px;padding-bottom:18px;border-bottom:1px solid var(--line);background:repeating-linear-gradient(to top,transparent 0 37px,var(--line) 37px 38px)}
.ivx .ivx-chart .plot i{flex:1;min-width:3px;border-radius:4px 4px 0 0;background:linear-gradient(180deg,var(--p),color-mix(in srgb,var(--p) 55%,#fff))}
.ivx .ivx-chart .x{position:absolute;left:0;right:0;bottom:0;display:flex;justify-content:space-between;font-size:.62rem;color:var(--faint)}
.ivx .ivx-tool{display:flex;align-items:center;gap:12px;padding:14px;border:1px solid var(--line);border-radius:14px;background:#fff}
.dark .ivx .ivx-tool{background:#0f172a}
.ivx .ivx-tool:hover{border-color:var(--p)}
.ivx .ivx-tool.primary{background:var(--p);border-color:var(--p);color:#fff;box-shadow:0 8px 22px color-mix(in srgb,var(--p) 35%,transparent)}
.ivx .ivx-tool.primary .ivx-ic{background:rgba(255,255,255,.18);color:#fff}
.ivx .ivx-tool.primary .ivx-sub{color:rgba(255,255,255,.8)}
.ivx .ivx-tool .t{font-size:.9rem;font-weight:800}
.ivx .ivx-trow{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr) 64px;gap:10px;align-items:center;padding:9px 14px;border-bottom:1px solid var(--line);font-size:.8rem}
.ivx a.ivx-trow:hover{background:var(--soft)}
.ivx .ivx-thead{font-size:.68rem;font-weight:700;color:var(--muted);padding-top:12px}
.ivx .ivx-trow .thumb{width:30px;height:30px;border-radius:7px}
</style>

<div class="ivx">
    <nav class="ivx-tabs" aria-label="Inventory overview sections">
        @foreach(['overview' => 'Overview', 'tools' => 'Tools', 'reports' => 'Reports'] as $k => $l)
            <button type="button" wire:click="setTab('{{ $k }}')" @class(['on' => $tab === $k]) aria-pressed="{{ $tab === $k ? 'true' : 'false' }}">{{ $l }}</button>
        @endforeach
    </nav>

    @if($tab === 'overview')
        {{-- ── 1. Overview ─────────────────────────────────────────── --}}
        <div class="ivx-kpis wide4" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <div class="ivx-kpi" style="flex-direction:row;align-items:center;gap:12px">
                <span class="ivx-ic green"><x-heroicon-o-banknotes /></span>
                <span class="min-w-0"><span class="v block" style="font-size:clamp(1.05rem,4.6vw,1.35rem)">${{ number_format($s['value'], 0) }}</span><span class="l">Inventory Value</span>
                    @if($change !== null)<span class="ivx-up {{ $change < 0 ? 'down' : '' }} mt-1">{{ $change < 0 ? '↓' : '↑' }} {{ abs($change) }}%</span>@endif</span>
            </div>
            <div class="ivx-kpi" style="flex-direction:row;align-items:center;gap:12px">
                <span class="ivx-ic"><x-heroicon-o-cube /></span>
                <span class="min-w-0"><span class="v block">{{ number_format($s['units']) }}</span><span class="l">Units on Hand</span></span>
            </div>
        </div>
        <div class="ivx-kpis k3">
            <a href="{{ $this->stockStatusUrl('in') }}" class="ivx-kpi"><span class="v">{{ number_format($s['in']) }}</span><span class="l"><span class="ivx-dot green"></span>In Stock</span></a>
            <a href="{{ $this->stockStatusUrl('low') }}" class="ivx-kpi"><span class="v">{{ number_format($s['low']) }}</span><span class="l"><span class="ivx-dot amber"></span>Low Stock</span></a>
            <a href="{{ $this->stockStatusUrl('out') }}" class="ivx-kpi"><span class="v">{{ number_format($s['out']) }}</span><span class="l"><span class="ivx-dot red"></span>Out of Stock</span></a>
        </div>

        <div class="ivx-grid2">
            <section class="ivx-card ivx-pad">
                <div class="ivx-head" style="margin-bottom:4px">
                    <h3 class="ivx-h">Inventory Value Trend</h3>
                    <div class="ivx-tabs" style="padding:3px;flex:none" role="group" aria-label="Trend period">
                        @foreach([7, 30, 90] as $days)<button type="button" wire:click="$set('trendDays', {{ $days }})" @class(['on' => (int) $trendDays === $days]) style="min-height:28px;padding:0 10px;font-size:.72rem">{{ $days }}D</button>@endforeach
                    </div>
                </div>
                @if($change !== null)<span class="ivx-up {{ $change < 0 ? 'down' : '' }}">{{ $change < 0 ? '↓' : '↑' }} {{ abs($change) }}%</span>@endif
                @if($trend->count() >= 2)
                    <div class="ivx-chart" role="img" aria-label="Inventory value over the last {{ $trendDays }} days">
                        <div class="y"><span>${{ number_format($maxV / 1000, 0) }}K</span><span>${{ number_format($maxV * 2 / 3000, 0) }}K</span><span>${{ number_format($maxV / 3000, 0) }}K</span><span>$0</span></div>
                        <div class="plot">
                            @foreach($trend as $point)<i style="height:{{ max(2, round($point['value'] / $maxV * 100)) }}%" title="{{ $point['date'] }} · ${{ number_format($point['value'], 0) }}"></i>@endforeach
                            <div class="x"><span>{{ $trend->first()['date'] }}</span><span>{{ $trend[(int) floor($trend->count() / 2)]['date'] }}</span><span>{{ $trend->last()['date'] }}</span></div>
                        </div>
                    </div>
                @else
                    <div class="ivx-empty">The trend fills in as daily value snapshots are taken.</div>
                @endif
            </section>

            <section class="ivx-card ivx-pad">
                <div class="ivx-head"><h3 class="ivx-h">Inventory Health</h3><a href="{{ $this->healthUrl() }}" class="ivx-link">Details</a></div>
                <div class="ivx-bar" style="height:12px">
                    <i style="width:{{ $s['in'] / $total * 100 }}%;background:var(--green)"></i>
                    <i style="width:{{ $s['low'] / $total * 100 }}%;background:var(--amber)"></i>
                    <i style="width:{{ $s['out'] / $total * 100 }}%;background:var(--red)"></i>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs" style="color:var(--muted)">
                    <span class="inline-flex items-center gap-1.5"><span class="ivx-dot green"></span><b style="color:var(--text)">{{ number_format($s['in']) }}</b> Fresh</span>
                    <span class="inline-flex items-center gap-1.5"><span class="ivx-dot amber"></span><b style="color:var(--text)">{{ number_format($s['low']) }}</b> Low Stock</span>
                    <span class="inline-flex items-center gap-1.5"><span class="ivx-dot red"></span><b style="color:var(--text)">{{ number_format($s['out']) }}</b> Out of Stock</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a href="{{ $this->ageUrl() }}" class="ivx-btn"><x-heroicon-o-clock /> Inventory Age</a>
                    <a href="{{ $this->activityUrl() }}" class="ivx-btn"><x-heroicon-o-arrow-path /> Recent Activity</a>
                </div>
            </section>
        </div>

        <section class="ivx-card">
            <div class="ivx-head ivx-pad" style="margin:0;padding-bottom:6px"><h3 class="ivx-h">Recent Inventory Activity</h3><a href="{{ $this->activityUrl() }}" class="ivx-link">View all</a></div>
            <div class="ivx-list">
                @forelse($this->recentRestocks as $m)
                    <a href="{{ $m->item ? $this->itemUrl($m->item->id) : $this->activityUrl() }}" class="ivx-row">
                        <span class="ivx-ic green" style="width:34px;height:34px"><x-heroicon-o-inbox-arrow-down /></span>
                        <span class="min-w-0 flex-1"><span class="ivx-name block">{{ $m->item?->name ?? 'Deleted item' }}</span><span class="ivx-meta block">{{ $m->toLocation?->name ?? '—' }}</span></span>
                        <span class="ivx-right"><b class="text-sm" style="color:var(--green)">+{{ number_format((float) $m->quantity) }}</b><span class="ivx-meta">{{ $m->created_at?->format('M j') }}</span></span>
                    </a>
                @empty
                    <div class="ivx-empty">No stock received yet.</div>
                @endforelse
            </div>
        </section>

    @elseif($tab === 'tools')
        {{-- ── 6. Tools ────────────────────────────────────────────── --}}
        <a href="{{ $this->receiveUrl() }}" class="ivx-tool primary"><span class="ivx-ic"><x-heroicon-o-inbox-arrow-down /></span><span class="flex-1"><span class="t block">Receive Inventory</span><span class="ivx-sub">Pallets &amp; receiving</span></span><x-heroicon-m-chevron-right class="ivx-chev" style="color:#fff" /></a>
        <a href="{{ $this->quickAddUrl() }}" class="ivx-tool"><span class="ivx-ic"><x-heroicon-o-plus-circle /></span><span class="flex-1"><span class="t block">Quick Add Stock</span><span class="ivx-sub">Add quantity fast</span></span><x-heroicon-m-chevron-right class="ivx-chev" /></a>
        <a href="{{ $this->scanUrl() }}" class="ivx-tool"><span class="ivx-ic"><x-heroicon-o-qr-code /></span><span class="flex-1"><span class="t block">Scan Inventory</span><span class="ivx-sub">Barcode workflow</span></span><x-heroicon-m-chevron-right class="ivx-chev" /></a>
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ $this->addItemUrl() }}" class="ivx-tool"><span class="ivx-ic"><x-heroicon-o-cube /></span><span class="min-w-0"><span class="t block">Add Item</span><span class="ivx-sub">Create new SKU</span></span></a>
            <a href="{{ $this->transferUrl() }}" class="ivx-tool"><span class="ivx-ic"><x-heroicon-o-arrows-right-left /></span><span class="min-w-0"><span class="t block">Transfer Stock</span><span class="ivx-sub">Move between locations</span></span></a>
        </div>
        <section class="ivx-card ivx-list">
            @foreach([
                ['All inventory', 'Cards and table of every item', $this->inventoryUrl(), 'heroicon-o-squares-2x2'],
                ['Stock count', 'Count a location and correct it', $this->countUrl(), 'heroicon-o-clipboard-document-check'],
                ['Import a sheet', 'Bring in a product cost sheet', $this->importUrl(), 'heroicon-o-arrow-up-tray'],
                ['Locations', 'Warehouses, shelves and streamer stock', $this->locationsUrl(), 'heroicon-o-map-pin'],
                ['Vendors', 'Who you buy from', $this->vendorsUrl(), 'heroicon-o-building-storefront'],
            ] as [$t, $d, $u, $i])
                <a href="{{ $u }}" class="ivx-row"><span class="ivx-ic" style="width:34px;height:34px"><x-dynamic-component :component="$i" /></span><span class="min-w-0 flex-1"><span class="ivx-name block">{{ $t }}</span><span class="ivx-meta block">{{ $d }}</span></span><x-heroicon-m-chevron-right class="ivx-chev" /></a>
            @endforeach
        </section>

    @else
        {{-- ── 7. Reports ──────────────────────────────────────────── --}}
        @php $report = $this->reportRows(); @endphp
        <section class="ivx-card ivx-pad">
            <div class="ivx-head" style="margin-bottom:8px">
                <div><h3 class="ivx-h">Inventory Report</h3><p class="ivx-sub">Detailed inventory data with filters and export.</p></div>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <label class="text-xs font-semibold" style="color:var(--muted)">Location
                    <select wire:model.live="reportLocation" class="ivx-input mt-1"><option value="">All Locations</option>@foreach($this->reportLocations as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold" style="color:var(--muted)">Category
                    <select wire:model.live="reportCategory" class="ivx-input mt-1"><option value="">All Categories</option>@foreach($this->reportCategories as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select></label>
            </div>
            <div class="mt-2 flex gap-2">
                <div class="ivx-search"><x-heroicon-o-magnifying-glass /><input type="search" wire:model.live.debounce.300ms="reportSearch" placeholder="Search items, SKU, or name…"></div>
                <a href="{{ $this->exportUrl() }}" target="_blank" class="ivx-btn ic" title="Export Excel" aria-label="Export Excel"><x-heroicon-o-arrow-down-tray /></a>
            </div>
        </section>
        <section class="ivx-card" style="overflow:hidden">
            <div class="ivx-trow ivx-thead"><span>Item</span><span>SKU</span><span style="text-align:right">Stock</span></div>
            @forelse($report['rows'] as $r)
                <a href="{{ $this->itemUrl($r['id']) }}" class="ivx-trow" wire:key="rep-{{ $r['id'] }}">
                    <span class="flex min-w-0 items-center gap-2">@if($r['image'])<img src="{{ $r['image'] }}" alt="" class="ivx-thumb thumb" loading="lazy">@else<span class="ivx-thumb thumb"><x-heroicon-o-cube /></span>@endif<span class="ivx-name" style="font-size:.78rem">{{ $r['name'] }}</span></span>
                    <span class="ivx-meta">{{ $r['sku'] }}</span>
                    <span style="text-align:right;font-weight:800;color:{{ $r['qty'] <= 0 ? 'var(--red)' : 'var(--green)' }}">{{ number_format($r['qty']) }}</span>
                </a>
            @empty
                <div class="ivx-empty">No items match these filters.</div>
            @endforelse
            @if($report['total'] > $report['rows']->count())
                <div class="p-3 text-center"><button type="button" class="ivx-btn" wire:click="moreReportRows">Show more ({{ $report['total'] - $report['rows']->count() }} left)</button></div>
            @endif
        </section>
        <a href="{{ $this->reportUrl() }}" class="ivx-tool"><span class="ivx-ic"><x-heroicon-o-chart-bar /></span><span class="flex-1"><span class="t block">Full report &amp; analytics</span><span class="ivx-sub">Value, movement, categories, exports</span></span><x-heroicon-m-chevron-right class="ivx-chev" /></a>
    @endif
</div>
</x-filament-panels::page>

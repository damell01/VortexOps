<x-filament-panels::page>
@php
 $data=$this->getViewerData(); $summary=$data['summary']; $items=collect($data['items']); $trend=collect($data['trend']); $locations=collect($data['locations']); $categories=collect($data['category_breakdown']);
 $allRows = match ($activeTab) {
  'recent' => collect($this->getRecentActivityRows()),
  'moving' => collect($this->getTopMovingRows()),
  default => $this->getReportItems(),
 };
 $pageSize = 25; $pageCount = max(1,(int)ceil($allRows->count()/$pageSize)); $page = min(max(1,$reportPage),$pageCount);
 $visible = $allRows->slice(($page-1)*$pageSize,$pageSize)->values();
 $recentRows = $visible; $movingRows = $visible;
 $movement = $this->getMovementSummary();
 $idle = $items->where('quantity','>',0);
 $maxTrend=max(1,(float)$trend->max('value')); $maxLocation=max(1,(float)$locations->max('quantity')); $totalQty=max(1,(float)$categories->sum('quantity'));
@endphp
@include('filament.pages.partials.inventory-report-styles')
<div class="vx-report space-y-4">
 <div class="vx-report-head">
  <div><h2 class="text-2xl font-bold tracking-tight">Inventory Report</h2><p class="mt-1 text-sm text-gray-500">Stock health, movement and money tied up in inventory.</p></div>
  <div class="vx-report-actions">
   <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-bold shadow-sm dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-arrow-left class="h-4 w-4"/> All Inventory</a>
   <div x-data="{open:false}" class="vx-export">
    <button @click="open=!open" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-bold text-white shadow-sm hover:bg-primary-700"><x-heroicon-o-arrow-down-tray class="h-4 w-4"/> Export <x-heroicon-o-chevron-down class="h-4 w-4"/></button>
    <div x-cloak x-show="open" @click.outside="open=false" class="vx-export-menu">
     @if(!in_array($activeTab,['recent','moving']))<button wire:click="exportPdf"><x-heroicon-o-document-text class="mr-2 h-4 w-4"/> Export to PDF</button>@endif<button wire:click="exportCsv"><x-heroicon-o-table-cells class="mr-2 h-4 w-4"/> Export to CSV</button><button onclick="window.print()"><x-heroicon-o-printer class="mr-2 h-4 w-4"/> Print Report</button>
    </div>
   </div>
  </div>
 </div>

 <div class="vx-r-card flex flex-wrap items-center justify-between gap-3 p-4">
  <div><label for="report-period" class="text-xs font-bold">Activity period</label><select id="report-period" wire:model.live="reportDays" class="ml-2 rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800"><option value="7">Last 7 days</option><option value="30">Last {{ $this->periodDays() }} days</option><option value="90">Last 90 days</option><option value="custom">Custom dates</option></select></div>
  @if($reportDays==='custom')<div class="flex flex-wrap items-center gap-2"><label class="text-xs">From <input type="date" wire:model.live="reportStart" class="rounded-lg border-gray-200 text-sm dark:bg-gray-800"/></label><label class="text-xs">To <input type="date" wire:model.live="reportEnd" class="rounded-lg border-gray-200 text-sm dark:bg-gray-800"/></label>@error('reportStart')<span class="text-xs text-red-600">{{ $message }}</span>@enderror @error('reportEnd')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</div>@endif
  <p class="text-xs text-gray-500">Movement compared with previous {{ $this->periodDays() }} days · Stock values are current · Channel: {{ \App\Support\ChannelContext::current()?->name ?? 'All channels' }}</p>
 </div>
 <div class="vx-r-card sticky top-[64px] z-20 p-2.5 bg-white/95 backdrop-blur dark:bg-gray-900/95">
  <div class="mb-2 flex items-center justify-between px-1"><div><div class="text-xs font-extrabold uppercase tracking-wide text-gray-500">Inventory views</div><div class="mt-1 text-[11px] text-gray-400">Choose the inventory view you need. Filters apply across the report.</div></div><button wire:click="toggleReportFilters" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold dark:border-gray-700">Filters</button></div>
  <div class="vx-quick-reports">
   <button wire:click="applyQuickReport('current')" class="vx-quick {{ $reportStock==='in' && $activeTab==='items'?'active':'' }}"><span class="vx-quick-icon vx-icon-purple"><x-heroicon-o-cube/></span><span><div class="vx-quick-title">Current Inventory</div><div class="vx-quick-sub">All stock on hand</div></span></button>
   <button wire:click="applyQuickReport('valuation')" class="vx-quick"><span class="vx-quick-icon vx-icon-blue"><x-heroicon-o-currency-dollar/></span><span><div class="vx-quick-title">Inventory Valuation</div><div class="vx-quick-sub">Total value & cost</div></span></button>
   <button wire:click="resetReportFilters" class="vx-quick"><span class="vx-quick-icon" style="background:#dbeafe;color:#2563eb"><x-heroicon-o-map-pin/></span><span><div class="vx-quick-title">Stock by Location</div><div class="vx-quick-sub">Warehouse breakdown</div></span></button>
   <button wire:click="applyQuickReport('low-stock')" class="vx-quick {{ $activeTab==='low-stock'?'active':'' }}"><span class="vx-quick-icon vx-icon-orange"><x-heroicon-o-exclamation-triangle/></span><span><div class="vx-quick-title">Low Stock Items</div><div class="vx-quick-sub">At risk of stockout</div></span></button>
   <button wire:click="applyQuickReport('out-stock')" class="vx-quick {{ $activeTab==='out-stock'?'active':'' }}"><span class="vx-quick-icon" style="background:#fee2e2;color:#dc2626"><x-heroicon-o-archive-box-x-mark/></span><span><div class="vx-quick-title">Out of Stock Items</div><div class="vx-quick-sub">No inventory remaining</div></span></button>
   <button wire:click="applyQuickReport('recent')" class="vx-quick {{ $activeTab==='recent'?'active':'' }}"><span class="vx-quick-icon vx-icon-green"><x-heroicon-o-arrows-right-left/></span><span><div class="vx-quick-title">Recent Activity</div><div class="vx-quick-sub">Adds & removals</div></span></button>
   <button wire:click="applyQuickReport('top-value')" class="vx-quick {{ $activeTab==='top-value'?'active':'' }}"><span class="vx-quick-icon" style="background:#fef3c7;color:#d97706"><x-heroicon-o-trophy/></span><span><div class="vx-quick-title">Top Value Items</div><div class="vx-quick-sub">Highest total value</div></span></button>
  </div>
 </div>

 <div class="vx-dashboard-layout">
  <aside class="vx-r-card vx-builder-panel {{ $showReportFilters ? '' : 'hidden' }}">
   <div class="flex items-start justify-between gap-2"><div><div class="text-sm font-extrabold">Filter inventory</div><div class="mt-1 text-[11px] leading-4 text-gray-500">Narrow this prebuilt report by item, category, location, stock status, cost or value.</div></div><x-heroicon-o-chevron-up class="h-4 w-4 text-gray-400"/></div>
   <div class="vx-builder-fields mt-3">
    <div class="vx-builder-field"><label class="vx-builder-label">Search</label><input wire:model.live.debounce.350ms="reportSearch" class="vx-builder-input" placeholder="Item, SKU, keyword..."/></div>
    <div class="vx-builder-field"><label class="vx-builder-label">Category</label><select wire:model.live="reportCategory" class="vx-builder-input"><option value="">All Categories</option>@foreach($data['categories'] as $category)<option>{{ $category }}</option>@endforeach</select></div>
    <div class="vx-builder-field"><label class="vx-builder-label">Location</label><select wire:model.live="reportLocation" class="vx-builder-input"><option value="">All Locations</option>@foreach($data['location_options'] as $locationName)<option>{{ $locationName }}</option>@endforeach</select></div>
    <div class="vx-builder-field"><label class="vx-builder-label">Stock Status</label><select wire:model.live="reportStock" class="vx-builder-input"><option value="">All Stock</option><option value="in">In Stock</option><option value="low">Low Stock</option><option value="out">Out of Stock</option></select></div>
    <div class="vx-builder-field"><label class="vx-builder-label">Price Range (Avg. Cost)</label><div class="vx-range"><input wire:model.live.debounce.350ms="reportPriceMin" type="number" min="0" step=".01" class="vx-builder-input" placeholder="$ Min"/><input wire:model.live.debounce.350ms="reportPriceMax" type="number" min="0" step=".01" class="vx-builder-input" placeholder="$ Max"/></div></div>
    <div class="vx-builder-field"><label class="vx-builder-label">Value Range (Total Value)</label><div class="vx-range"><input wire:model.live.debounce.350ms="reportValueMin" type="number" min="0" step=".01" class="vx-builder-input" placeholder="$ Min"/><input wire:model.live.debounce.350ms="reportValueMax" type="number" min="0" step=".01" class="vx-builder-input" placeholder="$ Max"/></div></div>
   </div>
   <div class="mt-4 grid grid-cols-2 gap-2"><button wire:click="toggleReportFilters" class="rounded-lg bg-primary-600 px-3 py-2 text-xs font-bold text-white">Done</button><button wire:click="resetReportFilters" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-bold dark:border-gray-700">Reset</button></div>
  </aside>
  <main class="vx-dashboard-main">

 <div class="vx-kpis">
  <button type="button" wire:click="setTab('top-value')" class="vx-r-card vx-kpi"><div class="vx-icon-kpi vx-icon-purple"><x-heroicon-o-cube/></div><div class="vx-kpi-copy"><div class="text-xs font-bold text-gray-500">Total Inventory Value</div><div class="vx-kpi-value">${{ number_format($summary['value'],2) }}</div><div class="vx-kpi-sub">Current on-hand valuation</div></div></button>
  <button type="button" wire:click="setTab('items')" class="vx-r-card vx-kpi"><div class="vx-icon-kpi vx-icon-blue"><x-heroicon-o-squares-2x2/></div><div class="vx-kpi-copy"><div class="text-xs font-bold text-gray-500">Total Items</div><div class="vx-kpi-value">{{ number_format($summary['items']) }}</div><div class="vx-kpi-sub">{{ number_format($summary['in_stock_items']) }} SKUs currently in stock</div></div></button>
  <button type="button" wire:click="setTab('items')" class="vx-r-card vx-kpi"><div class="vx-icon-kpi vx-icon-green"><x-heroicon-o-archive-box/></div><div class="vx-kpi-copy"><div class="text-xs font-bold text-gray-500">Total Quantity</div><div class="vx-kpi-value">{{ number_format($summary['quantity']) }}</div><div class="vx-kpi-sub">Units currently in stock</div></div></button>
  <button type="button" wire:click="setTab('low-stock')" class="vx-r-card vx-kpi"><div class="vx-icon-kpi vx-icon-orange"><x-heroicon-o-tag/></div><div class="vx-kpi-copy"><div class="text-xs font-bold text-gray-500">Avg. Cost per Unit</div><div class="vx-kpi-value">${{ number_format($summary['avg_cost'],2) }}</div><div class="vx-kpi-sub">{{ number_format($summary['low_stock']) }} low stock · {{ number_format($summary['out_stock']) }} out of stock</div></div></button>
 </div>

 <div class="vx-flow">
 @foreach(['received'=>'Received / returned','removed'=>'Sold / removed','adjustments'=>'Corrections (net)','transfers'=>'Internal transfers'] as $key=>$label)
 <div class="vx-r-card p-4"><div class="text-xs font-bold text-gray-500">{{ $label }}</div><div class="vx-flow-number">{{ number_format($movement['current'][$key],2) }}</div><div class="text-xs text-gray-500">Previous period: {{ number_format($movement['previous'][$key],2) }} units</div></div>
 @endforeach
 </div>
 <div class="vx-r-card flex flex-wrap justify-between gap-3 p-4"><div><div class="text-xs font-bold text-gray-500">Net stock movement</div><div class="text-lg font-extrabold">{{ number_format($movement['current']['net'],2) }} units</div></div><div><div class="text-xs font-bold text-gray-500">Recorded movement cost (net)</div><div class="text-lg font-extrabold">${{ number_format($movement['current']['cost_change'],2) }}</div></div><p class="w-full text-xs text-gray-500">Transfers are shown separately. Movement cost uses recorded unit costs and excludes price-only changes; missing costs contribute $0. It is not a reconciliation of total inventory value.</p></div>
 <div class="vx-r-card p-4"><div class="flex flex-wrap justify-between gap-2"><h3 class="text-sm font-bold">Inventory inactivity</h3><button wire:click="setTab('aging')" class="text-xs font-bold text-primary-600">View idle inventory →</button></div><p class="mt-1 text-xs text-gray-500">Current stock grouped by days since last recorded movement, or creation if none. This measures inactivity, not lot age.</p><div class="vx-aging mt-4">
 @foreach([[30,59,'30–59 days'],[60,89,'60–89 days'],[90,null,'90+ days']] as [$min,$max,$label])
 @php $bucket=$idle->filter(fn($row)=>$row['idle_days'] >= $min && ($max===null || $row['idle_days'] <= $max)); @endphp
 <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-xs font-semibold">{{ $label }}</div><div class="mt-1 text-lg font-extrabold">${{ number_format($bucket->sum('total_value'),2) }}</div><div class="text-xs text-gray-500">{{ $bucket->count() }} items · {{ number_format($bucket->sum('quantity'),2) }} units</div></div>
 @endforeach
 </div></div>
 <div class="flex flex-wrap items-end justify-between gap-3 px-1 pt-1"><div><div class="text-[10px] font-extrabold uppercase tracking-wider text-primary-600">Inventory health</div><div class="mt-1 text-sm font-extrabold">Inventory Overview</div><div class="text-xs text-gray-500">At-a-glance inventory health, value and distribution.</div></div><div class="flex gap-2"><button wire:click="setTab('low-stock')" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700 dark:border-amber-500/20 dark:bg-amber-950/20 dark:text-amber-300">Low stock {{ number_format($summary['low_stock']) }}</button><button wire:click="setTab('out-stock')" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 dark:border-rose-500/20 dark:bg-rose-950/20 dark:text-rose-300">Out of stock {{ number_format($summary['out_stock']) }}</button></div></div>
 <div @if($locations->count() < 2) class="vx-report-grid vx-two-columns" @else class="vx-report-grid" @endif>
  <div class="vx-r-card p-4"><div class="flex items-center justify-between"><h3 class="text-sm font-bold">Global Inventory Value Trend</h3><span class="text-xs text-gray-500">{{ $this->periodStart()->format('M d') }} – {{ $this->periodEnd()->format('M d') }} · All inventory</span></div>@if($trend->isNotEmpty())@php $axisMax=max(1,ceil($maxTrend/100000)*100000); $midIndex=(int) floor(($trend->count()-1)/2); @endphp<div class="vx-chart-wrap"><div class="vx-y-axis"><span>${{ number_format($axisMax/1000,0) }}K</span><span>${{ number_format(($axisMax*.66)/1000,0) }}K</span><span>${{ number_format(($axisMax*.33)/1000,0) }}K</span><span>$0</span></div><div class="vx-chart-area"><div class="vx-chart">@foreach($trend as $point)<button type="button" class="vx-bar" aria-label="{{ $point['date'] }} inventory value ${{ number_format($point['value'],2) }}" style="height:{{ max(7,($point['value']/$axisMax)*100) }}%"><span class="vx-tooltip">{{ $point['date'] }} · ${{ number_format($point['value'],2) }}</span></button>@endforeach</div><div class="vx-x-axis"><span>{{ $trend->first()['date'] }}</span>@if($trend->count()>2)<span>{{ $trend[$midIndex]['date'] }}</span>@endif<span>{{ $trend->last()['date'] }}</span></div></div></div>@else<div class="py-16 text-center text-sm text-gray-400">Global snapshots will appear as data accumulates. Item and channel filters do not apply to this chart.</div>@endif</div>
  <div class="vx-r-card p-4"><h3 class="text-sm font-bold">Inventory by Category</h3><div class="mt-4 space-y-3">@forelse($categories->take(6) as $cat)<div><div class="flex justify-between text-xs"><span class="font-semibold">{{ $cat['name'] }}</span><span>${{ number_format($cat['value'],2) }} · {{ number_format($cat['quantity']) }} units</span></div><div class="vx-track mt-1"><div class="vx-fill" style="width:{{ $summary['value'] > 0 ? ($cat['value']/$summary['value'])*100 : 0 }}%"></div></div></div>@empty<div class="text-sm text-gray-400">No category data.</div>@endforelse</div></div>
  @if($locations->count() > 1)<div class="vx-r-card p-4"><h3 class="text-sm font-bold">Inventory by Location</h3>@forelse($locations->take(7) as $loc)<div class="vx-loc-row"><span class="truncate text-xs font-semibold">{{ $loc['name'] }}</span><div class="vx-track"><div class="vx-fill" style="width:{{ ($loc['quantity']/$maxLocation)*100 }}%"></div></div><span class="text-right text-xs">{{ number_format($loc['quantity']) }}<br><span class="text-gray-500">${{ number_format($loc['value'],2) }}</span></span></div>@empty<div class="mt-5 text-sm text-gray-400">No location data.</div>@endforelse</div>@endif
 </div>

 <div class="vx-report-sheet">
  <div class="vx-sheet-toolbar"><div class="vx-tabs border-0">
   @foreach(['items'=>'Inventory Items','low-stock'=>'Low Stock','out-stock'=>'Out of Stock','recent'=>'Recent Activity','top-value'=>'Top Value Items','moving'=>'Top Sold Items','aging'=>'Idle Inventory'] as $key=>$label)<button wire:click="setTab('{{ $key }}')" class="vx-tab {{ $activeTab===$key?'active':'' }}">{{ $label }}</button>@endforeach
   </div><div class="vx-sheet-actions flex items-center gap-2"><div class="relative hidden lg:block"><x-heroicon-o-magnifying-glass class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400"/><input wire:model.live.debounce.350ms="reportSearch" placeholder="Search within report..." class="w-48 rounded-lg border-gray-200 py-2 pl-8 text-xs dark:border-gray-700 dark:bg-gray-800"/></div><select wire:model.live="reportSort" class="rounded-lg border-gray-200 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800"><option value="value">Sort: Highest Value</option><option value="qty">Quantity: High-Low</option><option value="name">Name A-Z</option><option value="updated">Recently Updated</option></select></div></div>
  @if(!in_array($activeTab,['overview','recent','moving']))
  <div class="vx-report-table"><table class="vx-table"><thead><tr><th>Item</th><th>SKU</th><th>Category</th><th>Location</th><th class="text-right">Quantity</th><th class="text-right">Avg. Cost</th><th class="text-right">Total Value</th><th>Last Updated</th></tr></thead><tbody>
   @forelse($visible as $item)<tr><td><div class="flex items-center gap-3"><img src="{{ $item['image_url'] }}" alt="" loading="lazy" class="vx-thumb"/><div><a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record'=>$item['id']]) }}" class="font-bold text-primary-600">{{ $item['name'] }}</a>@if($activeTab==='aging')<div class="text-xs text-amber-600">{{ $item['idle_days'] }} days without movement</div>@endif</div></div></td><td>{{ $item['sku'] }}</td><td>{{ $item['category'] }}</td><td>{{ $item['locations'] }}</td><td class="text-right"><span class="vx-pill">{{ number_format($item['quantity']) }}</span></td><td class="text-right">${{ number_format($item['unit_cost'],2) }}</td><td class="text-right font-bold">${{ number_format($item['total_value'],2) }}</td><td>{{ $item['updated_at']?->format('M d, Y') }}</td></tr>@empty<tr><td colspan="8" class="py-10 text-center text-gray-500">No inventory matches this view.</td></tr>@endforelse
  </tbody></table></div>
  @elseif($activeTab==='recent')
  <div class="vx-report-table"><table class="vx-table"><thead><tr><th>Item</th><th>SKU</th><th>Activity</th><th>Location</th><th>Change</th><th>Updated / By</th></tr></thead><tbody>
   @forelse($recentRows as $row)<tr><td class="font-bold">{{ $row['name'] }}</td><td>{{ $row['sku'] }}</td><td>{{ $row['type'] }}</td><td>{{ $row['location'] }}</td><td class="font-bold">{{ $row['change'] }}</td><td>{{ $row['updated_at']?->format('M d, Y g:i A') }}<div class="text-xs text-gray-500">{{ $row['actor'] }}</div></td></tr>@empty<tr><td colspan="6" class="py-10 text-center text-gray-500">No recent inventory activity.</td></tr>@endforelse
  </tbody></table></div>
  @elseif($activeTab==='moving')
  <div class="vx-report-table"><table class="vx-table"><thead><tr><th>Item</th><th>SKU</th><th class="text-right">Units Sold</th><th>Period</th></tr></thead><tbody>
   @forelse($movingRows as $row)<tr><td class="font-bold">{{ $row['name'] }}</td><td>{{ $row['sku'] }}</td><td class="text-right font-bold">{{ number_format($row['moved_units']) }}</td><td>{{ $this->periodStart()->format('M d') }} – {{ $this->periodEnd()->format('M d') }}</td></tr>@empty<tr><td colspan="4" class="py-10 text-center text-gray-500">No movement data in the selected period.</td></tr>@endforelse
  </tbody></table></div>
  @else
  <div class="px-4 py-8 text-center text-sm text-gray-500">Select a report view above.</div>
  @endif
 <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 p-4 dark:border-gray-700"><span class="text-xs text-gray-500">{{ number_format($allRows->count()) }} matching rows · Page {{ $page }} of {{ $pageCount }}</span><div class="flex gap-2"><button wire:click="changeReportPage({{ $page-1 }})" @disabled($page<=1) class="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40">Previous</button><button wire:click="changeReportPage({{ $page+1 }})" @disabled($page>=$pageCount) class="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40">Next</button></div></div>
 </div>
 </main>
 </div>
</div>
</x-filament-panels::page>

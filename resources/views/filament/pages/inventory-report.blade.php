<x-filament-panels::page>
@php
 $data=$this->getViewerData(); $summary=$data['summary']; $items=collect($data['items']); $trend=collect($data['trend']); $locations=collect($data['locations']); $categories=collect($data['category_breakdown']);
 $visible = match($activeTab) {
  'low-stock' => $items->where('is_low_stock', true)->values(),
  'out-stock' => $items->where('is_out_stock', true)->values(),
  'top-value' => $items->where('quantity','>',0)->sortByDesc('total_value')->take(25)->values(),
  default => $items->values(),
 };
 $recentRows = $activeTab === 'recent' ? collect($this->getRecentActivityRows()) : collect();
 $movingRows = $activeTab === 'moving' ? collect($this->getTopMovingRows()) : collect();
 $maxTrend=max(1,(float)$trend->max('value')); $maxLocation=max(1,(float)$locations->max('quantity')); $totalQty=max(1,(float)$categories->sum('quantity'));
@endphp
<style>
.vx-report{--vx-purple:#6d28d9}.vx-report-head{display:flex;align-items:end;justify-content:space-between;gap:18px}.vx-report-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}.vx-filter-row{display:grid;grid-template-columns:minmax(260px,1.8fr) repeat(3,minmax(150px,.65fr));gap:10px}.vx-filter-control{min-height:42px;border:1px solid #e2e8f0!important;border-radius:10px!important;background:#fff!important;font-size:.78rem!important}.dark .vx-filter-control{background:#111827!important;border-color:#374151!important}.vx-icon-kpi{width:44px;height:44px;border-radius:13px;display:grid;place-items:center;flex:none}.vx-icon-kpi svg{width:22px;height:22px}.vx-icon-purple{background:#ede9fe;color:#7c3aed}.vx-icon-blue{background:#e0e7ff;color:#4f46e5}.vx-icon-green{background:#dcfce7;color:#16a34a}.vx-icon-orange{background:#ffedd5;color:#ea580c}.vx-kpi-copy{min-width:0;flex:1}.vx-kpi-change{margin-top:6px;font-size:.72rem;font-weight:700;color:#16a34a}.vx-kpi-sub{font-size:.68rem;color:#64748b;margin-top:1px}.vx-r-card{border:1px solid #e5e7eb;border-radius:14px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .vx-r-card{background:#111827;border-color:#374151}.vx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.vx-kpi{padding:18px;display:flex;gap:14px;align-items:flex-start}.vx-kpi-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:14px;background:#f3e8ff;color:#7c3aed;flex:none}.vx-kpi-icon svg{width:23px;height:23px}.vx-kpi-value{font-size:1.55rem;line-height:1.1;font-weight:800;margin-top:6px}.vx-report-grid{display:grid;grid-template-columns:1.2fr .9fr 1fr;gap:12px}.vx-chart{height:170px;display:flex;align-items:end;gap:5px;padding-top:18px}.vx-bar{flex:1;min-width:5px;border-radius:5px 5px 0 0;background:linear-gradient(180deg,#7c3aed,#a78bfa);opacity:.9}.vx-loc-row{display:grid;grid-template-columns:110px 1fr 72px;align-items:center;gap:8px;margin-top:12px}.vx-track{height:9px;border-radius:999px;background:#f1f5f9;overflow:hidden}.dark .vx-track{background:#263248}.vx-fill{height:100%;border-radius:999px;background:#8b5cf6}.vx-tabs{display:flex;gap:4px;overflow:auto;border-bottom:1px solid #e5e7eb}.dark .vx-tabs{border-color:#374151}.vx-tab{padding:12px 14px;white-space:nowrap;font-size:.78rem;font-weight:750;color:#64748b;border-bottom:2px solid transparent}.vx-tab.active{color:#6d28d9;border-color:#6d28d9}.vx-table{width:100%;border-collapse:collapse}.vx-table th{padding:10px 12px;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc}.dark .vx-table th{background:#182235}.vx-table td{padding:12px;border-top:1px solid #f1f5f9;font-size:.78rem}.dark .vx-table td{border-color:#263248}.vx-pill{display:inline-flex;border-radius:8px;padding:4px 8px;background:#ecfdf5;color:#047857;font-weight:800}.vx-export{position:relative}.vx-export-menu{position:absolute;right:0;top:calc(100% + 6px);z-index:30;width:210px;border:1px solid #e5e7eb;border-radius:12px;background:white;padding:6px;box-shadow:0 16px 40px rgba(15,23,42,.14)}.dark .vx-export-menu{background:#111827;border-color:#374151}.vx-export-menu button{display:flex;width:100%;padding:9px 10px;border-radius:8px;font-size:.8rem;font-weight:650}.vx-export-menu button:hover{background:#f8fafc}.dark .vx-export-menu button:hover{background:#1f2937}@media(max-width:1000px){.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-report-grid{grid-template-columns:1fr}.vx-filter-row{grid-template-columns:1fr 1fr}.vx-report-head{align-items:flex-start;flex-direction:column}.vx-report-actions{justify-content:flex-start}}@media(max-width:640px){.vx-filter-row{grid-template-columns:1fr}.vx-kpis{grid-template-columns:1fr 1fr}.vx-kpi{padding:13px}.vx-kpi-value{font-size:1.15rem}.vx-report-table{overflow:auto}.vx-table{min-width:760px}}
</style>
<div class="vx-report space-y-4">
 <div class="vx-report-head">
  <div><h2 class="text-2xl font-bold tracking-tight">Inventory Report</h2><p class="mt-1 text-sm text-gray-500">View your inventory value, quantities, item breakdowns and detailed insights.</p></div>
  <div class="vx-report-actions">
   <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-bold shadow-sm dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-arrow-left class="h-4 w-4"/> All Inventory</a>
   <div x-data="{open:false}" class="vx-export">
    <button @click="open=!open" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-primary-600 px-4 text-sm font-bold text-white shadow-sm hover:bg-primary-700"><x-heroicon-o-arrow-down-tray class="h-4 w-4"/> Export <x-heroicon-o-chevron-down class="h-4 w-4"/></button>
    <div x-cloak x-show="open" @click.outside="open=false" class="vx-export-menu">
     <button wire:click="exportPdf"><x-heroicon-o-document-text class="mr-2 h-4 w-4"/> Export to PDF</button><button wire:click="exportCsv"><x-heroicon-o-table-cells class="mr-2 h-4 w-4"/> Export to CSV</button><button onclick="window.print()"><x-heroicon-o-printer class="mr-2 h-4 w-4"/> Print Report</button>
    </div>
   </div>
  </div>
 </div>

 <div class="vx-r-card p-3">
  <div class="vx-filter-row">
   <div class="relative"><x-heroicon-o-magnifying-glass class="absolute left-3 top-3 h-4 w-4 text-gray-400"/><input wire:model.live.debounce.350ms="reportSearch" type="search" placeholder="Search inventory, SKU, or keyword..." class="vx-filter-control w-full py-2.5 pl-9"/></div>
   <select wire:model.live="reportCategory" class="vx-filter-control"><option value="">All Categories</option>@foreach($data['categories'] as $category)<option>{{ $category }}</option>@endforeach</select>
   <select wire:model.live="reportLocation" class="vx-filter-control"><option value="">All Locations</option>@foreach($locations as $loc)<option>{{ $loc['name'] }}</option>@endforeach</select>
   <select wire:model.live="reportStock" class="vx-filter-control"><option value="">All Stock</option><option value="in">In Stock</option><option value="low">Low Stock</option><option value="out">Out of Stock</option></select>
  </div>
  @if($reportSearch || $reportCategory || $reportLocation || $reportStock)<div class="mt-2 flex justify-end"><button wire:click="resetReportFilters" class="text-xs font-bold text-primary-600">Reset filters</button></div>@endif
 </div>

 <div class="vx-kpis">
  <div class="vx-r-card vx-kpi"><div class="vx-icon-kpi vx-icon-purple"><x-heroicon-o-cube/></div><div class="vx-kpi-copy"><div class="text-xs font-bold text-gray-500">Total Inventory Value</div><div class="vx-kpi-value">${{ number_format($summary['value'],2) }}</div><div class="vx-kpi-sub">Current on-hand valuation</div></div></div>
  <div class="vx-r-card vx-kpi"><div class="vx-icon-kpi vx-icon-blue"><x-heroicon-o-squares-2x2/></div><div class="vx-kpi-copy"><div class="text-xs font-bold text-gray-500">Total Items</div><div class="vx-kpi-value">{{ number_format($summary['items']) }}</div><div class="vx-kpi-sub">{{ number_format($summary['in_stock_items']) }} SKUs currently in stock</div></div></div>
  <div class="vx-r-card vx-kpi"><div class="vx-icon-kpi vx-icon-green"><x-heroicon-o-archive-box/></div><div class="vx-kpi-copy"><div class="text-xs font-bold text-gray-500">Total Quantity</div><div class="vx-kpi-value">{{ number_format($summary['quantity']) }}</div><div class="vx-kpi-sub">Units currently in stock</div></div></div>
  <div class="vx-r-card vx-kpi"><div class="vx-icon-kpi vx-icon-orange"><x-heroicon-o-tag/></div><div class="vx-kpi-copy"><div class="text-xs font-bold text-gray-500">Avg. Cost per Unit</div><div class="vx-kpi-value">${{ number_format($summary['avg_cost'],2) }}</div><div class="vx-kpi-sub">{{ number_format($summary['low_stock']) }} low stock · {{ number_format($summary['out_stock']) }} out of stock</div></div></div>
 </div>

 @if($activeTab==='overview')
 <div class="vx-report-grid">
  <div class="vx-r-card p-4"><div class="flex items-center justify-between"><h3 class="text-sm font-bold">Inventory Value Trend</h3><span class="text-xs text-gray-500">Last 30 Days</span></div><div class="vx-chart">@forelse($trend as $point)<div class="vx-bar" title="{{ $point['date'] }} — ${{ number_format($point['value'],2) }}" style="height:{{ max(7,($point['value']/$maxTrend)*100) }}%"></div>@empty<div class="m-auto text-sm text-gray-400">Trend data will appear as snapshots accumulate.</div>@endforelse</div></div>
  <div class="vx-r-card p-4"><h3 class="text-sm font-bold">Inventory by Category</h3><div class="mt-4 space-y-3">@forelse($categories->take(6) as $cat)<div><div class="flex justify-between text-xs"><span class="font-semibold">{{ $cat['name'] }}</span><span>{{ number_format($cat['quantity']) }} · {{ number_format(($cat['quantity']/$totalQty)*100,1) }}%</span></div><div class="vx-track mt-1"><div class="vx-fill" style="width:{{ ($cat['quantity']/$totalQty)*100 }}%"></div></div></div>@empty<div class="text-sm text-gray-400">No category data.</div>@endforelse</div></div>
  <div class="vx-r-card p-4"><h3 class="text-sm font-bold">Inventory by Location</h3>@forelse($locations->take(7) as $loc)<div class="vx-loc-row"><span class="truncate text-xs font-semibold">{{ $loc['name'] }}</span><div class="vx-track"><div class="vx-fill" style="width:{{ ($loc['quantity']/$maxLocation)*100 }}%"></div></div><span class="text-right text-xs">{{ number_format($loc['quantity']) }}</span></div>@empty<div class="mt-5 text-sm text-gray-400">No location data.</div>@endforelse</div>
 </div>
 @endif

 <div class="vx-r-card overflow-hidden">
  <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-3 dark:border-gray-700"><div class="vx-tabs border-0">
   @foreach(['items'=>'Inventory Items','low-stock'=>'Low Stock','out-stock'=>'Out of Stock','recent'=>'Recent Activity','top-value'=>'Top Value Items','moving'=>'Top Moving Items'] as $key=>$label)<button wire:click="setTab('{{ $key }}')" class="vx-tab {{ $activeTab===$key?'active':'' }}">{{ $label }}</button>@endforeach
   </div><select wire:model.live="reportSort" class="my-2 rounded-lg border-gray-200 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-800"><option value="value">Sort: Highest Value</option><option value="qty">Quantity: High-Low</option><option value="name">Name A-Z</option><option value="updated">Recently Updated</option></select></div>
  @if(!in_array($activeTab,['overview','recent','moving']))
  <div class="vx-report-table"><table class="vx-table"><thead><tr><th>Item</th><th>SKU</th><th>Category</th><th>Location</th><th class="text-right">Quantity</th><th class="text-right">Avg. Cost</th><th class="text-right">Total Value</th><th>Last Updated</th></tr></thead><tbody>
   @forelse($visible as $item)<tr><td><div class="font-bold">{{ $item['name'] }}</div></td><td>{{ $item['sku'] }}</td><td>{{ $item['category'] }}</td><td>{{ $item['locations'] }}</td><td class="text-right"><span class="vx-pill">{{ number_format($item['quantity']) }}</span></td><td class="text-right">${{ number_format($item['unit_cost'],2) }}</td><td class="text-right font-bold">${{ number_format($item['total_value'],2) }}</td><td>{{ $item['updated_at']?->format('M d, Y') }}</td></tr>@empty<tr><td colspan="8" class="py-10 text-center text-gray-500">No inventory matches this view.</td></tr>@endforelse
  </tbody></table></div>
  @elseif($activeTab==='recent')
  <div class="vx-report-table"><table class="vx-table"><thead><tr><th>Item</th><th>SKU</th><th>Activity</th><th>Location</th><th>Change</th><th>Updated</th></tr></thead><tbody>
   @forelse($recentRows as $row)<tr><td class="font-bold">{{ $row['name'] }}</td><td>{{ $row['sku'] }}</td><td>{{ $row['type'] }}</td><td>{{ $row['location'] }}</td><td class="font-bold">{{ $row['change'] }}</td><td>{{ $row['updated_at']?->format('M d, Y g:i A') }}</td></tr>@empty<tr><td colspan="6" class="py-10 text-center text-gray-500">No recent inventory activity.</td></tr>@endforelse
  </tbody></table></div>
  @elseif($activeTab==='moving')
  <div class="vx-report-table"><table class="vx-table"><thead><tr><th>Item</th><th>SKU</th><th class="text-right">Units Moved</th><th>Period</th></tr></thead><tbody>
   @forelse($movingRows as $row)<tr><td class="font-bold">{{ $row['name'] }}</td><td>{{ $row['sku'] }}</td><td class="text-right font-bold">{{ number_format($row['moved_units']) }}</td><td>Last 30 days</td></tr>@empty<tr><td colspan="4" class="py-10 text-center text-gray-500">No movement data in the last 30 days.</td></tr>@endforelse
  </tbody></table></div>
  @else
  <div class="px-4 py-8 text-center text-sm text-gray-500">Select a report view above.</div>
  @endif
 </div>
</div>
</x-filament-panels::page>
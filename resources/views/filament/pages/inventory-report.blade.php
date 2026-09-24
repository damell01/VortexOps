<x-filament-panels::page>
@php
 $data=$this->getViewerData(); $summary=$data['summary']; $items=collect($data['items']); $trend=collect($data['trend']); $locations=collect($data['locations']); $categories=collect($data['category_breakdown']);
 $visible=$activeTab==='low-stock'?$items->where('is_low_stock',true)->values():($activeTab==='top-value'?$items->sortByDesc('total_value')->take(20)->values():$items);
 $maxTrend=max(1,(float)$trend->max('value')); $maxLocation=max(1,(float)$locations->max('quantity')); $totalQty=max(1,(float)$categories->sum('quantity'));
@endphp
<style>
.vx-report{--vx-purple:#6d28d9}.vx-r-card{border:1px solid #e5e7eb;border-radius:14px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .vx-r-card{background:#111827;border-color:#374151}.vx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.vx-kpi{padding:18px;display:flex;gap:14px;align-items:flex-start}.vx-kpi-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:14px;background:#f3e8ff;color:#7c3aed;flex:none}.vx-kpi-icon svg{width:23px;height:23px}.vx-kpi-value{font-size:1.55rem;line-height:1.1;font-weight:800;margin-top:6px}.vx-report-grid{display:grid;grid-template-columns:1.2fr .9fr 1fr;gap:12px}.vx-chart{height:170px;display:flex;align-items:end;gap:5px;padding-top:18px}.vx-bar{flex:1;min-width:5px;border-radius:5px 5px 0 0;background:linear-gradient(180deg,#7c3aed,#a78bfa);opacity:.9}.vx-loc-row{display:grid;grid-template-columns:110px 1fr 72px;align-items:center;gap:8px;margin-top:12px}.vx-track{height:9px;border-radius:999px;background:#f1f5f9;overflow:hidden}.dark .vx-track{background:#263248}.vx-fill{height:100%;border-radius:999px;background:#8b5cf6}.vx-tabs{display:flex;gap:4px;overflow:auto;border-bottom:1px solid #e5e7eb}.dark .vx-tabs{border-color:#374151}.vx-tab{padding:12px 14px;white-space:nowrap;font-size:.78rem;font-weight:750;color:#64748b;border-bottom:2px solid transparent}.vx-tab.active{color:#6d28d9;border-color:#6d28d9}.vx-table{width:100%;border-collapse:collapse}.vx-table th{padding:10px 12px;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc}.dark .vx-table th{background:#182235}.vx-table td{padding:12px;border-top:1px solid #f1f5f9;font-size:.78rem}.dark .vx-table td{border-color:#263248}.vx-pill{display:inline-flex;border-radius:8px;padding:4px 8px;background:#ecfdf5;color:#047857;font-weight:800}.vx-export{position:relative}.vx-export-menu{position:absolute;right:0;top:calc(100% + 6px);z-index:30;width:210px;border:1px solid #e5e7eb;border-radius:12px;background:white;padding:6px;box-shadow:0 16px 40px rgba(15,23,42,.14)}.dark .vx-export-menu{background:#111827;border-color:#374151}.vx-export-menu button{display:flex;width:100%;padding:9px 10px;border-radius:8px;font-size:.8rem;font-weight:650}.vx-export-menu button:hover{background:#f8fafc}.dark .vx-export-menu button:hover{background:#1f2937}@media(max-width:1000px){.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-report-grid{grid-template-columns:1fr}}@media(max-width:640px){.vx-kpis{grid-template-columns:1fr 1fr}.vx-kpi{padding:13px}.vx-kpi-value{font-size:1.15rem}.vx-report-table{overflow:auto}.vx-table{min-width:760px}}
</style>
<div class="vx-report space-y-4">
 <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
  <div><h2 class="text-2xl font-bold tracking-tight">Inventory Report</h2><p class="mt-1 text-sm text-gray-500">Live inventory value, quantities, categories, locations and item detail.</p></div>
  <div x-data="{open:false}" class="vx-export">
   <button @click="open=!open" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-sm font-bold shadow-sm dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-arrow-down-tray class="h-4 w-4"/> Export <x-heroicon-o-chevron-down class="h-4 w-4"/></button>
   <div x-cloak x-show="open" @click.outside="open=false" class="vx-export-menu">
    <button wire:click="exportPdf">Export PDF</button><button wire:click="exportCsv">Export CSV</button><button onclick="window.print()">Print Report</button>
   </div>
  </div>
 </div>

 <div class="vx-r-card p-3"><div class="flex flex-col gap-2 lg:flex-row">
  <div class="relative flex-1"><x-heroicon-o-magnifying-glass class="absolute left-3 top-3 h-4 w-4 text-gray-400"/><input wire:model.live.debounce.350ms="reportSearch" type="search" placeholder="Search inventory, SKU, or keyword..." class="w-full rounded-lg border-gray-200 bg-gray-50 py-2.5 pl-9 text-sm dark:border-gray-700 dark:bg-gray-800"/></div>
  <select wire:model.live="reportCategory" class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800"><option value="">All Categories</option>@foreach($data['categories'] as $category)<option>{{ $category }}</option>@endforeach</select>
  <select wire:model.live="reportLocation" class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800"><option value="">All Locations</option>@foreach($locations as $loc)<option>{{ $loc['name'] }}</option>@endforeach</select>
 </div></div>

 <div class="vx-kpis">
  <div class="vx-r-card vx-kpi"><div class="text-xs font-bold text-gray-500">Total Inventory Value</div><div class="vx-kpi-value">${{ number_format($summary['value'],2) }}</div><div class="mt-2 text-xs text-emerald-600">Current on-hand valuation</div></div>
  <div class="vx-r-card vx-kpi"><div class="vx-kpi-icon"><x-heroicon-o-squares-2x2/></div><div><div class="text-xs font-bold text-gray-500">Total Items</div><div class="vx-kpi-value">{{ number_format($summary['items']) }}</div><div class="mt-2 text-xs text-gray-500">Unique SKUs</div></div></div>
  <div class="vx-r-card vx-kpi"><div class="vx-kpi-icon" style="background:#dcfce7;color:#16a34a"><x-heroicon-o-archive-box/></div><div><div class="text-xs font-bold text-gray-500">Total Quantity</div><div class="vx-kpi-value">{{ number_format($summary['quantity']) }}</div><div class="mt-2 text-xs text-gray-500">Units in stock</div></div></div>
  <div class="vx-r-card vx-kpi"><div class="text-xs font-bold text-gray-500">Avg. Cost per Unit</div><div class="vx-kpi-value">${{ number_format($summary['avg_cost'],2) }}</div><div class="mt-2 text-xs text-gray-500">{{ number_format($summary['low_stock']) }} low-stock items</div></div>
 </div>

 @if($activeTab==='overview')
 <div class="vx-report-grid">
  <div class="vx-r-card p-4"><div class="flex items-center justify-between"><h3 class="text-sm font-bold">Inventory Value Trend</h3><span class="text-xs text-gray-500">Last 30 Days</span></div><div class="vx-chart">@forelse($trend as $point)<div class="vx-bar" title="{{ $point['date'] }} — ${{ number_format($point['value'],2) }}" style="height:{{ max(7,($point['value']/$maxTrend)*100) }}%"></div>@empty<div class="m-auto text-sm text-gray-400">Trend data will appear as snapshots accumulate.</div>@endforelse</div></div>
  <div class="vx-r-card p-4"><h3 class="text-sm font-bold">Inventory by Category</h3><div class="mt-4 space-y-3">@forelse($categories->take(6) as $cat)<div><div class="flex justify-between text-xs"><span class="font-semibold">{{ $cat['name'] }}</span><span>{{ number_format($cat['quantity']) }} · {{ number_format(($cat['quantity']/$totalQty)*100,1) }}%</span></div><div class="vx-track mt-1"><div class="vx-fill" style="width:{{ ($cat['quantity']/$totalQty)*100 }}%"></div></div></div>@empty<div class="text-sm text-gray-400">No category data.</div>@endforelse</div></div>
  <div class="vx-r-card p-4"><h3 class="text-sm font-bold">Inventory by Location</h3>@forelse($locations->take(7) as $loc)<div class="vx-loc-row"><span class="truncate text-xs font-semibold">{{ $loc['name'] }}</span><div class="vx-track"><div class="vx-fill" style="width:{{ ($loc['quantity']/$maxLocation)*100 }}%"></div></div><span class="text-right text-xs">{{ number_format($loc['quantity']) }}</span></div>@empty<div class="mt-5 text-sm text-gray-400">No location data.</div>@endforelse</div>
 </div>
 @endif

 <div class="vx-r-card overflow-hidden">
  <div class="vx-tabs">
   @foreach(['items'=>'Inventory Items','low-stock'=>'Low Stock','out-stock'=>'Out of Stock','recent'=>'Recent Activity','top-value'=>'Top Value Items','moving'=>'Top Moving Items'] as $key=>$label)<button wire:click="setTab('{{ $key }}')" class="vx-tab {{ $activeTab===$key?'active':'' }}">{{ $label }}</button>@endforeach
  </div>
  @if(!in_array($activeTab,['overview','recent','moving','out-stock']))
  <div class="vx-report-table"><table class="vx-table"><thead><tr><th>Item</th><th>SKU</th><th>Category</th><th>Location</th><th class="text-right">Quantity</th><th class="text-right">Avg. Cost</th><th class="text-right">Total Value</th></tr></thead><tbody>
   @forelse($visible as $item)<tr><td><div class="font-bold">{{ $item['name'] }}</div></td><td>{{ $item['sku'] }}</td><td>{{ $item['category'] }}</td><td>{{ $item['locations'] }}</td><td class="text-right"><span class="vx-pill">{{ number_format($item['quantity']) }}</span></td><td class="text-right">${{ number_format($item['unit_cost'],2) }}</td><td class="text-right font-bold">${{ number_format($item['total_value'],2) }}</td></tr>@empty<tr><td colspan="7" class="py-10 text-center text-gray-500">No inventory matches this view.</td></tr>@endforelse
  </tbody></table></div>
  @else
  <div class="px-4 py-8 text-center text-sm text-gray-500">This report view will populate from live inventory activity.</div>
  @endif
 </div>
</div>
</x-filament-panels::page>
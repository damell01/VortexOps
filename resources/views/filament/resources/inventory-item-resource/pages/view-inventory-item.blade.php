<x-filament-panels::page>
@php
$tabs=['overview'=>'Overview','movements'=>'Activity','stock'=>'Locations','cost'=>'Price & Cost','receiving'=>'History','lots'=>'Lots','analysis'=>'Analysis','aliases'=>'Aliases'];
$totalQty=collect($this->stockByLocation)->sum('qty');$lots=$this->lots;$cost=$record->effectiveCost();$target=(float)($record->sale_price??0);$margin=($target>0&&$cost>0)?$target-$cost:null;$pct=($margin!==null&&$target>0)?($margin/$target)*100:null;
@endphp
<style>
.vx-item-shell{max-width:1100px;margin:auto;color:#111827}.dark .vx-item-shell{color:#f8fafc}.vx-card{border:1px solid rgb(229 231 235);background:white;border-radius:16px;color:#111827}.dark .vx-card{border-color:#334155;background:#101827;color:#f8fafc}.vx-tab{padding:11px 16px;border-bottom:2px solid transparent;white-space:nowrap;font-size:.875rem}.vx-tab.active{color:#a855f7;border-color:#a855f7}.vx-photo{cursor:zoom-in}.vx-table{width:100%;font-size:.875rem}.vx-table th{padding:.75rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}.vx-table td{padding:.8rem 1rem;border-top:1px solid rgb(229 231 235)}.dark .vx-table td{border-color:#263248}.vx-empty{padding:3rem 1rem;text-align:center;color:#6b7280}.vx-pill{display:inline-flex;align-items:center;border-radius:999px;padding:.18rem .55rem;font-size:.72rem;font-weight:600;background:#1f2937;color:#d1d5db}.vx-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.vx-stat{border:1px solid rgb(229 231 235);border-radius:12px;padding:1rem}.dark .vx-stat{border-color:#334155;background:#101827}.vx-stat-label{font-size:.72rem;color:#6b7280}.dark .vx-stat-label{color:#94a3b8}.vx-stat-value{margin-top:.2rem;font-size:1.2rem;font-weight:700;color:#111827}.dark .vx-stat-value{color:#f8fafc}.dark .vx-item-shell .text-gray-500{color:#94a3b8!important}.dark .vx-item-shell .text-gray-400{color:#cbd5e1!important}.dark .vx-item-shell h1,.dark .vx-item-shell h2,.dark .vx-item-shell h3,.dark .vx-item-shell b,.dark .vx-item-shell td{color:#f8fafc}.dark .vx-item-shell .vx-tab{color:#cbd5e1}.dark .vx-item-shell .vx-tab.active{color:#c084fc}.dark .vx-item-shell .vx-pill{background:#1e293b;color:#e2e8f0}
@media(max-width:640px){body:has(.vx-item-shell) .fi-page-header{display:none!important}.vx-item-shell{margin-top:0}.vx-hero{padding:16px!important}.vx-hero-main{display:grid!important;grid-template-columns:92px 1fr!important;gap:14px!important}.vx-hero-photo{width:92px!important;height:92px!important;border-radius:12px!important}.vx-metrics{grid-column:1/3;display:grid!important;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px}.vx-metric{border:1px solid #263248;border-radius:10px;padding:12px;text-align:center}.vx-actions{grid-column:1/3;display:grid!important;grid-template-columns:1fr 1fr;gap:8px}.vx-tabs{border-bottom:1px solid #e5e7eb!important}.vx-tab{flex:none!important}.vx-tab{flex:1;text-align:center;padding:12px 8px}.vx-overview{display:block!important}.vx-overview>.vx-card{margin-bottom:12px}.vx-summary-grid{display:grid!important;grid-template-columns:repeat(3,1fr);gap:7px}.vx-summary-grid>div{border:1px solid #263248;border-radius:10px;padding:12px 7px;text-align:center}.vx-desktop-stock{display:none!important}.vx-stat-grid{grid-template-columns:1fr 1fr}.vx-table{min-width:720px}.vx-table-wrap{overflow-x:auto}}

.ivx-hero{padding:18px}
/* The hero carries Edit / Add Stock / Delete; the page header above it would only repeat them. */
body:has(.vx-item-shell) .fi-header .fi-ac{display:none!important}
@media(max-width:767px){body:has(.vx-item-shell) .fi-header{display:none!important}}
.ivx-itabs{display:flex;gap:2px;overflow-x:auto;scrollbar-width:none;border-bottom:1px solid #e5e7eb}
.dark .ivx-itabs{border-color:#334155}
.ivx-itabs::-webkit-scrollbar{display:none}
.ivx-itabs button{flex:none;padding:11px 14px;border-bottom:2px solid transparent;margin-bottom:-1px;font-size:.84rem;font-weight:600;color:#64748b;white-space:nowrap}
.ivx-itabs button.on{color:var(--primary-600,#7c3aed);border-color:var(--primary-600,#7c3aed)}
.ivx-hero-top{display:flex;gap:16px;align-items:flex-start}
.ivx-hero-photo{width:96px;height:96px;flex:none;border-radius:14px;object-fit:cover;border:1px solid #e5e7eb;background:#f8fafc}
.dark .ivx-hero-photo{border-color:#334155;background:#182235}
.ivx-hero-name{font-size:1.25rem;font-weight:800;line-height:1.25}
.ivx-hero-sku{margin-top:4px;font-size:.78rem;color:#64748b}
.ivx-hp{display:inline-flex;padding:3px 10px;border-radius:999px;font-size:.7rem;font-weight:700}
.ivx-hp.green{background:#dcfce7;color:#15803d}.ivx-hp.amber{background:#fef3c7;color:#b45309}.ivx-hp.red{background:#fee2e2;color:#dc2626}.ivx-hp.violet{background:#ede9fe;color:#6d28d9}.ivx-hp.gray{background:#f1f5f9;color:#475569}
.ivx-hero-actions{display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-top:16px}
.ivx-hbtn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:44px;padding:0 12px;white-space:nowrap;border:1px solid #e5e7eb;border-radius:12px;font-size:.85rem;font-weight:700;background:#fff;color:#111827}
.dark .ivx-hbtn{background:#0f172a;border-color:#334155;color:#f8fafc}
.ivx-hbtn.primary{background:var(--primary-600,#7c3aed);border-color:var(--primary-600,#7c3aed);color:#fff}
.ivx-hbtn.icon{width:44px;padding:0;color:#dc2626}
.ivx-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
@media(min-width:900px){.ivx-stats{grid-template-columns:repeat(4,minmax(0,1fr))}.ivx-hero-actions{max-width:520px}}
.ivx-stat{padding:14px 16px;border:1px solid #e5e7eb;border-radius:14px;background:#fff}
.dark .ivx-stat{background:#101827;border-color:#334155}
.ivx-stat b{display:block;font-size:1.35rem;font-weight:800;font-variant-numeric:tabular-nums}
.ivx-stat b.neg{color:#dc2626}
.ivx-stat span{font-size:.74rem;color:#64748b}
.ivx-margin{padding:14px 16px}.ivx-margin .pos{color:#15803d}.ivx-margin .neg{color:#dc2626}
.ivx-details{padding:16px}
.ivx-details dl{margin-top:10px}
.ivx-details dl>div{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-top:1px solid #f1f5f9;font-size:.84rem}
.dark .ivx-details dl>div{border-color:#1f2937}
.ivx-details dt{color:#64748b}.ivx-details dd{font-weight:600;text-align:right;overflow-wrap:anywhere}
</style>
<div class="vx-item-shell space-y-4" x-data="{photoOpen:false}">
 @php
  $stockState = $totalQty <= 0 ? ['Out of stock', 'red'] : (($record->reorder_level !== null && $totalQty <= (float) $record->reorder_level) ? ['Low stock', 'amber'] : ['In stock', 'green']);
  $canEdit = \App\Filament\Resources\InventoryItemResource::canEdit($record);
  $canDelete = \App\Filament\Resources\InventoryItemResource::canDeleteAny();
  $locationsList = collect($this->stockByLocation)->where('qty', '>', 0)->pluck('location')->filter()->unique()->implode(', ');
 @endphp
 {{-- Hero: photo, name, SKU, status, and the two things you came to do. --}}
 <div class="vx-card ivx-hero">
  <div class="ivx-hero-top">
   <img @click="photoOpen=true" src="{{$record->imageUrl()}}" alt="{{$record->name}}" class="vx-photo ivx-hero-photo">
   <div class="min-w-0 flex-1">
    <h1 class="ivx-hero-name">{{$record->name}}</h1>
    <div class="ivx-hero-sku">SKU: {{$record->sku?:'—'}}</div>
    <div class="mt-2 flex flex-wrap gap-1.5">
     <span class="ivx-hp {{ $stockState[1] }}">{{ $stockState[0] }}</span>
     <span class="ivx-hp violet">{{ $record->is_container ? 'Case' : 'Item' }}</span>
     @unless($record->is_active)<span class="ivx-hp gray">Inactive</span>@endunless
    </div>
   </div>
  </div>
  <div class="ivx-hero-actions">
   @if($canEdit)<a href="{{\App\Filament\Resources\InventoryItemResource::getUrl('edit',['record'=>$record])}}" class="ivx-hbtn"><x-heroicon-o-pencil-square class="h-4 w-4"/> Edit</a>@endif
   @if($canEdit)<button type="button" wire:click="mountAction('add_stock')" class="ivx-hbtn primary"><x-heroicon-o-plus-circle class="h-4 w-4"/> Add Stock</button>@endif
   @if($canDelete)<button type="button" wire:click="mountAction('deleteItem')" class="ivx-hbtn icon" title="Delete item" aria-label="Delete item"><x-heroicon-o-trash class="h-4 w-4"/></button>@endif
  </div>
 </div>
 <nav class="ivx-itabs" aria-label="Item sections">@foreach($tabs as $key=>$label)<button type="button" wire:click="setTab('{{$key}}')" @class(['on' => $tab===$key])>{{$label}}</button>@endforeach</nav>
 @if($tab==='overview')
 <div class="ivx-stats">
  <div class="ivx-stat"><b @class(['neg' => $totalQty <= 0])>{{number_format($totalQty)}}</b><span>In Stock</span></div>
  <div class="ivx-stat"><b>${{number_format($cost,2)}}</b><span>Avg Cost</span></div>
  <div class="ivx-stat"><b>${{number_format(max(0,$totalQty)*$cost,2)}}</b><span>Total Value</span></div>
  <div class="ivx-stat"><b>{{$target?'$'.number_format($target,2):'—'}}</b><span>Sale Target</span></div>
 </div>
 @if($target > 0)
  <div class="vx-card ivx-margin">
   @if($cost > 0)
    <div class="flex items-center justify-between gap-3"><span class="font-semibold">Potential Margin</span><b class="{{ $margin >= 0 ? 'pos' : 'neg' }}">${{number_format($margin,2)}} / unit</b></div>
    <div class="mt-1 text-right text-xs" style="color:#64748b">{{number_format($pct,1)}}% of the sale target</div>
   @else
    <div class="flex items-center justify-between gap-3"><span class="font-semibold">Potential Margin</span><span class="text-sm" style="color:#b45309">needs a cost</span></div>
    <div class="mt-1 text-xs" style="color:#64748b">Receive stock with a unit cost (or set one) to see the margin against the ${{number_format($target,2)}} target.</div>
   @endif
  </div>
 @endif
 <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
  <div class="vx-card ivx-details">
   <h3 class="font-bold">Item Details</h3>
   <dl>
    @foreach(['SKU'=>$record->sku,'Barcode'=>$record->barcode,'Category'=>$record->category,'Type'=>$record->product_type ?: ($record->is_container?'Case':'Item'),'Brand'=>$record->brand,'Sold as'=>$record->sold_as,'Preferred Vendor'=>$record->preferredVendor?->name,'Locations'=>$locationsList] as $label=>$value)
     <div><dt>{{$label}}</dt><dd>{{ filled($value) ? $value : 'N/A' }}</dd></div>
    @endforeach
   </dl>
  </div>
  <div class="vx-card ivx-details">
   <h3 class="font-bold">Inventory Summary</h3>
   <dl>
    <div><dt>Total Stock</dt><dd>{{number_format($totalQty)}}</dd></div>
    <div><dt>Active Lots</dt><dd>{{collect($lots)->where('status','active')->count()}}</dd></div>
    <div><dt>Units Received (all time)</dt><dd>{{number_format((float)$record->total_units_received)}}</dd></div>
    <div><dt>List Cost</dt><dd>${{number_format((float)$record->unit_cost,2)}}</dd></div>
    <div><dt>Average Cost (FIFO, on hand)</dt><dd>${{number_format($cost,2)}}</dd></div>
    <div><dt>Reorder at</dt><dd>{{ $record->reorder_level !== null ? number_format((float)$record->reorder_level) : 'Not set' }}</dd></div>
   </dl>
   <p class="mt-3 text-xs leading-5" style="color:#64748b">Average Cost is a live FIFO average of what's currently on hand, priced at what was actually paid. It holds at the last real cost once sold out.</p>
  </div>
 </div>
 @endif
 @if($tab==='stock')<div class="vx-card overflow-hidden"><div class="p-5"><h3 class="font-bold">Stock by Location</h3></div>@forelse($this->stockByLocation as $s)<div class="flex justify-between border-t border-gray-800 px-5 py-4"><div><b>{{$s['location']}}</b><div class="text-xs text-gray-500">{{ucfirst($s['type'])}}</div></div><b>{{number_format($s['qty'])}}</b></div>@empty<div class="p-12 text-center text-gray-500">No stock on hand.</div>@endforelse</div>@endif
 @if($tab==='lots')<div class="vx-card p-5"><h3 class="font-bold">Active Lots ({{collect($lots)->where('status','active')->count()}})</h3>@forelse(collect($lots)->where('status','active') as $lot)<div class="mt-3 rounded-xl border border-gray-800 p-4"><div class="flex justify-between"><b>{{$lot['vendor']}}</b><span>{{$lot['remaining']}} remaining</span></div><div class="mt-1 text-xs text-gray-500">Received {{$lot['received_at']}} · ${{$lot['unit_cost']}}/unit</div></div>@empty<div class="mt-4 rounded-xl border border-gray-800 p-10 text-center"><div class="text-4xl text-primary-500">◇</div><b class="mt-3 block">No active lots</b><p class="mt-1 text-sm text-gray-500">This item doesn’t have any active lots yet.</p></div>@endforelse</div>@endif

 @if($tab==='receiving')
 <div class="vx-card overflow-hidden">
  <div class="p-5"><h3 class="font-bold">Receiving History</h3><p class="mt-1 text-xs text-gray-500">Every pallet/case receipt recorded for this item.</p></div>
  @if(count($this->receivingHistory)===0)<div class="vx-empty">No receiving history for this item yet.</div>@else
  <div class="vx-table-wrap"><table class="vx-table"><thead><tr><th>Date</th><th>Pallet</th><th>Vendor</th><th class="text-right">Cases</th><th class="text-right">Received</th><th class="text-right">Unit Cost</th><th>Match</th></tr></thead><tbody>
   @foreach($this->receivingHistory as $row)<tr><td>{{$row['date']}}</td><td>@if($row['pallet_url'])<a class="font-semibold text-primary-500" href="{{$row['pallet_url']}}">{{$row['pallet']}}</a>@else{{$row['pallet']}}@endif</td><td>{{$row['vendor']}}</td><td class="text-right">{{number_format($row['cases'])}}</td><td class="text-right">{{number_format($row['received'])}}</td><td class="text-right">${{number_format((float)$row['unit_cost'],2)}}</td><td><span class="vx-pill">{{$row['confidence']}}% · {{$row['stage']}}</span></td></tr>@endforeach
  </tbody></table></div>@endif
 </div>
 @endif

 @if($tab==='movements')
 <div class="vx-card overflow-hidden">
  <div class="p-5"><h3 class="font-bold">Inventory Movements</h3><p class="mt-1 text-xs text-gray-500">Receipts, transfers, adjustments, damage, returns, and other stock changes.</p></div>
  @if(count($this->movements)===0)<div class="vx-empty">No inventory movements recorded yet.</div>@else
  <div class="vx-table-wrap"><table class="vx-table"><thead><tr><th>When</th><th>Type</th><th class="text-right">Change</th><th>Location</th><th>Reason</th><th class="text-right">After</th></tr></thead><tbody>
   @foreach($this->movements as $m)<tr><td class="whitespace-nowrap">{{$m['date']}}</td><td><span class="vx-pill">{{ucfirst(str_replace('_',' ',$m['type']))}}</span></td><td class="text-right font-bold {{$m['qty']<0?'text-red-500':'text-emerald-500'}}">{{$m['label']}}</td><td>{{$m['location']}}</td><td>{{$m['reason']}}@if(($m['grouped']??1)>1)<span class="ml-1 text-xs text-gray-500">({{$m['grouped']}} grouped)</span>@endif</td><td class="text-right">{{$m['after']!==null?number_format((float)$m['after']):'—'}}</td></tr>@endforeach
  </tbody></table></div>@endif
 </div>
 @endif

 @if($tab==='cost')
 @php $costHistory=$this->costHistory;$costMetrics=$this->costMetrics; @endphp
 <div class="space-y-4">
  <div class="vx-stat-grid"><div class="vx-stat"><div class="vx-stat-label">Current Avg Cost</div><div class="vx-stat-value">${{number_format((float)$costMetrics['current_average_cost'],2)}}</div></div><div class="vx-stat"><div class="vx-stat-label">Inventory Value</div><div class="vx-stat-value">${{number_format((float)$costMetrics['inventory_value'],2)}}</div></div><div class="vx-stat"><div class="vx-stat-label">Lowest Recorded</div><div class="vx-stat-value">${{number_format((float)$costMetrics['min_cost'],2)}}</div></div><div class="vx-stat"><div class="vx-stat-label">Highest Recorded</div><div class="vx-stat-value">${{number_format((float)$costMetrics['max_cost'],2)}}</div></div></div>
  <div class="vx-card overflow-hidden"><div class="p-5"><h3 class="font-bold">Cost History</h3><p class="mt-1 text-xs text-gray-500">Historical receipt and adjustment costs used to build this item’s weighted average cost.</p></div>
   @if(count($costHistory)===0)<div class="vx-empty">No cost history recorded yet.</div>@else<div class="vx-table-wrap"><table class="vx-table"><thead><tr><th>When</th><th>Type</th><th class="text-right">Unit Cost</th><th class="text-right">Quantity</th><th>Source</th><th>Note</th></tr></thead><tbody>
    @foreach($costHistory as $row)<tr><td class="whitespace-nowrap">{{$row['display']}}</td><td><span class="vx-pill">{{ucfirst($row['type'])}}</span></td><td class="text-right font-semibold">{{$row['unit_cost']!==null?'$'.number_format((float)$row['unit_cost'],2):'Unknown'}}</td><td class="text-right">{{number_format((float)$row['qty'])}}</td><td>{{ucfirst(str_replace('_',' ',$row['source']??'—'))}}</td><td>{{$row['note']}}</td></tr>@endforeach
   </tbody></table></div>@endif
  </div>
 </div>
 @endif

 @if($tab==='analysis')
 @php $analysis=$this->costAnalysis; @endphp
 <div class="space-y-4">
  <div class="vx-stat-grid"><div class="vx-stat"><div class="vx-stat-label">Total Invested</div><div class="vx-stat-value">${{number_format((float)$analysis['total_invested'],2)}}</div></div><div class="vx-stat"><div class="vx-stat-label">Current Value</div><div class="vx-stat-value">${{number_format((float)$analysis['current_value'],2)}}</div></div><div class="vx-stat"><div class="vx-stat-label">Weighted Avg Cost</div><div class="vx-stat-value">${{number_format((float)$analysis['weighted_avg_cost'],2)}}</div></div><div class="vx-stat"><div class="vx-stat-label">Current Stock</div><div class="vx-stat-value">{{number_format((float)$analysis['current_stock'])}}</div></div></div>
  <div class="vx-card overflow-hidden"><div class="p-5"><h3 class="font-bold">Cost by Vendor</h3><p class="mt-1 text-xs text-gray-500">Where this item has been sourced and what each source has cost.</p></div>
   @if(count($analysis['by_vendor'])===0)<div class="vx-empty">No vendor cost data yet.</div>@else<div class="vx-table-wrap"><table class="vx-table"><thead><tr><th>Vendor</th><th class="text-right">Lots</th><th class="text-right">Units</th><th class="text-right">Avg Unit Cost</th><th class="text-right">Total Cost</th></tr></thead><tbody>
    @foreach($analysis['by_vendor'] as $vendor)<tr><td class="font-semibold">{{$vendor['vendor']}}</td><td class="text-right">{{number_format($vendor['lots_count'])}}</td><td class="text-right">{{number_format((float)$vendor['total_units'])}}</td><td class="text-right">${{number_format((float)$vendor['avg_unit_cost'],2)}}</td><td class="text-right">${{number_format((float)$vendor['total_cost'],2)}}</td></tr>@endforeach
   </tbody></table></div>@endif
  </div>
  <div class="vx-card overflow-hidden"><div class="p-5"><h3 class="font-bold">Active Lot Value</h3></div>@if(count($analysis['active_lots'])===0)<div class="vx-empty">No active lots to analyze.</div>@else<div class="vx-table-wrap"><table class="vx-table"><thead><tr><th>Vendor</th><th>Received</th><th class="text-right">Remaining</th><th class="text-right">Unit Cost</th><th class="text-right">Lot Cost</th><th class="text-right">% of Stock</th></tr></thead><tbody>@foreach($analysis['active_lots'] as $lot)<tr><td>{{$lot['vendor']}}</td><td>{{$lot['received_at']}}</td><td class="text-right">{{number_format((float)$lot['remaining'])}}</td><td class="text-right">${{number_format((float)$lot['unit_cost'],2)}}</td><td class="text-right">${{number_format((float)$lot['total_cost'],2)}}</td><td class="text-right">{{number_format((float)$lot['pct_of_stock'],1)}}%</td></tr>@endforeach</tbody></table></div>@endif</div>
 </div>
 @endif

 @if($tab==='aliases')
 <div class="vx-card overflow-hidden"><div class="p-5"><h3 class="font-bold">Aliases & Product Identities</h3><p class="mt-1 text-xs text-gray-500">Barcodes, UPCs, vendor SKUs and aliases learned for this item.</p></div>
  @if(count($this->aliases)===0)<div class="vx-empty">No aliases or alternate identifiers learned yet.</div>@else<div class="vx-table-wrap"><table class="vx-table"><thead><tr><th>Type</th><th>Value</th><th class="text-right">Confirmed</th><th class="text-right">Confidence</th><th>Last Seen</th></tr></thead><tbody>@foreach($this->aliases as $alias)<tr><td><span class="vx-pill">{{ucfirst(str_replace('_',' ',$alias['type']))}}</span></td><td class="font-mono">{{$alias['value']}}</td><td class="text-right">{{number_format((int)$alias['times'])}}</td><td class="text-right">{{$alias['confidence']}}%</td><td>{{$alias['last_seen']}}</td></tr>@endforeach</tbody></table></div>@endif
 </div>
 @endif

 <div x-show="photoOpen" x-cloak @keydown.escape.window="photoOpen=false" @click.self="photoOpen=false" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-4"><button @click="photoOpen=false" class="absolute right-5 top-5 h-12 w-12 rounded-full bg-white/10 text-2xl text-white">×</button><img src="{{$record->imageUrl()}}" alt="{{$record->name}} enlarged" class="max-h-[88vh] max-w-[95vw] rounded-2xl object-contain shadow-2xl"></div>
</div>
</x-filament-panels::page>
<x-filament-panels::page>
@php $s=$this->summary; @endphp
<style>
.vx-fulfill{max-width:1480px;margin:0 auto}.vx-f-card{border:1px solid rgb(229 231 235);background:#fff;border-radius:16px}.dark .vx-f-card{border-color:#263248;background:#101827}.vx-f-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.vx-f-kpi{padding:14px}.vx-f-tabs{display:flex;gap:8px;overflow-x:auto}.vx-f-tab{white-space:nowrap;border:1px solid rgb(229 231 235);border-radius:10px;padding:9px 13px;font-size:.82rem;font-weight:700}.dark .vx-f-tab{border-color:#334155}.vx-f-tab.active{background:#7c3aed;color:white;border-color:#7c3aed}.vx-f-row{display:grid;grid-template-columns:minmax(220px,1.5fr) minmax(150px,.8fr) 90px 120px 140px minmax(180px,.9fr);gap:12px;align-items:center;padding:14px 16px;border-top:1px solid rgb(243 244 246)}.dark .vx-f-row{border-color:#1f2937}.vx-f-head{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;font-weight:700}.vx-f-status{display:inline-flex;border-radius:999px;padding:.25rem .55rem;font-size:.7rem;font-weight:700;background:#f1f5f9}.dark .vx-f-status{background:#1e293b}.vx-f-actions{display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap}.vx-f-btn{border:1px solid #cbd5e1;border-radius:8px;padding:7px 9px;font-size:.72rem;font-weight:700}.dark .vx-f-btn{border-color:#475569}.vx-f-btn.primary{background:#7c3aed;color:#fff;border-color:#7c3aed}.vx-f-btn.success{background:#059669;color:#fff;border-color:#059669}
@media(max-width:1100px){.vx-f-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.vx-f-row{grid-template-columns:minmax(0,1.6fr) 1fr 80px 110px}.vx-f-row>.vx-hide-tablet{display:none}.vx-f-actions{grid-column:auto}}
@media(max-width:700px){.vx-fulfill{width:100%}.vx-f-kpis{grid-template-columns:1fr 1fr;gap:8px}.vx-f-kpi{padding:12px}.vx-f-row{display:block;padding:14px}.vx-f-row.vx-f-head{display:none}.vx-f-mobile-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.vx-f-actions{justify-content:flex-start;margin-top:12px}.vx-f-card{border-radius:14px}}
</style>
<div class="vx-fulfill space-y-4">
 <div class="vx-f-kpis">
  @foreach([
   ['Open',$s['open'],'Active work'],['Ready / Packed',$s['ready'],'Ready for carrier'],['In Transit',$s['inTransit'],'Shipped orders'],['Delivered',$s['delivered'],'Completed'],['Exceptions',$s['exceptions'],'Needs review'],['Open Units',$s['unitsOpen'],'Units still moving']
  ] as [$label,$value,$sub])
   <div class="vx-f-card vx-f-kpi"><div class="text-xs font-medium text-gray-500">{{$label}}</div><div class="mt-1 text-2xl font-bold">{{number_format($value)}}</div><div class="mt-1 text-xs text-gray-400">{{$sub}}</div></div>
  @endforeach
 </div>

 <section class="vx-f-card p-4">
  <div class="flex flex-wrap items-center gap-3">
   <div class="vx-f-tabs">
    <button wire:click="setTab('queue')" class="vx-f-tab {{$tab==='queue'?'active':''}}">Queue</button>
    <button wire:click="setTab('exceptions')" class="vx-f-tab {{$tab==='exceptions'?'active':''}}">Exceptions</button>
    <button wire:click="setTab('history')" class="vx-f-tab {{$tab==='history'?'active':''}}">History</button>
   </div>
   <div class="ml-auto flex min-w-0 flex-1 flex-wrap justify-end gap-2">
    <input type="search" wire:model.live.debounce.300ms="searchQuery" placeholder="Buyer, show, order or tracking…" class="min-w-[220px] flex-1 rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800">
    <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800">
     <option value="all">All statuses</option><option value="ready_to_ship">Ready to ship</option><option value="packed">Packed</option><option value="shipped">Shipped</option><option value="in_transit">In transit</option><option value="delivered">Delivered</option><option value="returned">Returned</option>
    </select>
   </div>
  </div>
 </section>

 <section class="vx-f-card overflow-hidden">
  <div class="vx-f-row vx-f-head"><div>Show / Buyer</div><div>Order</div><div>Items</div><div>Status</div><div class="vx-hide-tablet">Tracking</div><div class="text-right">Actions</div></div>
  @forelse($this->shipments as $shipment)
   @php $show=$shipment->show; $status=strtolower((string)$shipment->status); @endphp
   <div class="vx-f-row">
    <div class="min-w-0"><div class="truncate text-sm font-bold">{{$show?->title ?? 'Unknown Show'}}</div><div class="mt-1 truncate text-xs text-gray-500">{{$shipment->buyer_username ?: 'Buyer missing'}}@if($show?->show_date) · {{$show->show_date->format('M j')}}@endif</div></div>
    <div class="min-w-0"><div class="truncate text-xs font-mono">{{$shipment->whatnot_order_id ?: '—'}}</div><div class="mt-1 text-xs text-gray-400">{{$shipment->carrier ?: 'Carrier TBD'}}</div></div>
    <div class="vx-f-mobile-grid"><div><span class="text-xs text-gray-400 md:hidden">Items</span><div class="font-bold">{{number_format((int)$shipment->item_count)}}</div></div></div>
    <div><span class="vx-f-status">{{ucwords(str_replace('_',' ',$status ?: 'unknown'))}}</span></div>
    <div class="vx-hide-tablet min-w-0"><div class="truncate text-xs">{{$shipment->tracking_number ?: 'No tracking yet'}}</div></div>
    <div class="vx-f-actions">
     @if(!in_array($status,['delivered','returned'],true))
      @if($status!=='packed')<button wire:click="markPacked({{$shipment->id}})" class="vx-f-btn">Packed</button>@endif
      @if($status!=='ready_to_ship')<button wire:click="markReady({{$shipment->id}})" class="vx-f-btn success">Ready</button>@endif
     @endif
     @if($show)<a href="{{$this->showUrl($show->id)}}" class="vx-f-btn primary">Show</a>@endif
    </div>
   </div>
  @empty
   <div class="p-12 text-center"><div class="text-sm font-semibold">No shipments in this view.</div><div class="mt-1 text-xs text-gray-500">Try another tab, status, or search.</div></div>
  @endforelse
 </section>
</div>
</x-filament-panels::page>

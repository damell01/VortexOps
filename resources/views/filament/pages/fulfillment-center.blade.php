<x-filament-panels::page>
@php $s=$this->summary; @endphp
<div class="space-y-6">
 <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
  @foreach([
   ['Open',$s['open'],'Active work'],['Ready / Packed',$s['ready'],'Ready for carrier'],['In Transit',$s['inTransit'],'Shipped orders'],['Delivered',$s['delivered'],'Completed'],['Exceptions',$s['exceptions'],'Needs review'],['Open Units',$s['unitsOpen'],'Units still moving']
  ] as [$label,$value,$sub])
   <div class="vx-card p-4"><div class="text-xs font-medium text-gray-500">{{$label}}</div><div class="mt-1 text-2xl font-bold">{{number_format($value)}}</div><div class="mt-1 text-xs text-gray-400">{{$sub}}</div></div>
  @endforeach
 </div>

 <section class="vx-card p-4">
  <div class="flex flex-wrap items-center gap-3">
   <div class="flex gap-5 overflow-x-auto border-b border-[var(--vx-border)]">
    <button wire:click="setTab('queue')" class="min-h-10 border-b-2 border-transparent px-1 text-sm font-semibold text-[var(--vx-muted)] {{$tab==='queue'?'active':''}}">Queue</button>
    <button wire:click="setTab('exceptions')" class="min-h-10 border-b-2 border-transparent px-1 text-sm font-semibold text-[var(--vx-muted)] {{$tab==='exceptions'?'active':''}}">Exceptions</button>
    <button wire:click="setTab('history')" class="min-h-10 border-b-2 border-transparent px-1 text-sm font-semibold text-[var(--vx-muted)] {{$tab==='history'?'active':''}}">History</button>
   </div>
   <div class="ml-auto flex min-w-0 flex-1 flex-wrap justify-end gap-2">
    <input type="search" wire:model.live.debounce.300ms="searchQuery" placeholder="Buyer, show, order or tracking…" class="min-w-[220px] flex-1 rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800">
    <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800">
     <option value="all">All statuses</option><option value="ready_to_ship">Ready to ship</option><option value="packed">Packed</option><option value="shipped">Shipped</option><option value="in_transit">In transit</option><option value="delivered">Delivered</option><option value="returned">Returned</option>
    </select>
   </div>
  </div>
 </section>

 <section class="vx-card overflow-hidden">
  <div class="hidden h-10 grid-cols-[minmax(220px,1.5fr)_minmax(150px,.8fr)_90px_120px_140px_minmax(180px,.9fr)] items-center gap-3 bg-[#fafafc] px-4 text-xs font-semibold text-[var(--vx-muted)] lg:grid"><div>Show / Buyer</div><div>Order</div><div>Items</div><div>Status</div><div class="vx-hide-tablet">Tracking</div><div class="text-right">Actions</div></div>
  @forelse($this->shipments as $shipment)
   @php $show=$shipment->show; $status=strtolower((string)$shipment->status); @endphp
   <div class="grid gap-3 border-t border-[var(--vx-divider)] p-4 lg:grid-cols-[minmax(220px,1.5fr)_minmax(150px,.8fr)_90px_120px_140px_minmax(180px,.9fr)] lg:items-center">
    <div class="min-w-0"><div class="truncate text-sm font-bold">{{$show?->title ?? 'Unknown Show'}}</div><div class="mt-1 truncate text-xs text-gray-500">{{$shipment->buyer_username ?: 'Buyer missing'}}@if($show?->show_date) · {{$show->show_date->format('M j')}}@endif</div></div>
    <div class="min-w-0"><div class="truncate text-xs font-mono">{{$shipment->whatnot_order_id ?: '—'}}</div><div class="mt-1 text-xs text-gray-400">{{$shipment->carrier ?: 'Carrier TBD'}}</div></div>
    <div class="vx-f-mobile-grid"><div><span class="text-xs text-gray-400 md:hidden">Items</span><div class="font-bold">{{number_format((int)$shipment->item_count)}}</div></div></div>
    <div><span class="vx-status vx-status--draft">{{ucwords(str_replace('_',' ',$status ?: 'unknown'))}}</span></div>
    <div class="vx-hide-tablet min-w-0"><div class="truncate text-xs">{{$shipment->tracking_number ?: 'No tracking yet'}}</div></div>
    <div class="flex flex-wrap gap-2 lg:justify-end">
     @if(!in_array($status,['delivered','returned'],true))
      @if($status!=='packed')<button wire:click="markPacked({{$shipment->id}})" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold">Packed</button>@endif
      @if($status!=='ready_to_ship')<button wire:click="markReady({{$shipment->id}})" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold success">Ready</button>@endif
     @endif
     @if($show)<a href="{{$this->showUrl($show->id)}}" class="inline-flex min-h-10 items-center justify-center rounded-[10px] border border-[var(--vx-border)] px-3 text-xs font-semibold primary">Show</a>@endif
    </div>
   </div>
  @empty
   <div class="p-12 text-center"><div class="text-sm font-semibold">No shipments in this view.</div><div class="mt-1 text-xs text-gray-500">Try another tab, status, or search.</div></div>
  @endforelse
 </section>
</div>
</x-filament-panels::page>

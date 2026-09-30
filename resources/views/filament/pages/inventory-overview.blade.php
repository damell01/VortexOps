<x-filament-panels::page>
@php $s=$this->inventorySnapshot; $trend=$this->valueTrend; $maxTrend=max(1,(float)$trend->max('value')); $axisMax=max(1,ceil($maxTrend/100000)*100000); $midIndex=(int) floor(max(0,$trend->count()-1)/2); @endphp
<style>
.vx-inventory-shell a{transition:border-color .15s,box-shadow .15s,transform .15s}.vx-inventory-shell a:hover{border-color:#8b5cf6;box-shadow:0 5px 18px rgba(109,40,217,.08)}.vx-inventory-shell .fi-section{border-color:#e6e1f2}.vx-inventory-shell .vx-progress span{background:#7c3aed}.dark .vx-inventory-shell .fi-section{border-color:#342b4a}
@media(max-width:640px){.vx-inventory-shell{gap:12px}.vx-inventory-shell .fi-section-content{padding:14px}}
</style>
<div class="vx-inventory-shell space-y-6">
 <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_260px]">
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
   <a href="{{ $this->receiveUrl() }}" class="rounded-xl border border-primary-500 bg-violet-600 p-4 text-white shadow-sm"><div class="flex items-center gap-3"><x-heroicon-o-inbox-arrow-down class="h-6 w-6"/><div><div class="font-bold">Receive Inventory</div><div class="text-xs opacity-80">Pallets & receiving</div></div></div></a>
   <a href="{{ $this->quickAddUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-plus-circle class="h-6 w-6 text-violet-600"/><div><div class="font-bold">Quick Add Stock</div><div class="text-xs text-[var(--vx-muted)]">Add quantity fast</div></div></div></a>
   <a href="{{ $this->scanUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-qr-code class="h-6 w-6 text-violet-600"/><div><div class="font-bold">Scan Inventory</div><div class="text-xs text-[var(--vx-muted)]">Barcode workflow</div></div></div></a>
   <a href="{{ $this->addItemUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-cube class="h-6 w-6 text-violet-600"/><div><div class="font-bold">Add Item</div><div class="text-xs text-[var(--vx-muted)]">Create new SKU</div></div></div></a>
   <a href="{{ $this->transferUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-arrows-right-left class="h-6 w-6 text-violet-600"/><div><div class="font-bold">Transfer Stock</div><div class="text-xs text-[var(--vx-muted)]">Move between locations</div></div></div></a>
   <a href="{{ $this->countUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-clipboard-document-check class="h-6 w-6 text-violet-600"/><div><div class="font-bold">Inventory Count</div><div class="text-xs text-[var(--vx-muted)]">Count & reconcile</div></div></div></a>
  </div>
  <div class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="font-bold">Inventory tools</div><div class="mt-3 grid gap-2 text-sm"><a href="{{ $this->reportUrl() }}" class="flex justify-between rounded-lg border border-[var(--vx-divider)] p-2.5"><span>Inventory Report</span><span>→</span></a><a href="{{ $this->movementsUrl() }}" class="flex justify-between rounded-lg border border-[var(--vx-divider)] p-2.5"><span>Activity History</span><span>→</span></a><a href="{{ $this->locationsUrl() }}" class="flex justify-between rounded-lg border border-[var(--vx-divider)] p-2.5"><span>Locations</span><span>→</span></a><a href="{{ $this->vendorsUrl() }}" class="flex justify-between rounded-lg border border-[var(--vx-divider)] p-2.5"><span>Vendors</span><span>→</span></a></div></div>
 </div>
 <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
  @foreach([['Inventory value','
  <x-filament::section><div class="flex items-start justify-between"><div><div class="text-sm text-[var(--vx-muted)]">{{ $label }}</div><div class="vx-kpi-value mt-2">{{ $value }}</div></div><x-filament::icon :icon="$icon" class="h-5 w-5 text-[var(--vx-faint)]"/></div></x-filament::section>
  @endforeach
 </div>
 <x-filament::section heading="Inventory Value Trend" description="On-hand inventory value over the last 30 days.">
  @if($trend->isNotEmpty())
   <div class="grid grid-cols-[54px_minmax(0,1fr)] gap-2">
    <div class="flex h-44 flex-col justify-between pb-5 text-right text-[10px] text-[var(--vx-muted)]"><span>${{ number_format($axisMax/1000,0) }}K</span><span>${{ number_format(($axisMax*.66)/1000,0) }}K</span><span>${{ number_format(($axisMax*.33)/1000,0) }}K</span><span>$0</span></div>
    <div><div class="flex h-40 items-end gap-1 border-b border-[var(--vx-divider)]">@foreach($trend as $point)<div class="group relative flex-1 rounded-t bg-violet-500 hover:bg-violet-600" style="height:{{ max(7,($point['value']/$axisMax)*100) }}%" tabindex="0"><span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-950 px-2 py-1.5 text-[10px] font-bold text-white group-hover:block group-focus:block">{{ $point['date'] }} · ${{ number_format($point['value'],2) }}</span></div>@endforeach</div><div class="flex justify-between pt-1.5 text-[10px] text-[var(--vx-muted)]"><span>{{ $trend->first()['date'] }}</span>@if($trend->count()>2)<span>{{ $trend[$midIndex]['date'] }}</span>@endif<span>{{ $trend->last()['date'] }}</span></div></div>
   </div>
  @else<div class="py-10 text-center text-sm text-[var(--vx-muted)]">Trend data will appear as snapshots accumulate.</div>@endif
 </x-filament::section>
 <div class="grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
  <x-filament::section heading="Stock Status" description="Current catalog health across active SKUs.">
   <div class="space-y-5">@foreach([['In stock',$s['in'],$s['percentages']['in']],['Low stock',$s['low'],$s['percentages']['low']],['Out of stock',$s['out'],$s['percentages']['out']]] as [$label,$count,$pct])<div><div class="mb-2 flex justify-between gap-4 text-sm"><span class="font-medium">{{ $label }}</span><span class="vx-mono">{{ number_format($count) }} items</span></div><div class="vx-progress"><span style="width:{{ $pct }}%"></span></div></div>@endforeach</div>
  </x-filament::section>
  <x-filament::section heading="Inventory Health" description="Fresh inventory and items that need attention.">
   <div class="grid grid-cols-2 gap-4"><a href="{{ $this->ageUrl() }}" class="rounded-[10px] border border-[var(--vx-divider)] p-4"><div class="text-xs font-semibold text-[var(--vx-muted)]">Fresh inventory</div><div class="vx-kpi-value mt-2">{{ number_format($s['in']) }}</div><div class="mt-2 text-xs text-[var(--vx-muted)]">Review age detail →</div></a><a href="{{ $this->ageUrl() }}" class="rounded-[10px] border border-[var(--vx-divider)] p-4"><div class="text-xs font-semibold text-[var(--vx-muted)]">Needs attention</div><div class="vx-kpi-value mt-2">{{ number_format($s['low']+$s['out']) }}</div><div class="mt-2 text-xs text-[var(--vx-muted)]">Review aging stock →</div></a></div>
  </x-filament::section>
 </div>
 <x-filament::section heading="Recent Inventory Activity" description="Latest inventory changes, receipts, and movements.">
  <div class="overflow-x-auto"><table class="w-full"><thead><tr class="h-10 bg-[#fafafc] text-left text-xs font-semibold text-[var(--vx-muted)]"><th class="px-4">Item</th><th class="px-4">Location</th><th class="px-4 text-right">Units</th><th class="px-4">Received</th></tr></thead><tbody>@forelse($this->recentRestocks as $move)<tr class="h-14 border-t border-[var(--vx-divider)]"><td class="px-4"><div class="font-medium">{{ $move->item?->name ?? 'Inventory item' }}</div><div class="text-xs text-[var(--vx-muted)]">{{ $move->item?->sku ?? 'No SKU' }}</div></td><td class="px-4 text-sm">{{ $move->toLocation?->name ?? '—' }}</td><td class="vx-mono px-4 text-right">{{ $move->changeLabel() }}</td><td class="px-4 text-sm text-[var(--vx-muted)]">{{ $move->created_at?->format('M j, g:i A') }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-sm text-[var(--vx-muted)]">No recent receiving activity.</td></tr>@endforelse</tbody></table></div>
 </x-filament::section>
</div>
</x-filament-panels::page>.number_format($s['value'],2),'heroicon-o-circle-stack'],['Total items',number_format($s['total']),'heroicon-o-cube'],['In stock',number_format($s['in']),'heroicon-o-check-circle'],['Low stock',number_format($s['low']),'heroicon-o-exclamation-triangle'],['Out of stock',number_format($s['out']),'heroicon-o-x-circle']] as [$label,$value,$icon])
  <x-filament::section><div class="flex items-start justify-between"><div><div class="text-sm text-[var(--vx-muted)]">{{ $label }}</div><div class="vx-kpi-value mt-2">{{ $value }}</div></div><x-filament::icon :icon="$icon" class="h-5 w-5 text-[var(--vx-faint)]"/></div></x-filament::section>
  @endforeach
 </div>
 <div class="grid gap-6 xl:grid-cols-2">
  <x-filament::section heading="Value by inventory pool" description="Current catalog health as a share of visible inventory.">
   <div class="space-y-5">@foreach([['In stock',$s['in'],$s['percentages']['in']],['Low stock',$s['low'],$s['percentages']['low']],['Out of stock',$s['out'],$s['percentages']['out']]] as [$label,$count,$pct])<div><div class="mb-2 flex justify-between gap-4 text-sm"><span class="font-medium">{{ $label }}</span><span class="vx-mono">{{ number_format($count) }} items</span></div><div class="vx-progress"><span style="width:{{ $pct }}%"></span></div></div>@endforeach</div>
  </x-filament::section>
  <x-filament::section heading="Inventory age buckets" description="Age reporting stays linked to the existing inventory age workflow.">
   <div class="grid grid-cols-2 gap-4"><a href="{{ $this->ageUrl() }}" class="rounded-[10px] border border-[var(--vx-divider)] p-4"><div class="text-xs font-semibold text-[var(--vx-muted)]">Fresh inventory</div><div class="vx-kpi-value mt-2">{{ number_format($s['in']) }}</div><div class="mt-2 text-xs text-[var(--vx-muted)]">Review age detail →</div></a><a href="{{ $this->ageUrl() }}" class="rounded-[10px] border border-[var(--vx-divider)] p-4"><div class="text-xs font-semibold text-[var(--vx-muted)]">Needs attention</div><div class="vx-kpi-value mt-2">{{ number_format($s['low']+$s['out']) }}</div><div class="mt-2 text-xs text-[var(--vx-muted)]">Review aging stock →</div></a></div>
  </x-filament::section>
 </div>
 <x-filament::section heading="Recent receiving" description="Latest receiving and return activity across inventory.">
  <div class="overflow-x-auto"><table class="w-full"><thead><tr class="h-10 bg-[#fafafc] text-left text-xs font-semibold text-[var(--vx-muted)]"><th class="px-4">Item</th><th class="px-4">Location</th><th class="px-4 text-right">Units</th><th class="px-4">Received</th></tr></thead><tbody>@forelse($this->recentRestocks as $move)<tr class="h-14 border-t border-[var(--vx-divider)]"><td class="px-4"><div class="font-medium">{{ $move->item?->name ?? 'Inventory item' }}</div><div class="text-xs text-[var(--vx-muted)]">{{ $move->item?->sku ?? 'No SKU' }}</div></td><td class="px-4 text-sm">{{ $move->toLocation?->name ?? '—' }}</td><td class="vx-mono px-4 text-right">{{ $move->changeLabel() }}</td><td class="px-4 text-sm text-[var(--vx-muted)]">{{ $move->created_at?->format('M j, g:i A') }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center text-sm text-[var(--vx-muted)]">No recent receiving activity.</td></tr>@endforelse</tbody></table></div>
 </x-filament::section>
</div>
</x-filament-panels::page>
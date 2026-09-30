<x-filament-panels::page>
@php $s=$this->inventorySnapshot; @endphp
<div class="space-y-6">
 <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_260px]">
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
   <a href="{{ $this->receiveUrl() }}" class="rounded-xl border border-primary-500 bg-primary-600 p-4 text-white shadow-sm"><div class="flex items-center gap-3"><x-heroicon-o-inbox-arrow-down class="h-6 w-6"/><div><div class="font-bold">Receive Inventory</div><div class="text-xs opacity-80">Pallets & receiving</div></div></div></a>
   <a href="{{ $this->quickAddUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-plus-circle class="h-6 w-6 text-primary-600"/><div><div class="font-bold">Quick Add Stock</div><div class="text-xs text-[var(--vx-muted)]">Add quantity fast</div></div></div></a>
   <a href="{{ $this->scanUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-qr-code class="h-6 w-6 text-primary-600"/><div><div class="font-bold">Scan Inventory</div><div class="text-xs text-[var(--vx-muted)]">Barcode workflow</div></div></div></a>
   <a href="{{ $this->addItemUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-cube class="h-6 w-6 text-primary-600"/><div><div class="font-bold">Add Item</div><div class="text-xs text-[var(--vx-muted)]">Create new SKU</div></div></div></a>
   <a href="{{ $this->transferUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-arrows-right-left class="h-6 w-6 text-primary-600"/><div><div class="font-bold">Transfer Stock</div><div class="text-xs text-[var(--vx-muted)]">Move between locations</div></div></div></a>
   <a href="{{ $this->countUrl() }}" class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="flex items-center gap-3"><x-heroicon-o-clipboard-document-check class="h-6 w-6 text-primary-600"/><div><div class="font-bold">Inventory Count</div><div class="text-xs text-[var(--vx-muted)]">Count & reconcile</div></div></div></a>
  </div>
  <div class="rounded-xl border border-[var(--vx-divider)] bg-[var(--vx-surface)] p-4"><div class="font-bold">Inventory tools</div><div class="mt-3 grid gap-2 text-sm"><a href="{{ $this->reportUrl() }}" class="flex justify-between rounded-lg border border-[var(--vx-divider)] p-2.5"><span>Inventory Report</span><span>→</span></a><a href="{{ $this->movementsUrl() }}" class="flex justify-between rounded-lg border border-[var(--vx-divider)] p-2.5"><span>Activity History</span><span>→</span></a><a href="{{ $this->locationsUrl() }}" class="flex justify-between rounded-lg border border-[var(--vx-divider)] p-2.5"><span>Locations</span><span>→</span></a><a href="{{ $this->vendorsUrl() }}" class="flex justify-between rounded-lg border border-[var(--vx-divider)] p-2.5"><span>Vendors</span><span>→</span></a></div></div>
 </div>
 <x-filament::section>
  <div class="flex flex-wrap items-center justify-between gap-3">
   <div><div class="text-sm font-bold text-gray-950 dark:text-white">Quick actions</div><div class="mt-1 text-xs text-[var(--vx-muted)]">Receive, add, scan, move, or count inventory without hunting through menus.</div></div>
   <div class="flex flex-wrap gap-2">
    <x-filament::button tag="a" :href="$this->receiveUrl()" icon="heroicon-o-inbox-arrow-down">Receive Inventory</x-filament::button>
    <x-filament::button tag="a" :href="$this->quickAddUrl()" icon="heroicon-o-plus-circle" color="gray">Quick Add Stock</x-filament::button>
    <x-filament::button tag="a" :href="$this->scanUrl()" icon="heroicon-o-qr-code" color="gray">Scan Inventory</x-filament::button>
    <x-filament::button tag="a" :href="$this->addItemUrl()" icon="heroicon-o-cube" color="gray">Add Item</x-filament::button>
    <x-filament::button tag="a" :href="$this->transferUrl()" icon="heroicon-o-arrows-right-left" color="gray">Transfer Stock</x-filament::button>
    <x-filament::button tag="a" :href="$this->countUrl()" icon="heroicon-o-clipboard-document-check" color="gray">Inventory Count</x-filament::button>
    <x-filament::dropdown placement="bottom-end">
     <x-slot name="trigger"><x-filament::button icon="heroicon-o-ellipsis-horizontal" color="gray">More</x-filament::button></x-slot>
     <x-filament::dropdown.list>
      <x-filament::dropdown.list.item tag="a" :href="$this->importUrl()" icon="heroicon-o-arrow-up-tray">Import Inventory</x-filament::dropdown.list.item>
      <x-filament::dropdown.list.item tag="a" :href="$this->locationsUrl()" icon="heroicon-o-map-pin">Locations</x-filament::dropdown.list.item>
      <x-filament::dropdown.list.item tag="a" :href="$this->vendorsUrl()" icon="heroicon-o-building-storefront">Vendors</x-filament::dropdown.list.item>
      <x-filament::dropdown.list.item tag="a" :href="$this->movementsUrl()" icon="heroicon-o-clock">Movement History</x-filament::dropdown.list.item>
      <x-filament::dropdown.list.item tag="a" :href="$this->reconciliationUrl()" icon="heroicon-o-scale">Reconciliation</x-filament::dropdown.list.item>
      <x-filament::dropdown.list.item tag="a" :href="$this->reportUrl()" icon="heroicon-o-chart-bar-square">Inventory Report</x-filament::dropdown.list.item>
     </x-filament::dropdown.list>
    </x-filament::dropdown>
   </div>
  </div>
 </x-filament::section>
 <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
  @foreach([['Inventory value','$'.number_format($s['value'],2),'heroicon-o-banknotes'],['Total items',number_format($s['total']),'heroicon-o-cube'],['Low stock',number_format($s['low']),'heroicon-o-exclamation-triangle'],['Out of stock',number_format($s['out']),'heroicon-o-x-circle']] as [$label,$value,$icon])
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
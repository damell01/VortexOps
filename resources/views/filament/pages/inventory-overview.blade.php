<x-filament-panels::page>
@php $s=$this->inventorySnapshot; @endphp
<div class="space-y-6">
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
<x-filament-panels::page>
<div class="space-y-6">
 <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">@foreach($this->getStats() as $stat)<x-filament::section><div class="flex items-start justify-between gap-3"><div><div class="text-sm text-[var(--vx-muted)]">{{ $stat['label'] }}</div><div class="vx-kpi-value mt-2">{{ $stat['value'] }}</div><div class="mt-1 text-xs text-[var(--vx-faint)]">{{ $stat['sub'] }}</div></div><x-filament::icon :icon="$stat['icon']" class="h-5 w-5 text-[var(--vx-faint)]"/></div></x-filament::section>@endforeach</div>
 <div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
  <x-filament::section heading="Report inbox" description="Select a submitted show report to review."><div class="text-sm text-[var(--vx-muted)]">Search, filters, status tabs, pagination and all existing report actions remain in the live queue.</div></x-filament::section>
  <div class="vx-card overflow-hidden">{{ $this->table }}</div>
 </div>
</div>
</x-filament-panels::page>
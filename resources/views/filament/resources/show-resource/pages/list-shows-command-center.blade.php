<x-filament-panels::page>
@php
$reviewQueue=$this->activeTab === 'flagged' && auth()->user()?->isAdmin();
$stats=$reviewQueue ? [] : $this->getStats();
$statusClass=fn(string $label)=>match(true){str_contains(strtolower($label),'closed')=>'vx-status vx-status--closed',str_contains(strtolower($label),'cancel')=>'vx-status vx-status--cancelled',str_contains(strtolower($label),'approval')=>'vx-status vx-status--approval',str_contains(strtolower($label),'review')=>'vx-status vx-status--review',str_contains(strtolower($label),'reconcil')=>'vx-status vx-status--reconciled',default=>'vx-status vx-status--draft'};
@endphp
<div class="space-y-6">
 @if($reviewQueue)
 <x-filament::section>
  <h2 class="text-lg font-semibold">Channel Review</h2>
  <p class="mt-1 text-sm text-[var(--vx-muted)]">Shows below need their channel confirmed. Open a row's actions and select Confirm Channel. An empty list means no shows currently need review.</p>
  <a class="mt-3 inline-block text-sm underline" href="{{ \App\Filament\Resources\ShowResource::getUrl('index', ['tab' => 'all']) }}">Back to all shows</a>
 </x-filament::section>
 @else
 <x-kpi-row :stats="array_slice($stats, 0, 4)" />
 @endif
 <x-quick-tabs />
 <div class="vx-card overflow-hidden">
  <div>{{ $this->table }}</div>
 </div>
</div>
</x-filament-panels::page>
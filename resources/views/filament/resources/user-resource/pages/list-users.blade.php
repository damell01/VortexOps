<x-filament-panels::page>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,.65fr)]">
 <div class="vx-card overflow-hidden">{{ $this->table }}</div>
 <x-filament::section heading="Role permissions" description="Current permission coverage by role. Edit roles through the existing access controls.">
  <div class="space-y-3">@foreach($this->roleMatrix() as $role)<div class="rounded-[10px] border border-[var(--vx-divider)] p-4"><div class="flex items-center justify-between gap-3"><div class="font-semibold">{{ $role['name'] }}</div><span class="vx-status vx-status--gray">{{ $role['count'] }} permissions</span></div><div class="mt-3 flex flex-wrap gap-2">@forelse($role['permissions'] as $permission)<span class="rounded-full bg-[var(--vx-gray-bg)] px-2 py-1 text-xs text-[var(--vx-gray)]">{{ str($permission)->replace('_',' ')->title() }}</span>@empty<span class="text-xs text-[var(--vx-muted)]">No direct permissions.</span>@endforelse</div></div>@endforeach</div>
 </x-filament::section>
</div>
</x-filament-panels::page>
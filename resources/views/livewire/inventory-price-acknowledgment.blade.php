<div>
@if (count($changes) || count($comingSoon) || count($arrivals))
<div role="dialog" aria-modal="true" aria-labelledby="inventory-update-title" x-data x-trap.inert.noscroll="true" x-on:keydown.escape.window.prevent.stop class="fixed inset-0 z-[9999] grid place-items-center bg-black/80 p-3 sm:p-6">
  <section class="flex w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-gray-700 bg-gray-950 text-white shadow-2xl" style="max-height:92dvh">
    <header class="shrink-0 border-b border-gray-800 p-4 sm:p-6">
      <div class="flex items-center gap-3"><div class="rounded-xl bg-amber-500/15 p-3 text-amber-400"><x-heroicon-o-bell-alert class="h-6 w-6"/></div>
        <div><h2 id="inventory-update-title" class="text-xl font-bold">Inventory updates</h2><p class="text-sm text-gray-400">Review changes before selling.</p></div></div>
      <div class="mt-4 grid grid-cols-3 gap-2">
        @foreach ([['Price changes',count($changes)],['Stock updates',count($arrivals)],['Coming soon',count($comingSoon)]] as $summary)
        <div class="rounded-lg bg-gray-900 px-3 py-2"><div class="text-xl font-bold">{{ $summary[1] }}</div><div class="text-xs text-gray-400">{{ $summary[0] }}</div></div>
        @endforeach
      </div>
    </header>
    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4 sm:p-6">
      @if (count($changes))
      <section aria-label="Price changes"><h3 class="mb-2 font-semibold">Price changes</h3><div class="space-y-2">
      @foreach ($changes as $change)
      <article wire:key="price-{{ $change['id'] }}" class="flex items-center gap-3 rounded-xl border p-3 {{ $change['decreased'] ? 'border-red-600/60 bg-red-950/20' : 'border-gray-700 bg-gray-900' }}">
        @if ($change['image'] ?? null)<img src="{{ $change['image'] }}" alt="" loading="lazy" class="h-14 w-14 shrink-0 rounded-lg bg-white object-contain">@else<div class="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-gray-800 text-xs text-gray-400">No photo</div>@endif
        <div class="min-w-0 flex-1"><div class="truncate font-semibold">{{ $change['name'] }}</div><div class="text-sm text-gray-300">{{ $change['old_price'] === null ? 'Unset' : '$'.number_format((float)$change['old_price'],2) }} → <strong>{{ $change['new_price'] === null ? 'Unset' : '$'.number_format((float)$change['new_price'],2) }}</strong></div></div>
        @if ($change['decreased'])<span class="rounded-full bg-red-900 px-2 py-1 text-xs font-bold text-red-200">Decreased</span>@endif
      </article>
      @endforeach</div></section>@endif
      @if (count($arrivals))
      <section aria-label="Stock changes"><h3 class="mb-2 font-semibold">Stock changes</h3><div class="space-y-2">
      @foreach ($arrivals as $item)
      <article wire:key="stock-{{ $item['id'] }}" class="flex items-center gap-3 rounded-xl border border-gray-700 bg-gray-900 p-3">
        @if ($item['image'])<img src="{{ $item['image'] }}" alt="" loading="lazy" class="h-14 w-14 shrink-0 rounded-lg bg-white object-contain">@else<div class="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-gray-800 text-xs text-gray-400">No photo</div>@endif
        <div class="min-w-0 flex-1"><div class="truncate font-semibold">{{ $item['name'] }}</div><div class="text-sm text-gray-400">{{ $item['kind'] === 'sold_out' ? 'Stock depleted' : 'Quantity after change: '.$item['quantity'] }}</div></div>
        <span class="rounded-full px-2 py-1 text-xs font-bold {{ $item['kind'] === 'sold_out' ? 'bg-red-900 text-red-200' : 'bg-emerald-900 text-emerald-200' }}">{{ match($item['kind']) {'sold_out' => 'Sold out','restocked' => 'Restocked','stock_added' => 'Stock added','new_item' => 'New item',default => 'New stock'} }}</span>
      </article>
      @endforeach</div></section>@endif
      @if (count($comingSoon))
      <section aria-label="Coming soon"><h3 class="mb-2 font-semibold">Coming soon · awaiting pallet receipt</h3><div class="space-y-2">
      @foreach ($comingSoon as $item)
      <article wire:key="pallet-{{ $item['id'] }}" class="flex items-center gap-3 rounded-xl border border-gray-700 bg-gray-900 p-3">
        @if ($item['image'])<img src="{{ $item['image'] }}" alt="" loading="lazy" class="h-14 w-14 shrink-0 rounded-lg bg-white object-contain">@else<div class="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-gray-800 text-xs text-gray-400">No photo</div>@endif
        <div class="min-w-0 flex-1"><div class="truncate font-semibold">{{ $item['name'] }}</div><div class="text-sm text-gray-400">{{ $item['pallet'] }} · {{ $item['cases'] }} cases @if ($item['expected']) · Expected {{ $item['expected'] }} @endif</div></div>
        <span class="rounded-full bg-blue-900 px-2 py-1 text-xs font-bold text-blue-200">Coming soon</span>
      </article>
      @endforeach</div></section>@endif
    </div>
    <footer class="shrink-0 border-t border-gray-800 bg-gray-950 p-4 sm:p-5">
      <button type="button" wire:click="acknowledge" wire:loading.attr="disabled" wire:target="acknowledge" class="w-full rounded-xl bg-amber-400 px-4 py-3 font-bold text-gray-950 hover:bg-amber-300 disabled:opacity-50">Acknowledge &amp; continue</button>
    </footer>
  </section>
</div>
@endif
</div>

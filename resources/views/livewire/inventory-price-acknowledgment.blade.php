<div>
<style>
.vx-inventory-updates-scroll { scrollbar-width: thin; scrollbar-color: #7041a4 #171127; }
.vx-inventory-updates-scroll::-webkit-scrollbar { width: 8px; }
.vx-inventory-updates-scroll::-webkit-scrollbar-track { background: #171127; }
.vx-inventory-updates-scroll::-webkit-scrollbar-thumb { background: #7041a4; border: 2px solid #171127; border-radius: 999px; }
</style>
@if (count($changes) || count($comingSoon) || count($arrivals))
<div role="dialog" aria-modal="true" aria-labelledby="inventory-update-title" x-data="{ tab: 'all', slide: 0, touchX: null, categories: @js($mobileCategories), visibleIndices() { return this.categories.map((c,i) => (this.tab === 'all' || c === this.tab || (this.tab === 'stock' && ['stock','out','back'].includes(c))) ? i : -1).filter(i => i >= 0) }, activeIndex() { return this.visibleIndices()[this.slide] ?? -1 }, go(n) { const count = this.visibleIndices().length; this.slide = count ? (n + count) % count : 0 }, choose(key) { this.tab = key; this.slide = 0 } }" x-trap.inert.noscroll="true" x-on:keydown.escape.window.prevent.stop class="fixed inset-0 z-[9999] flex items-center justify-center overflow-hidden bg-black/80 p-2 sm:p-4">
  <section class="vx-inventory-modal flex min-h-0 w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-purple-500 bg-[#0d0b1d] text-white shadow-2xl" style="height:min(740px,calc(100dvh - 32px));max-height:calc(100dvh - 32px);box-shadow:0 0 0 1px rgba(168,85,247,.35),0 0 35px rgba(124,58,237,.3)">
    <header class="shrink-0 border-b border-purple-900/60 bg-gradient-to-r from-[#17102f] via-[#241044] to-[#10182e] p-3 sm:p-4">
      <div class="flex items-center gap-3"><div class="rounded-xl bg-purple-500/20 p-3 text-purple-300"><x-heroicon-o-bell-alert class="h-6 w-6"/></div>
        <div><h2 id="inventory-update-title" class="text-2xl font-extrabold tracking-tight text-purple-100 sm:text-3xl">Inventory Updates</h2><p class="text-sm text-gray-400">Review the latest pricing and stock changes before selling.</p></div></div>
      @php
        $mobileItems = collect();
        foreach ($changes as $change) $mobileItems->push(['category'=>'price','name'=>$change['name'],'image'=>$change['image'] ?? null,'old'=>$change['old_price'],'new'=>$change['new_price'],'decreased'=>$change['decreased']]);
        foreach ($arrivals as $item) $mobileItems->push(['category'=>$item['kind'] === 'sold_out' ? 'out' : ($item['kind'] === 'restocked' ? 'back' : 'stock'),'name'=>$item['name'],'image'=>$item['image'] ?? null,'old'=>$item['previous_quantity'] ?? 0,'new'=>$item['quantity']]);
        foreach ($comingSoon as $item) $mobileItems->push(['category'=>'soon','name'=>$item['name'],'image'=>$item['image'] ?? null,'pallet'=>$item['pallet'],'cases'=>$item['cases'],'expected'=>$item['expected']]);
        $mobileCategories = $mobileItems->pluck('category')->all();
        $outCount = collect($arrivals)->where('kind','sold_out')->count();
        $backCount = collect($arrivals)->where('kind','restocked')->count();
      @endphp
      <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach ([['Price Changes',count($changes),'border-purple-500/50','text-purple-300'],['Stock Updates',count($arrivals),'border-sky-500/50','text-sky-300'],['Back in Stock',$backCount,'border-emerald-500/50','text-emerald-300'],['Out of Stock',$outCount,'border-rose-500/50','text-rose-300']] as [$label,$count,$borderClass,$textClass])
          <div class="rounded-xl border {{ $borderClass }} bg-black/30 p-2 shadow-inner">
            <div class="text-2xl font-black">{{ $count }}</div><div class="text-xs font-semibold {{ $textClass }} sm:text-sm">{{ $label }}</div>
          </div>
        @endforeach
      </div>
      <div class="mt-3 grid grid-cols-2 gap-1.5 sm:grid-cols-3 lg:grid-cols-6" role="tablist" aria-label="Inventory update filters">
        @foreach ([['all','All Updates',count($changes)+count($arrivals)+count($comingSoon)],['price','Price Changes',count($changes)],['stock','Stock Updates',count($arrivals)],['out','Out of Stock',$outCount],['back','Back in Stock',$backCount],['soon','Coming Soon',count($comingSoon)]] as [$key,$label,$count])
          <button type="button" role="tab" :aria-selected="tab === '{{ $key }}'" @click="choose('{{ $key }}')" :class="tab === '{{ $key }}' ? 'bg-purple-700 border-purple-400 text-white shadow-lg shadow-purple-800/40' : 'bg-black/30 border-gray-700 text-gray-300 hover:border-purple-600'" class="min-w-0 rounded-lg border px-2 py-2 text-center text-xs font-semibold leading-tight">{{ $label }} ({{ $count }})</button>
        @endforeach
      </div>
    </header>
    <div class="vx-inventory-updates-scroll min-h-0 flex-1 space-y-3 overflow-x-hidden overflow-y-auto overscroll-contain bg-[#0b0b19] p-3 sm:p-4">
      @if (count($changes))
      <section x-show="tab === 'all' || tab === 'price'" aria-label="Price changes"><h3 class="mb-2 font-semibold">Price changes</h3><div class="space-y-2">
      @foreach ($changes as $change)
      <article wire:key="price-{{ $change['id'] }}" class="flex items-center gap-3 rounded-xl border p-2.5 transition-colors {{ $change['decreased'] ? 'border-red-600/60 bg-red-950/20' : 'border-purple-900/60 bg-[#181426]' }}">
        @if ($change['image'] ?? null)<img src="{{ $change['image'] }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-lg bg-white object-contain">@else<div class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-gray-800 text-[10px] text-gray-400">No photo</div>@endif
        <div class="min-w-0 flex-1">
          <div class="truncate font-semibold" title="{{ $change['name'] }}">{{ $change['name'] }}</div>
          <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
            <span class="text-gray-400 line-through decoration-gray-500" aria-label="Old price">{{ $change['old_price'] === null ? 'Not set' : '$'.number_format((float)$change['old_price'], 2) }}</span>
            <x-heroicon-o-arrow-right class="h-4 w-4 shrink-0 text-gray-500" aria-hidden="true" />
            <span class="rounded-md border border-emerald-500/40 bg-emerald-500/15 px-2.5 py-1 text-base font-bold text-emerald-300" aria-label="New price">{{ $change['new_price'] === null ? 'Not set' : '$'.number_format((float)$change['new_price'], 2) }}</span>
          </div>
        </div>
        <span class="shrink-0 rounded-full px-2 py-1 text-xs font-bold {{ $change['decreased'] ? 'bg-red-900 text-red-200' : 'bg-emerald-900 text-emerald-200' }}">{{ $change['decreased'] ? 'Decreased' : 'Increased' }}</span>
      </article>
      @endforeach</div></section>@endif
      @if (count($arrivals))
      <section x-show="tab === 'all' || tab === 'stock' || tab === 'out' || tab === 'back'" aria-label="Stock changes"><h3 class="mb-2 font-semibold">Stock changes</h3><div class="space-y-2">
      @foreach ($arrivals as $item)
      <article x-show="tab !== 'out' && tab !== 'back' || (tab === 'out' && @js($item['kind'] === 'sold_out')) || (tab === 'back' && @js($item['kind'] === 'restocked'))" wire:key="stock-{{ $item['id'] }}" class="flex items-center gap-3 rounded-xl border border-gray-700 bg-gray-900 p-2.5">
        @if ($item['image'])<img src="{{ $item['image'] }}" alt="" loading="lazy" class="h-14 w-14 shrink-0 rounded-lg bg-white object-contain">@else<div class="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-gray-800 text-xs text-gray-400">No photo</div>@endif
        <div class="min-w-0 flex-1"><div class="truncate font-semibold">{{ $item['name'] }}</div><div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
          <span class="text-gray-400 line-through">{{ number_format((float) ($item['previous_quantity'] ?? 0)) }} units</span>
          <span class="text-gray-500" aria-hidden="true">→</span>
          <span class="rounded-md border px-2.5 py-1 font-bold {{ $item['kind'] === 'sold_out' ? 'border-red-500/40 bg-red-500/15 text-red-300' : 'border-emerald-500/40 bg-emerald-500/15 text-emerald-300' }}">{{ number_format((float) $item['quantity']) }} units</span>
        </div></div>
        <span class="rounded-full px-2 py-1 text-xs font-bold {{ $item['kind'] === 'sold_out' ? 'bg-red-900 text-red-200' : 'bg-emerald-900 text-emerald-200' }}">{{ match($item['kind']) {'sold_out' => 'Sold out','restocked' => 'Back in stock','stock_added' => 'Stock added','new', 'new_item' => 'New item',default => 'New stock'} }}</span>
      </article>
      @endforeach</div></section>@endif
      @if (count($comingSoon))
      <section x-show="tab === 'all' || tab === 'soon'" aria-label="Coming soon"><h3 class="mb-2 font-semibold">Coming soon · awaiting pallet receipt</h3><div class="space-y-2">
      @foreach ($comingSoon as $item)
      <article wire:key="pallet-{{ $item['id'] }}" class="flex items-center gap-3 rounded-xl border border-gray-700 bg-gray-900 p-3">
        @if ($item['image'])<img src="{{ $item['image'] }}" alt="" loading="lazy" class="h-14 w-14 shrink-0 rounded-lg bg-white object-contain">@else<div class="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-gray-800 text-xs text-gray-400">No photo</div>@endif
        <div class="min-w-0 flex-1"><div class="truncate font-semibold">{{ $item['name'] }}</div><div class="text-sm text-gray-400">{{ $item['pallet'] }} · {{ $item['cases'] }} cases @if ($item['expected']) · Expected {{ $item['expected'] }} @endif</div></div>
        <span class="rounded-full bg-blue-900 px-2 py-1 text-xs font-bold text-blue-200">Coming soon</span>
      </article>
      @endforeach</div></section>@endif
    </div>
    <footer class="shrink-0 border-t border-purple-900/70 bg-[#100d23] p-3 sm:p-4">
      <button type="button" wire:click="acknowledge" wire:loading.attr="disabled" wire:target="acknowledge" class="w-full rounded-xl bg-gradient-to-r from-yellow-300 to-amber-400 px-4 py-3 text-lg font-extrabold text-gray-950 shadow-lg shadow-amber-500/20 hover:from-yellow-200 hover:to-amber-300 disabled:opacity-50">Acknowledge &amp; continue</button>
    </footer>
  </section>
</div>
@endif
</div>

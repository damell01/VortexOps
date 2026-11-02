<div>
@if(count($changes) || count($arrivals) || count($comingSoon))
<div role="dialog" aria-modal="true" aria-labelledby="inventory-updates-heading" x-data="{tab:'all', slide:0, startX:null, visible(){return Array.from(this.$refs.items.children).filter(el=>this.tab==='all'||el.dataset.kind===this.tab)}, go(delta){let n=this.visible().length; if(n)this.slide=(this.slide+delta+n)%n}, choose(value){this.tab=value;this.slide=0}}" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-2 sm:p-4">
<section class="flex w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-purple-600 bg-[#100d23] text-white" style="max-height:calc(100dvh - 24px);height:min(700px,calc(100dvh - 24px))">
<header class="shrink-0 border-b border-purple-900 p-3">
<h2 id="inventory-updates-heading" class="text-xl font-bold text-purple-200">Inventory Updates</h2>
<p class="text-xs text-gray-400">Review changes before selling.</p>
<div class="mt-3 flex flex-wrap gap-2">
@foreach(['all'=>'All','price'=>'Prices','stock'=>'Stock','soon'=>'Coming soon'] as $key=>$label)
<button type="button" @click="choose('{{ $key }}')" :class="tab==='{{ $key }}'?'bg-purple-700':'bg-gray-800'" class="rounded-lg px-3 py-2 text-xs font-semibold">{{ $label }}</button>
@endforeach
</div>
</header>
<div class="min-h-0 flex-1 overflow-y-auto p-3" x-on:touchstart.passive="startX=$event.changedTouches[0].screenX" x-on:touchend.passive="if(startX!==null){let d=$event.changedTouches[0].screenX-startX;if(Math.abs(d)>55)go(d<0?1:-1);startX=null}">
<div x-ref="items" class="space-y-2">
@foreach($changes as $item)
<article data-kind="price" x-show="tab==='all'||tab==='price'" class="rounded-xl border border-purple-800 bg-[#211631] p-3" :class="{'sm:hidden':false}">
<div class="font-semibold">{{ $item['name'] }}</div>
<div class="mt-1 text-sm"><span class="text-gray-400">{{ $item['old_price']===null?'Not set':'$'.number_format((float)$item['old_price'],2) }}</span> → <span class="font-bold text-green-300">{{ $item['new_price']===null?'Not set':'$'.number_format((float)$item['new_price'],2) }}</span></div>
</article>
@endforeach
@foreach($arrivals as $item)
<article data-kind="stock" x-show="tab==='all'||tab==='stock'" class="rounded-xl border border-purple-800 bg-[#211631] p-3">
<div class="font-semibold">{{ $item['name'] }}</div><div class="text-sm text-green-300">{{ number_format((float)($item['previous_quantity']??0)) }} → {{ number_format((float)$item['quantity']) }} units</div>
</article>
@endforeach
@foreach($comingSoon as $item)
<article data-kind="soon" x-show="tab==='all'||tab==='soon'" class="rounded-xl border border-purple-800 bg-[#211631] p-3"><div class="font-semibold">{{ $item['name'] }}</div><div class="text-sm text-gray-400">{{ $item['pallet'] }} · {{ $item['cases'] }} cases</div></article>
@endforeach
</div>
</div>
<footer class="shrink-0 border-t border-purple-900 p-3"><button type="button" wire:click="acknowledge" wire:loading.attr="disabled" class="w-full rounded-xl bg-amber-300 p-3 font-bold text-gray-950">Acknowledge &amp; continue</button></footer>
</section>
</div>
@endif
</div>
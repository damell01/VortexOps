<div>
@if(count($changes) || count($arrivals) || count($comingSoon))
@php
$slides = collect();
foreach ($changes as $item) $slides->push(['type'=>'price','name'=>$item['name'],'image'=>$item['image'] ?? null,'old'=>$item['old_price'],'new'=>$item['new_price'],'decreased'=>$item['decreased'] ?? false]);
foreach ($arrivals as $item) $slides->push(['type'=>'stock','name'=>$item['name'],'image'=>$item['image'] ?? null,'old'=>$item['previous_quantity'] ?? 0,'new'=>$item['quantity'],'kind'=>$item['kind']]);
foreach ($comingSoon as $item) $slides->push(['type'=>'soon','name'=>$item['name'],'image'=>$item['image'] ?? null,'pallet'=>$item['pallet'],'cases'=>$item['cases']]);
$slideTypes = $slides->pluck('type')->all();
@endphp
<div role="dialog" aria-modal="true" aria-labelledby="inventory-updates-heading" x-data="{tab:'all',slide:0,startX:null,types:@js($slideTypes),visible(){return this.types.map((t,i)=>this.tab==='all'||t===this.tab?i:-1).filter(i=>i>=0)},active(){return this.visible()[this.slide]??-1},go(d){const n=this.visible().length;this.slide=n?(this.slide+d+n)%n:0},choose(t){this.tab=t;this.slide=0}}" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-2 sm:p-4">
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
<div class="flex min-h-0 flex-1 flex-col p-3 sm:hidden">
<div class="min-h-0 flex-1 overflow-y-auto overscroll-contain" x-on:touchstart.passive="startX=$event.changedTouches[0].screenX" x-on:touchend.passive="if(startX!==null){const d=$event.changedTouches[0].screenX-startX;if(Math.abs(d)>55)go(d<0?1:-1);startX=null}">
@foreach($slides as $i=>$item)
<article x-cloak x-show="active()==={{ $i }}" class="rounded-xl border border-purple-700 bg-[#211631] p-4">
<div class="mb-3 flex justify-between gap-2"><span class="text-xs font-bold uppercase text-purple-300">{{ $item['type']==='price'?'Price change':($item['type']==='soon'?'Coming soon':'Stock update') }}</span><span class="text-xs text-gray-400" x-text="(slide+1)+' / '+visible().length"></span></div>
@if($item['image'])<img src="{{ $item['image'] }}" alt="" loading="lazy" class="mx-auto mb-3 h-28 w-28 rounded-lg bg-white object-contain">@else<div class="mx-auto mb-3 grid h-24 w-24 place-items-center rounded-lg bg-gray-800 text-xs text-gray-400">No photo</div>@endif
<h3 class="mb-3 text-center text-lg font-bold">{{ $item['name'] }}</h3>
@if($item['type']==='price')
<div class="text-center"><span class="text-gray-400 line-through">{{ $item['old']===null?'Not set':'
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
</div>.number_format((float)$item['old'],2) }}</span> → <span class="text-xl font-bold {{ $item['decreased']?'text-green-300':'text-rose-300' }}">{{ $item['new']===null?'Not set':'
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
</div>.number_format((float)$item['new'],2) }}</span></div>
@elseif($item['type']==='stock')
<div class="text-center text-lg"><span class="text-gray-400">{{ number_format((float)$item['old']) }}</span> → <span class="font-bold {{ $item['kind']==='sold_out'?'text-rose-300':'text-green-300' }}">{{ number_format((float)$item['new']) }} units</span></div>
@else
<div class="text-center text-sm text-sky-300">{{ $item['pallet'] }} · {{ $item['cases'] }} cases</div>
@endif
</article>
@endforeach
<p x-show="visible().length===0" class="py-8 text-center text-gray-400">No updates in this category.</p>
</div>
<div class="mt-3 flex items-center justify-between">
<button type="button" @click="go(-1)" :disabled="visible().length<2" aria-label="Previous update" class="rounded-lg border border-purple-600 px-4 py-2 disabled:opacity-40">←</button>
<span class="text-sm text-purple-200" aria-live="polite"><span x-text="visible().length?slide+1:0"></span> of <span x-text="visible().length"></span></span>
<button type="button" @click="go(1)" :disabled="visible().length<2" aria-label="Next update" class="rounded-lg border border-purple-600 px-4 py-2 disabled:opacity-40">→</button>
</div>
<p class="mt-1 text-center text-xs text-gray-400">Swipe to browse updates</p>
</div>
<div class="hidden min-h-0 flex-1 overflow-y-auto p-3 sm:block">
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
<div>
@if(count($changes) || count($arrivals) || count($comingSoon))
@php
$slides = collect();
foreach ($changes as $entry) { $slides->push(['type'=>'price','item'=>$entry]); }
foreach ($arrivals as $entry) { $slides->push(['type'=>'stock','item'=>$entry]); }
foreach ($comingSoon as $entry) { $slides->push(['type'=>'soon','item'=>$entry]); }
$types = $slides->pluck('type')->all();
@endphp
<div role="dialog" aria-modal="true" aria-labelledby="updates-title" x-data="{tab:'all',index:0,startX:null,types:@js($types),visible(){return this.types.map((t,i)=>this.tab==='all'||t===this.tab?i:-1).filter(i=>i>=0)},active(){return this.visible()[this.index]??-1},go(n){const c=this.visible().length;this.index=c?(this.index+n+c)%c:0},choose(t){this.tab=t;this.index=0}}" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-2 sm:p-4">
<section class="flex w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-purple-600 bg-[#100d23] text-white" style="height:min(700px,calc(100dvh - 24px));max-height:calc(100dvh - 24px)">
<header class="shrink-0 border-b border-purple-900 p-3">
<h2 id="updates-title" class="text-xl font-bold text-purple-200">Inventory Updates</h2>
<p class="text-xs text-gray-400">Review changes before selling.</p>
<div class="mt-3 flex flex-wrap gap-2">
@foreach(['all'=>'All','price'=>'Prices','stock'=>'Stock','soon'=>'Coming soon'] as $key=>$label)
<button type="button" @click="choose('{{ $key }}')" :class="tab==='{{ $key }}'?'bg-purple-700':'bg-gray-800'" class="rounded-lg px-3 py-2 text-xs font-semibold">{{ $label }}</button>
@endforeach
</div>
</header>
<div class="min-h-0 flex-1 overflow-y-auto p-3 sm:hidden" x-on:touchstart.passive="startX=$event.changedTouches[0].screenX" x-on:touchend.passive="if(startX!==null){let d=$event.changedTouches[0].screenX-startX;if(Math.abs(d)>55)go(d<0?1:-1);startX=null}">
@foreach($slides as $i=>$slide)
<article x-cloak x-show="active()==={{ $i }}" class="rounded-xl border border-purple-700 bg-[#211631] p-4">
<div class="mb-3 flex justify-between"><span class="text-xs font-bold uppercase text-purple-300">{{ $slide['type'] }}</span><span class="text-xs text-gray-400" x-text="(index+1)+' / '+visible().length"></span></div>
@if($slide['item']['image'] ?? null)<img src="{{ $slide['item']['image'] }}" alt="" class="mx-auto mb-3 h-28 w-28 rounded-lg bg-white object-contain">@endif
<h3 class="mb-3 text-center text-lg font-bold">{{ $slide['item']['name'] }}</h3>
@if($slide['type']==='price')
<p class="text-center text-lg">{{ $slide['item']['old_price']===null?'Not set':number_format((float)$slide['item']['old_price'],2) }} → <strong class="text-green-300">{{ $slide['item']['new_price']===null?'Not set':number_format((float)$slide['item']['new_price'],2) }}</strong></p>
@elseif($slide['type']==='stock')
<p class="text-center">{{ number_format((float)($slide['item']['previous_quantity']??0)) }} → <strong class="text-green-300">{{ number_format((float)$slide['item']['quantity']) }} units</strong></p>
@else
<p class="text-center text-sky-300">{{ $slide['item']['pallet'] }} · {{ $slide['item']['cases'] }} cases</p>
@endif
</article>
@endforeach
<p x-show="visible().length===0" class="text-center text-gray-400">No updates in this category.</p>
<div class="mt-3 flex items-center justify-between">
<button type="button" @click="go(-1)" :disabled="visible().length<2" class="rounded-lg border border-purple-600 px-4 py-2 disabled:opacity-40" aria-label="Previous">←</button>
<span class="text-sm" x-text="(visible().length?index+1:0)+' of '+visible().length"></span>
<button type="button" @click="go(1)" :disabled="visible().length<2" class="rounded-lg border border-purple-600 px-4 py-2 disabled:opacity-40" aria-label="Next">→</button>
</div>
</div>
<div class="hidden min-h-0 flex-1 space-y-2 overflow-y-auto p-3 sm:block">
@foreach($slides as $slide)
<article x-show="tab==='all'||tab==='{{ $slide['type'] }}'" class="rounded-xl border border-purple-800 bg-[#211631] p-3">
<div class="font-semibold">{{ $slide['item']['name'] }}</div>
@if($slide['type']==='price')
<p class="text-sm text-green-300">{{ $slide['item']['old_price']===null?'Not set':number_format((float)$slide['item']['old_price'],2) }} → {{ $slide['item']['new_price']===null?'Not set':number_format((float)$slide['item']['new_price'],2) }}</p>
@elseif($slide['type']==='stock')
<p class="text-sm text-green-300">{{ number_format((float)($slide['item']['previous_quantity']??0)) }} → {{ number_format((float)$slide['item']['quantity']) }} units</p>
@else
<p class="text-sm text-sky-300">{{ $slide['item']['pallet'] }} · {{ $slide['item']['cases'] }} cases</p>
@endif
</article>
@endforeach
</div>
<footer class="shrink-0 border-t border-purple-900 p-3"><button type="button" wire:click="acknowledge" wire:loading.attr="disabled" wire:target="acknowledge" class="w-full rounded-xl bg-amber-300 p-3 font-bold text-gray-950">Acknowledge &amp; continue</button></footer>
</section>
</div>
@endif
</div>

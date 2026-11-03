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
<section class="flex w-full max-w-[1600px] flex-col overflow-hidden rounded-2xl border border-purple-600 bg-[#100d23] text-white" style="height:min(700px,calc(100dvh - 24px));max-height:calc(100dvh - 24px)">
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
@if($slide['item']['image'] ?? null)<img src="{{ $slide['item']['image'] }}" alt="" class="mx-auto mb-3 h-28 w-28 rounded-lg bg-white object-contain dark:bg-gray-900">@endif
<h3 class="mb-3 text-center text-lg font-bold">{{ $slide['item']['name'] }}</h3>
@if($slide['type']==='price')
<div class="flex items-center justify-center gap-3"><span class="text-gray-400 line-through">{{ $slide['item']['old_price']===null?'Not set':number_format((float)$slide['item']['old_price'],2) }}</span><strong class="rounded-lg px-3 py-2 text-xl {{ ($slide['item']['decreased']??false)?'bg-emerald-500/20 text-emerald-300':'bg-amber-400/20 text-amber-200' }}">{{ $slide['item']['new_price']===null?'Not set':number_format((float)$slide['item']['new_price'],2) }}</strong></div>
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
<div class="hidden min-h-0 flex-1 grid-cols-2 content-start gap-3 overflow-y-auto p-4 sm:grid lg:grid-cols-3 xl:grid-cols-5 2xl:grid-cols-6">
@foreach($slides as $slide)
<article x-show="tab==='all'||tab==='{{ $slide['type'] }}'" class="flex min-w-0 flex-col gap-2 rounded-xl border border-purple-800 bg-[#211631] p-3 shadow-lg">
@if($slide['item']['image'] ?? null)<img src="{{ $slide['item']['image'] }}" alt="" loading="lazy" class="h-28 w-full rounded-lg bg-white/10 object-contain">@else<div class="grid h-28 w-full place-items-center rounded-lg bg-purple-950 text-xs text-purple-300">No image</div>@endif
<div class="min-w-0 flex-1"><span class="mb-1 inline-block rounded bg-purple-800/60 px-2 py-0.5 text-xs uppercase text-purple-200">{{ $slide['type']==='price'?'Price changed':($slide['type']==='stock'?'Stock update':'Coming soon') }}</span><div class="font-semibold leading-snug">{{ $slide['item']['name'] }}</div>
@if($slide['type']==='price')
<div class="mt-2 flex flex-wrap items-center gap-2"><span class="text-sm text-gray-400 line-through">{{ $slide['item']['old_price']===null?'Not set':number_format((float)$slide['item']['old_price'],2) }}</span><span class="rounded-lg px-2 py-1 text-base font-extrabold {{ ($slide['item']['decreased']??false)?'bg-emerald-500/20 text-emerald-300':'bg-amber-400/20 text-amber-200' }}">{{ $slide['item']['new_price']===null?'Not set':number_format((float)$slide['item']['new_price'],2) }}</span></div>
@elseif($slide['type']==='stock')
<p class="text-sm text-green-300">{{ number_format((float)($slide['item']['previous_quantity']??0)) }} → {{ number_format((float)$slide['item']['quantity']) }} units</p>
@else
<p class="text-sm text-sky-300">{{ $slide['item']['pallet'] }} · {{ $slide['item']['cases'] }} cases</p>
@endif
</div></article>
@endforeach
</div>
<footer class="shrink-0 border-t border-purple-900 p-3"><button type="button" wire:click="acknowledge" wire:loading.attr="disabled" wire:target="acknowledge" class="w-full rounded-xl bg-amber-300 p-3 font-bold text-gray-950 dark:text-white">Acknowledge &amp; continue</button></footer>
</section>
</div>
@endif
</div>

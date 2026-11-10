<div>
    <div class="inline-flex items-center">
        <button type="button" wire:click="show" wire:loading.attr="disabled" wire:target="show" class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700">
            <span wire:loading.remove wire:target="show">✦ Recent Inventory Changes</span>
            <span wire:loading wire:target="show" role="status">Opening inventory changes…</span>
        </button>
    </div>
    @if($open)
        @teleport('body')
        <div data-vx-dialog data-inventory-recent-changes class="fixed inset-0 z-[9998] flex items-center justify-center overflow-hidden bg-black/80 px-3 sm:px-6" style="position:fixed;inset:0;z-index:9998;height:100dvh;box-sizing:border-box;padding-top:max(12px,env(safe-area-inset-top));padding-bottom:max(12px,env(safe-area-inset-bottom))" role="dialog" aria-modal="true" aria-labelledby="recent-inventory-title" x-data="{ visible: true, dismiss() { if (!this.visible) return; this.visible = false; this.$wire.close(); } }" x-show="visible" x-init="$nextTick(() => $refs.closeRecentChanges?.focus())" x-trap.inert.noscroll="true" x-on:keydown.escape.window="dismiss()" x-on:click.self="dismiss()">
            <section class="flex min-h-0 w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-purple-500 bg-[#0d0b1d] text-white" style="max-height:100%;box-shadow:0 0 60px rgba(124,58,237,.35)">
                <header class="shrink-0 border-b border-purple-800/60 bg-gradient-to-r from-[#241047] to-[#0e1a32] p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-xs font-bold uppercase tracking-widest text-purple-300">Inventory activity</p><h2 id="recent-inventory-title" class="mt-1 text-2xl font-extrabold">Recent Changes</h2><p class="text-sm text-gray-300">Showing inventory changes for your selected period</p></div>
                        <button type="button" x-on:click="dismiss()" x-ref="closeRecentChanges" aria-label="Close recent changes" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-purple-500/50 font-bold hover:bg-purple-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-purple-300">✕</button>
                    </div>
                </header>
                <div data-inventory-changes-scroll class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                <div class="border-b border-purple-800/60 bg-[#161025] p-4 sm:p-5">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl border border-purple-500/60 bg-black/30 p-3"><div class="text-2xl font-black">{{ $prices->count() }}</div><div class="text-sm text-purple-300">Price changes</div></div>
                        <div class="rounded-xl border border-sky-500/60 bg-black/30 p-3"><div class="text-2xl font-black">{{ $stock->count() }}</div><div class="text-sm text-sky-300">Stock updates</div></div>
                        <div class="rounded-xl border border-green-500/60 bg-black/30 p-3"><div class="text-2xl font-black">{{ $stock->where('kind','restocked')->count() }}</div><div class="text-sm text-green-300">Back in stock</div></div>
                        <div class="rounded-xl border border-rose-500/60 bg-black/30 p-3"><div class="text-2xl font-black">{{ $stock->where('kind','sold_out')->count() }}</div><div class="text-sm text-rose-300">Out of stock</div></div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2"><label for="inventory-changes-range" class="text-sm font-semibold text-purple-200">Period</label><select id="inventory-changes-range" wire:model.live="range" class="rounded-xl border border-purple-500/60 bg-[#191426] px-3 py-2 text-sm text-white"><option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="90">Last 3 months</option><option value="custom">Custom dates</option></select>@if($range === 'custom')<label class="text-xs text-gray-300">From <input type="date" wire:model.live="dateFrom" class="rounded-lg border border-purple-500 bg-[#191426] text-white"></label><label class="text-xs text-gray-300">To <input type="date" wire:model.live="dateTo" class="rounded-lg border border-purple-500 bg-[#191426] text-white"></label>@endif</div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach (['all'=>'All updates','price'=>'Price changes','stock'=>'Stock updates','restocked'=>'Back in stock','sold_out'=>'Out of stock'] as $key => $label)
                            <button type="button" wire:click="$set('tab','{{ $key }}')" class="rounded-xl border px-4 py-2 text-sm font-bold {{ $tab === $key ? 'border-purple-400 bg-purple-700' : 'border-gray-700 bg-gray-900' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="space-y-3 p-4 sm:p-5">
                    @if(in_array($tab, ['all','price'], true))
                        @foreach($prices as $event)
                            <article wire:key="recent-price-{{ $event->id }}" class="flex items-center gap-3 rounded-xl border border-purple-700/50 bg-[#191426] p-3">
                                @if($event->image_path)<img alt="" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($event->image_path) }}" class="h-14 w-14 shrink-0 rounded-lg bg-white object-contain dark:bg-gray-900">@endif
                                <div class="min-w-0 flex-1"><div class="font-bold">{{ $event->name }}</div><div class="text-xs text-purple-300">PRICE CHANGE · {{ \Illuminate\Support\Carbon::parse($event->created_at)->format('M j, g:i A') }}</div></div>
                                <div class="shrink-0 text-right text-sm"><span class="text-gray-500 line-through">{{ $event->old_price === null ? 'Unset' : '$'.number_format((float)$event->old_price,2) }}</span><span class="mx-2">→</span><span class="font-bold text-green-300">{{ $event->new_price === null ? 'Unset' : '$'.number_format((float)$event->new_price,2) }}</span></div>
                            </article>
                        @endforeach
                    @endif
                    @if(in_array($tab, ['all','stock','restocked','sold_out'], true))
                        @foreach($stock->filter(fn ($event) => ! in_array($tab, ['restocked', 'sold_out'], true) || $event->kind === $tab) as $event)
                            <article wire:key="recent-stock-{{ $event->id }}" class="flex items-center gap-3 rounded-xl border border-sky-700/50 bg-[#191426] p-3">
                                @if($event->image_path)<img alt="" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($event->image_path) }}" class="h-14 w-14 shrink-0 rounded-lg bg-white object-contain dark:bg-gray-900">@endif
                                <div class="min-w-0 flex-1"><div class="font-bold">{{ $event->name }}</div><div class="text-xs text-sky-300">{{ ucwords(str_replace('_',' ',$event->kind)) }} · {{ \Illuminate\Support\Carbon::parse($event->created_at)->format('M j, g:i A') }}</div></div>
                                <div class="shrink-0 text-right text-sm"><span class="text-gray-500 line-through">{{ number_format((float)$event->quantity_before) }}</span><span class="mx-2">→</span><span class="font-bold {{ $event->kind === 'sold_out' ? 'text-red-300' : 'text-green-300' }}">{{ number_format((float)$event->quantity_after) }} units</span></div>
                            </article>
                        @endforeach
                    @endif
                    @if(($tab === 'price' && $prices->isEmpty()) || (in_array($tab, ['stock','restocked','sold_out'], true) && $stock->filter(fn ($event) => ! in_array($tab, ['restocked','sold_out'], true) || $event->kind === $tab)->isEmpty()) || ($tab === 'all' && $prices->isEmpty() && $stock->isEmpty()))
                        <p class="rounded-xl border border-gray-700 p-5 text-center text-gray-500">No matching inventory changes recorded for this period.</p>
                    @endif
                </div>
                </div>
                <footer class="shrink-0 border-t border-purple-900 bg-[#100d23] p-4 text-right"><button type="button" x-on:click="dismiss()" class="min-h-11 w-full rounded-xl bg-gradient-to-r from-yellow-300 to-amber-400 px-5 py-3 font-extrabold text-gray-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-300 sm:w-auto">Done</button></footer>
            </section>
        </div>
        @endteleport
    @endif
</div>

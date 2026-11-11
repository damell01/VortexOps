<x-filament-panels::page>
    {{-- The shared camera scanner (the one Scan Inventory uses) announces a
         read with a window event; hand it straight to the page. --}}
    <div class="w-full max-w-2xl mx-auto space-y-4 pb-24 sm:space-y-6 sm:pb-0" data-vx-touch-page
        x-data
        x-on:barcode-scanned.window="$event.detail?.value && $wire.handleBarcodeScan($event.detail.value)">
        <x-touch-mode-toggle />
        {{-- Product Scanner Section --}}
        <div @class(['bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6', 'hidden' => $product || $lastAdd])>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Scan or search</h2>

            <button type="button"
                x-on:click="window.dispatchEvent(new CustomEvent('open-camera-scanner', { detail: { title: 'Scan product', helper: 'Hold the barcode inside the frame. The product loads automatically.' } }))"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-6 py-3 font-semibold text-white transition hover:bg-primary-500 active:opacity-75 touch-manipulation"
                style="min-height: 48px;">
                <x-heroicon-o-camera class="h-5 w-5" />
                Scan with camera
            </button>

            {{-- Manual Barcode Input --}}
            <div class="mt-4 space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Manual Entry</label>
                <div class="flex gap-2">
                    <input
                        wire:model="barcode"
                        wire:keydown.enter="scanBarcode"
                        type="text"
                        autocomplete="off"
                        placeholder="Enter barcode or SKU"
                        class="min-w-0 flex-1 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        style="min-height: 44px;"
                    />
                    <button
                        wire:click="scanBarcode"
                        wire:loading.attr="disabled"
                        wire:target="scanBarcode"
                        class="px-5 py-2 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-lg transition active:opacity-75 touch-manipulation focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
                        style="min-height: 44px;"
                    >
                        <span wire:loading.remove wire:target="scanBarcode">Search</span>
                        <span wire:loading wire:target="scanBarcode">Searching…</span>
                    </button>
                </div>
            </div>

            {{-- Keyword Search: for when there's no barcode to scan --}}
            <div class="relative mt-4 space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">No barcode? Find it by name</label>
                <input
                    id="vx-quick-add-search"
                    wire:model.live.debounce.300ms="productSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Search by product name, SKU or barcode…"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                    style="min-height: 44px;"
                />

                @if($productOptions !== null)
                    <div class="absolute z-20 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        @forelse($productOptions as $option)
                            <button type="button" wire:click="selectSearchResult({{ $option['id'] }})"
                                class="block w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $option['name'] }}</span>
                                <span class="ml-2 text-xs text-gray-500">{{ $option['sku'] ?: '—' }}</span>
                            </button>
                        @empty
                            <div class="px-4 py-2.5 text-xs text-gray-500">No matching products.</div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        {{-- Step 2: the picked item, in one card. Same layout as Scan Inventory's quick add. --}}
        @if($product)
            @php($qaOnHand = $this->onHand($product))
            <style>
                .vx-qa-act{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;min-height:64px;padding:6px 4px;border-radius:12px;border:1px solid rgb(229 231 235);background:#fff;font-size:11px;font-weight:700;line-height:1.15;text-align:center;color:rgb(55 65 81)}
                .vx-qa-act svg{width:22px;height:22px;color:rgb(124 58 237)}
                .dark .vx-qa-act{background:rgb(17 24 39);border-color:rgb(55 65 81);color:rgb(229 231 235)}
                .vx-qa-key{min-height:52px;border-radius:12px;border:1px solid rgb(209 213 219);background:#fff;font-size:22px!important;font-weight:800!important;color:rgb(17 24 39);touch-action:manipulation}
                .vx-qa-pm{font-size:30px!important;line-height:1;padding:0!important}
                .vx-qa-key:active{background:rgb(237 233 254)}
                .dark .vx-qa-key{background:rgb(31 41 55);border-color:rgb(55 65 81);color:#fff}
            </style>
            <section id="vx-quick-add-item" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                x-data="{ qty: $wire.entangle('quantity'), fresh: true, onHand: {{ $qaOnHand }},
                    n() { const v = parseFloat(this.qty); return isFinite(v) && v > 0 ? v : 0 },
                    key(d) { const cur = this.fresh ? '' : String(this.qty ?? ''); this.fresh = false; const next = (cur === '0' ? '' : cur) + d; if (next.length <= 6) this.qty = Number(next) },
                    back() { this.fresh = false; const cur = String(this.qty ?? ''); this.qty = cur.length > 1 ? Number(cur.slice(0, -1)) : 0 },
                    step(d) { this.fresh = true; this.qty = Math.max(1, this.n() + d) } }"
                x-init="$nextTick(() => $el.scrollIntoView({ block: 'start' }))" style="scroll-margin-top:90px">
                <div class="flex items-center gap-3 p-3 sm:p-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gray-50 dark:bg-gray-800">
                        @if($product->hasImage())<img src="{{ $product->imageUrl() }}" alt="" class="h-full w-full object-cover" />@else<x-heroicon-o-cube class="h-8 w-8 text-gray-400" />@endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-base font-extrabold text-gray-950 dark:text-white sm:text-lg">{{ $product->name }}</h3>
                        <p class="truncate text-xs text-gray-500">SKU {{ $product->sku ?: '—' }}</p>
                        <p class="mt-0.5 text-sm"><b class="text-emerald-700 dark:text-emerald-400">{{ number_format($qaOnHand) }}</b> <span class="text-gray-500">on hand</span></p>
                    </div>
                </div>
                <div class="border-t border-gray-100 p-3 dark:border-gray-800 sm:p-4">
                    <div class="grid grid-cols-2 gap-1.5" role="group" aria-label="Item actions">
                        <button type="button" class="vx-qa-act" x-on:click="$wire.startOver().then(() => window.dispatchEvent(new CustomEvent('open-camera-scanner', { detail: { title: 'Scan product' } })))"><x-heroicon-o-camera /> <span>Scan another</span></button>
                        <button type="button" class="vx-qa-act" x-on:click="$wire.startOver().then(() => $nextTick(() => document.getElementById('vx-quick-add-search')?.focus()))"><x-heroicon-o-magnifying-glass /> <span>Look up item</span></button>
                    </div>

                    <label class="mt-3 block text-xs font-bold text-gray-700 dark:text-gray-200" for="vx-quick-add-qty">Quantity</label>
                    <div class="mt-1 grid grid-cols-[3.25rem_minmax(0,1fr)_3.25rem] overflow-hidden rounded-xl border border-gray-300 dark:border-gray-700">
                        <button type="button" x-on:click="step(-1)" class="vx-qa-pm min-h-14 bg-violet-50 font-bold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300" aria-label="One less">−</button>
                        <input id="vx-quick-add-qty" x-model.number="qty" type="text" inputmode="none" pattern="[0-9]*" autocomplete="off" aria-label="Quantity to add" x-on:focus="$el.select(); fresh = false" x-on:keydown.enter.prevent="$wire.addStock()"
                            class="min-h-14 w-full border-0 border-x border-gray-300 text-center text-2xl font-extrabold tabular-nums focus:ring-0 dark:border-gray-700 dark:bg-gray-900 dark:text-white" />
                        <button type="button" x-on:click="step(1)" class="vx-qa-pm min-h-14 bg-violet-50 font-bold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300" aria-label="One more">+</button>
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-1.5" aria-label="Number pad">
                        @foreach(['1','2','3','4','5','6','7','8','9'] as $digit)
                            <button type="button" x-on:click="key('{{ $digit }}')" class="vx-qa-key">{{ $digit }}</button>
                        @endforeach
                        <button type="button" x-on:click="fresh = false; qty = 0" class="vx-qa-key text-sm">Clear</button>
                        <button type="button" x-on:click="key('0')" class="vx-qa-key">0</button>
                        <button type="button" x-on:click="back()" class="vx-qa-key" aria-label="Delete last digit"><x-heroicon-o-backspace class="mx-auto h-6 w-6" /></button>
                    </div>

                    <label class="mt-3 block text-xs font-bold text-gray-700 dark:text-gray-200" for="vx-quick-add-loc">Location</label>
                    <select id="vx-quick-add-loc" wire:model="locationId" class="mt-1 min-h-12 w-full rounded-xl border-gray-300 text-base font-semibold dark:border-gray-700 dark:bg-gray-800">
                        <option value="">Select location…</option>
                        @foreach($this->locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>

                    <p class="mt-3 flex items-center gap-2 rounded-lg bg-violet-50 px-3 py-2 text-sm dark:bg-violet-950/30"><x-heroicon-o-cube class="h-4 w-4 text-violet-600" /> On hand: <b x-text="onHand.toLocaleString()"></b> → <b class="text-emerald-700 dark:text-emerald-400" x-text="(onHand + n()).toLocaleString()"></b></p>

                    <button type="button" wire:click="addStock" wire:loading.attr="disabled" wire:target="addStock" x-bind:disabled="n() <= 0"
                        class="mt-3 hidden min-h-12 w-full rounded-xl bg-violet-600 px-4 text-sm font-bold text-white disabled:opacity-50 sm:block">
                        <span wire:loading.remove wire:target="addStock" x-text="'Add ' + n().toLocaleString() + (n() === 1 ? ' unit' : ' units')"></span>
                        <span wire:loading wire:target="addStock">Adding…</span>
                    </button>
                </div>
                <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden">
                    <button type="button" wire:click="addStock" wire:loading.attr="disabled" wire:target="addStock" x-bind:disabled="n() <= 0" class="min-h-12 w-full rounded-xl bg-violet-600 px-4 text-base font-bold text-white disabled:opacity-50">
                        <span wire:loading.remove wire:target="addStock" x-text="'Add ' + n().toLocaleString() + (n() === 1 ? ' unit' : ' units')"></span>
                        <span wire:loading wire:target="addStock">Adding…</span>
                    </button>
                </div>
            </section>
        @elseif($lastAdd)
            {{-- Saved: what happened, and the next item one tap away. --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-4 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900" role="status" aria-live="polite">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50"><x-heroicon-o-check class="h-7 w-7" /></div>
                <p class="mt-2 text-base font-extrabold text-gray-950 dark:text-white">Added {{ number_format((float) $lastAdd['quantity']) }} {{ \Illuminate\Support\Str::plural('unit', (float) $lastAdd['quantity']) }} to {{ $lastAdd['location'] }}</p>
                <p class="text-xs text-gray-500">{{ $lastAdd['name'] }} · now {{ number_format((float) $lastAdd['location_total']) }} there · {{ number_format((float) $lastAdd['total']) }} on hand in all</p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button type="button" x-on:click="$wire.startOver().then(() => window.dispatchEvent(new CustomEvent('open-camera-scanner', { detail: { title: 'Scan product' } })))" class="col-span-2 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-violet-600 px-3 text-sm font-bold text-white"><x-heroicon-o-camera class="h-5 w-5" /> Scan next</button>
                    <button type="button" x-on:click="$wire.startOver().then(() => $nextTick(() => document.getElementById('vx-quick-add-search')?.focus()))" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-violet-200 px-3 text-sm font-bold text-violet-700 dark:border-violet-900 dark:text-violet-300"><x-heroicon-o-magnifying-glass class="h-5 w-5" /> Look up item</button>
                    <button type="button" wire:click="addMoreOfLast" class="min-h-12 rounded-xl border border-gray-200 px-3 text-sm font-bold text-gray-700 dark:border-gray-700 dark:text-gray-200">Add more</button>
                </div>
            </section>
        @endif

        {{-- Recent Additions --}}
        @if(!empty($recentAdds))
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3">Recent Additions</h3>
            <ul class="space-y-2">
                @foreach($recentAdds as $add)
                <li class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $add['product_name'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $add['quantity'] }} units → {{ $add['location'] }}
                        </p>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-500">{{ $add['timestamp'] }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    <script>
        document.addEventListener('scanSuccess', () => {
            if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
        });
        document.addEventListener('scanError', () => {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100, 50, 100]);
        });
    </script>
</x-filament-panels::page>

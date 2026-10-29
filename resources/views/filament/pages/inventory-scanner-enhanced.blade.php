<x-filament-panels::page>
    <div
        class="mx-auto w-full max-w-7xl space-y-4 pb-24 sm:space-y-5 sm:pb-0"
        data-vx-page="inventory-scanner"
        data-vx-touch-page
        x-data="{
            openScanner() { window.dispatchEvent(new CustomEvent('open-camera-scanner')); },
            useScan(event) {
                const value = event?.detail?.value;
                if (!value) return;
                $wire.barcodeScanned(value);
            }
        }"
        x-on:barcode-scanned.window="useScan($event)"
    >
        <x-touch-mode-toggle />
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="grid grid-cols-2 gap-2 bg-white p-2 dark:bg-gray-900 sm:gap-3 sm:p-3">
                <button type="button" wire:click="switchMode('lookup')" class="min-h-12 rounded-xl border border-gray-200 px-2 py-3 text-xs font-semibold transition-colors dark:border-gray-700 sm:min-h-16 sm:px-3 sm:text-sm {{ $mode === 'lookup' ? 'bg-violet-600 text-white shadow-sm' : 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}"><x-heroicon-o-magnifying-glass class="mr-1 inline h-4 w-4" /> Scan & Add Stock</button>
                <button type="button" wire:click="switchMode('quickadd')" class="hidden min-h-12 rounded-xl border border-gray-200 px-2 py-3 text-xs font-semibold transition-colors dark:border-gray-700 sm:min-h-16 sm:px-3 sm:text-sm {{ $mode === 'quickadd' ? 'bg-violet-600 text-white shadow-sm' : 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}"><x-heroicon-o-plus-circle class="mr-1 inline h-4 w-4" /> Add Stock</button>
                <button type="button" wire:click="switchMode('receive')" class="min-h-12 rounded-xl border border-gray-200 px-2 py-3 text-xs font-semibold transition-colors dark:border-gray-700 sm:min-h-16 sm:px-3 sm:text-sm {{ $mode === 'receive' ? 'bg-violet-600 text-white shadow-sm' : 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}"><x-heroicon-o-inbox-arrow-down class="mr-1 inline h-4 w-4" /> Receive</button>
            </div>
        </section>

        @if($mode === 'receive')
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <label>
                        <span class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-200">Which delivery?</span>
                        <select wire:model.live="rcvPalletId" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm">
                            <option value="">Choose a pallet…</option>
                            @foreach($this->pallets as $pallet)
                                <option value="{{ $pallet->id }}">{{ $pallet->reference ?: 'Pallet #' . $pallet->id }} — {{ $pallet->status }}</option>
                            @endforeach
                        </select>
                    </label>
                    {{-- The manifest is no longer a prerequisite, so starting from nothing is a button, not a workaround. --}}
                    <button type="button" wire:click="startBlankPallet" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        <x-heroicon-o-plus class="h-4 w-4" /> No manifest
                    </button>
                </div>
            </section>

            @if($rcvError)<section class="rounded-xl border border-red-200 bg-red-50 p-3.5 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">{{ $rcvError }}</section>@endif
            @if($rcvFlash)<section class="rounded-xl border border-green-200 bg-green-50 p-3.5 text-sm text-green-700 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200">{{ $rcvFlash }}</section>@endif

            @if($rcvProgress)
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $rcvProgress['reference'] ?: 'Untitled delivery' }}</div>
                            <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">{{ $rcvProgress['vendor'] }}</div>
                        </div>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300 sm:text-xs">
                            {{ $rcvProgress['done_lines'] }} / {{ $rcvProgress['total_lines'] }} lines in
                        </span>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rcvProgress['lines'] as $line)
                            <div @class([
                                'p-4 sm:p-5',
                                'bg-green-50/50 dark:bg-green-950/20' => $line['done'],
                                'bg-primary-50/60 dark:bg-primary-950/30' => $lastScannedLineId === $line['line_id'],
                            ])>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $line['item_name'] }}</div>
                                        <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                            SKU {{ $line['sku'] ?: '—' }}
                                            @unless($line['is_mapped'])
                                                · <span class="font-semibold text-amber-700 dark:text-amber-300">not linked to an item yet</span>
                                            @endunless
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $line['received_cases'] }} / {{ $line['total_cases'] }}</div>
                                        <div class="text-[10px] uppercase tracking-wide text-gray-400">cases</div>
                                    </div>
                                </div>

                                {{--
                                    The three answers a delivery has, on the line
                                    they apply to. Scanning fills the middle one
                                    in for you; it is not a separate screen.
                                --}}
                                @unless($line['done'])
                                    <div class="mt-3 flex flex-wrap items-center gap-2" x-data="{ some: '' }">
                                        <button type="button" wire:click="receiveAllOfLine({{ $line['line_id'] }})"
                                            @disabled(! $line['is_mapped'])
                                            class="inline-flex min-h-10 items-center rounded-lg bg-green-600 px-3 text-xs font-semibold text-white disabled:opacity-40 sm:text-sm">All arrived</button>

                                        <div class="inline-flex items-center gap-1.5">
                                            <input type="number" min="1" max="{{ max(1, $line['total_cases'] - $line['received_cases']) }}" inputmode="numeric" x-model="some" placeholder="#"
                                                class="min-h-10 w-16 rounded-lg border-gray-300 text-center text-sm dark:border-gray-600 dark:bg-gray-800"
                                                aria-label="How many cases of {{ $line['item_name'] }} arrived" />
                                            <button type="button" x-on:click="$wire.receiveSomeOfLine({{ $line['line_id'] }}, some); some = ''"
                                                @disabled(! $line['is_mapped'])
                                                class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:text-sm">Some</button>
                                        </div>

                                        <button type="button" wire:click="markLineShort({{ $line['line_id'] }})"
                                            wire:confirm="Report the rest of this line as missing?"
                                            class="inline-flex min-h-10 items-center rounded-lg px-3 text-xs font-semibold text-gray-500 hover:text-red-600 sm:text-sm">Not here</button>
                                    </div>
                                @endunless
                            </div>
                        @empty
                            <div class="p-6 text-center text-sm text-gray-500 sm:p-8">
                                Nothing on this delivery yet. Scan an item below to put it in, or add lines from the pallet.
                            </div>
                        @endforelse
                    </div>

                    {{--
                        Something turned up that nobody typed up. Priced here
                        because this is the screen you are standing at with the
                        box — the two manifest editors that take a cost are the
                        ones you are not at.
                    --}}
                    <div class="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/60 sm:p-5">
                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Not on this delivery? Add it</div>

                        <div class="relative mt-2.5">
                            <input type="text" wire:model.live.debounce.300ms="manualItemSearch"
                                placeholder="Search an item by name, SKU or barcode…"
                                class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />

                            @if($manualItemOptions)
                                <div class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                    @foreach($manualItemOptions as $option)
                                        <button type="button" wire:click="selectManualItem({{ $option['id'] }})"
                                            class="block w-full px-3 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <span class="font-medium text-gray-950 dark:text-white">{{ $option['name'] }}</span>
                                            <span class="ml-2 text-xs text-gray-500">{{ $option['sku'] ?: '—' }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="mt-2.5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <label>
                                <span class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300">Cases</span>
                                <input type="number" min="1" inputmode="numeric" wire:model="manualCaseCount"
                                    class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />
                            </label>
                            <label>
                                <span class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300">Units per case</span>
                                <input type="number" min="1" step="any" inputmode="numeric" wire:model="manualQtyPerCase"
                                    class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />
                            </label>
                            <label>
                                <span class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300">Unit cost</span>
                                <input type="number" min="0" step="0.01" inputmode="decimal" wire:model="manualUnitCost" placeholder="item average"
                                    class="min-h-11 w-full rounded-lg border-gray-300 text-base tabular-nums dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />
                            </label>
                            <label>
                                <span class="mb-1 block text-[11px] font-medium text-gray-600 dark:text-gray-300">Into</span>
                                <select wire:model="manualLocationId" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm">
                                    <option value="">Default receiving</option>
                                    @foreach($this->locations as $location)
                                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <button type="button" wire:click="addUnlistedToDelivery" @disabled(! $manualItemId)
                            class="mt-3 inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-600 px-4 text-sm font-semibold text-white disabled:opacity-40">
                            Add &amp; receive
                        </button>
                        <p class="mt-1.5 text-[11px] leading-4 text-gray-500">Leave the cost blank to keep the item's current average.</p>
                    </div>
                </section>
            @endif
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">Scan or Enter Barcode</h2>
            <button data-camera-scan type="button" @click="openScanner()" class="mt-3 flex min-h-36 w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-violet-400 bg-violet-50 p-4 text-violet-700 dark:bg-violet-950/30 dark:text-violet-200 sm:hidden">
                <x-heroicon-o-camera class="h-9 w-9" />
                <span class="text-sm font-bold">Tap to open camera</span>
                <span class="text-xs">Point your camera at the barcode</span>
            </button>
            <label for="vx-scanner-code" class="mt-3 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Barcode, UPC, or SKU</label>
            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                <input id="vx-scanner-code" wire:model="scanInput" wire:keydown.enter.prevent="submitScan" type="text" inputmode="text" autocomplete="off" autocapitalize="none" autofocus placeholder="Scan or type a code…" class="min-h-12 min-w-0 rounded-lg border-gray-300 bg-white px-3 font-mono text-base dark:border-gray-600 dark:bg-gray-900" />
                <button data-camera-scan type="button" @click="openScanner()" class="hidden min-h-12 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold sm:inline-flex text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"><x-heroicon-o-camera class="h-5 w-5" /> Camera</button>
                <button type="button" wire:click="submitScan" wire:loading.attr="disabled" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white disabled:opacity-60 sm:w-auto"><span wire:loading.remove>@if($mode === 'lookup') Look Up @elseif($mode === 'receive') Receive @else Add Stock @endif</span><span wire:loading>Working…</span></button>
            </div>
            <p class="mt-2 text-[10px] leading-4 text-gray-500 sm:text-xs">USB/Bluetooth scanner: scan directly into this field. Camera: tap Camera and center the barcode in the scan box.</p>

            @if(in_array($mode, ['lookup', 'quickadd'], true))
                <div class="relative mt-3 border-t border-gray-200/70 pt-3 dark:border-gray-700/70">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">No barcode? Find it by name</label>
                    <input type="text" wire:model.live.debounce.500ms="keywordSearch" autocomplete="off"
                        placeholder="Search by item name, SKU or barcode…"
                        class="mt-2 min-h-11 w-full rounded-lg border-gray-300 bg-white px-3 text-base dark:border-gray-600 dark:bg-gray-900" />

                    @if($keywordOptions !== null)
                        <div class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                            @forelse($keywordOptions as $option)
                                <button type="button" wire:click="selectKeywordItem({{ $option['id'] }})"
                                    class="block w-full px-3 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $option['name'] }}</span>
                                    <span class="ml-2 text-xs text-gray-500">{{ $option['sku'] ?: '—' }}</span>
                                </button>
                            @empty
                                <div class="px-3 py-2.5 text-xs text-gray-500">No matching items.</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endif
        </section>

        @if($unmatchedBarcode && $mode === 'lookup')
            @teleport('body')
            <div class="fixed inset-0 z-[9999] flex items-center justify-center overflow-hidden bg-slate-950/70 p-0 backdrop-blur-sm sm:p-3" role="presentation" x-data="{ choice: 'link' }" x-init="$wire.openBarcodeCatalog()" @keydown.escape.window="$wire.switchMode('lookup')">
                <section role="dialog" aria-modal="true" aria-labelledby="vx-barcode-title" class="flex h-[100dvh] w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-gray-900 sm:h-[calc(100dvh-1.5rem)] sm:max-w-6xl sm:rounded-2xl">
                    <header class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 p-4 dark:border-gray-800 sm:px-6">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-600 text-white"><x-heroicon-o-qr-code class="h-6 w-6" /></div>
                            <div class="min-w-0">
                                <h2 id="vx-barcode-title" class="text-lg font-extrabold text-gray-950 dark:text-white">New barcode detected</h2>
                                <p class="break-all font-mono text-sm font-bold text-violet-700 dark:text-violet-300">{{ $unmatchedBarcode }}</p>
                                <p class="text-xs text-gray-500">Find an existing item or create one. Stock is optional.</p>
                            </div>
                        </div>
                        <button type="button" wire:click="switchMode('lookup')" aria-label="Close barcode dialog" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </header>
                    @if($errorMessage)<p class="mx-4 mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700 dark:bg-red-950/30 dark:text-red-200" role="alert">{{ $errorMessage }}</p>@endif
                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-3 sm:p-5">
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" @click="choice='link'; $wire.barcodeBackToCatalog()" :class="choice === 'link' ? 'border-violet-600 bg-violet-50 text-violet-700 dark:bg-violet-950' : 'border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-200'" class="min-h-14 rounded-xl border-2 px-3 text-left text-sm font-bold">Link to Existing Item<span class="block text-xs font-normal">Search catalog</span></button>
                            <button type="button" @click="choice='new'" :class="choice === 'new' ? 'border-violet-600 bg-violet-50 text-violet-700 dark:bg-violet-950' : 'border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-200'" class="min-h-14 rounded-xl border-2 px-3 text-left text-sm font-bold">Create New Item<span class="block text-xs font-normal">Quick create</span></button>
                        </div>
                        <div x-show="choice === 'link'" class="mt-4">
                            @if($barcodeStep === 'confirm' && $this->catalogSelectedItem)
                                @php($preview = $this->catalogSelectedItem)
                                <div class="rounded-xl border border-violet-200 bg-violet-50/40 p-4 dark:border-violet-900 dark:bg-violet-950/20">
                                    <h3 class="text-base font-extrabold text-gray-950 dark:text-white">Confirm barcode link</h3>
                                    <p class="mt-1 text-xs text-gray-500">Nothing is saved until you press Link to This Item.</p>
                                    <div class="mt-4 flex items-center gap-4 rounded-xl bg-white p-3 dark:bg-gray-800">
                                        @if($preview['image'])<img src="{{ $preview['image'] }}" alt="" class="h-20 w-20 shrink-0 rounded-lg object-contain" />@else<div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700"><x-heroicon-o-cube class="h-8 w-8 text-gray-400" /></div>@endif
                                        <div class="min-w-0"><p class="font-bold text-gray-950 dark:text-white">{{ $preview['name'] }}</p><p class="text-sm text-gray-500">{{ $preview['sku'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $preview['category'] ?: 'Uncategorized' }} · {{ number_format($preview['quantity']) }} units in stock</p></div>
                                    </div>
                                </div>
                            @else
                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem]">
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200">Search inventory
                                        <input type="search" wire:model.live.debounce.300ms="barcodeLinkSearch" placeholder="Name, SKU or barcode…" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" />
                                    </label>
                                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200">Sort
                                        <select wire:model.live="catalogSort" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800"><option value="name">Name A–Z</option><option value="newest">Newest</option></select>
                                    </label>
                                </div>
                                <div class="mt-3 flex gap-2 overflow-x-auto pb-2">
                                    <button type="button" wire:click="$set('catalogCategory', '')" class="min-h-11 shrink-0 rounded-lg px-3 text-xs font-bold {{ $catalogCategory === '' ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">All Items</button>
                                    @foreach($this->catalogCategories as $category)
                                        <button type="button" wire:click="$set('catalogCategory', @js($category))" class="min-h-11 shrink-0 rounded-lg px-3 text-xs font-bold {{ $catalogCategory === $category ? 'bg-violet-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $category }}</button>
                                    @endforeach
                                </div>
                                @if($barcodeLinkOptions !== null)
                                    <div class="mt-2 flex items-center justify-between text-xs text-gray-500"><span>{{ number_format($barcodeLinkOptions['total']) }} items found</span><span>Page {{ $catalogPage }} of {{ $barcodeLinkOptions['pages'] }}</span></div>
                                    <div class="mt-2 max-h-[min(48dvh,540px)] divide-y divide-gray-100 overflow-y-auto overscroll-contain rounded-xl border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                                        @forelse($barcodeLinkOptions['items'] as $option)
                                            <div wire:key="catalog-row-{{ $option['id'] }}" class="flex min-w-0 items-center gap-3 p-2 sm:p-3">
                                                <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                                    @if($option['image'])<img src="{{ $option['image'] }}" alt="" loading="lazy" class="h-full w-full object-contain" />@else<x-heroicon-o-cube class="h-6 w-6 text-gray-400" />@endif
                                                </div>
                                                <div class="min-w-0 flex-1"><p class="text-sm font-bold leading-tight text-gray-950 dark:text-white">{{ $option['name'] }}</p><p class="mt-0.5 truncate text-xs text-gray-500">{{ $option['sku'] ?: 'No SKU' }} <span class="hidden sm:inline">· {{ $option['category'] ?: 'Uncategorized' }}</span></p></div>
                                                <span class="hidden shrink-0 text-xs font-semibold text-emerald-700 dark:text-emerald-300 sm:block">{{ number_format($option['quantity']) }} units</span>
                                                <button type="button" wire:click="catalogSelectItem({{ $option['id'] }})" wire:loading.attr="disabled" class="min-h-11 shrink-0 rounded-lg bg-violet-600 px-3 text-xs font-bold text-white disabled:opacity-50">Select</button>
                                            </div>
                                        @empty
                                            <p class="p-5 text-center text-sm text-gray-500">No matches. Try another search or category.</p>
                                        @endforelse
                                    </div>
                                    <div class="mt-3 flex items-center justify-between gap-3">
                                        <button type="button" wire:click="catalogGoToPage({{ max(1, $catalogPage - 1) }})" @disabled($catalogPage <= 1) class="min-h-11 rounded-lg border border-gray-200 px-4 text-sm font-semibold disabled:opacity-40 dark:border-gray-700">Previous</button>
                                        <span class="text-xs text-gray-500">{{ $catalogPage }} / {{ $barcodeLinkOptions['pages'] }}</span>
                                        <button type="button" wire:click="catalogGoToPage({{ min($barcodeLinkOptions['pages'], $catalogPage + 1) }})" @disabled($catalogPage >= $barcodeLinkOptions['pages']) class="min-h-11 rounded-lg border border-gray-200 px-4 text-sm font-semibold disabled:opacity-40 dark:border-gray-700">Next</button>
                                    </div>
                                @else
                                    <p class="p-4 text-sm text-gray-500">Loading catalog…</p>
                                @endif
                            @endif
                        </div>
                        <div x-show="choice === 'new'" x-cloak class="mt-4 space-y-3">
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">Create inventory item</h3>
                            <label class="block text-xs font-semibold">Item name *<input wire:model="newItemName" type="text" maxlength="255" placeholder="e.g. Pokémon Booster Box" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" /></label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-xs font-semibold">Item cost (optional)<span class="mt-0.5 block font-normal text-gray-500">What you paid per unit</span><input wire:model="newItemCost" type="number" min="0" step="0.01" inputmode="decimal" placeholder="$0.00" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" /></label>
                                <label class="block text-xs font-semibold">Target sale price (optional)<span class="mt-0.5 block font-normal text-gray-500">What you want to sell it for</span><input wire:model="newItemPrice" type="number" min="0" step="0.01" inputmode="decimal" placeholder="$0.00" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" /></label>
                            </div>
                            <p class="text-xs text-gray-500">Barcode {{ $unmatchedBarcode }} will be assigned automatically. You can add a photo after saving the item.</p>
                        </div>
                        <div x-show="choice === 'new' || $wire.barcodeStep === 'confirm'" class="mt-4 rounded-xl border border-violet-100 bg-violet-50/50 p-3 dark:border-violet-900 dark:bg-violet-950/20 sm:p-4">
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-bold"><input type="checkbox" wire:model.live="addStockAfterResolve" class="h-5 w-5 rounded border-gray-300 text-violet-600" /> Add stock now <span class="font-normal text-gray-500">(optional)</span></label>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <label class="block text-xs font-semibold">Quantity<input type="number" min="0.01" step="1" wire:model="qaQty" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" /></label>
                                <label class="block text-xs font-semibold">Stock location<select wire:model="qaLocationId" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">@foreach($this->locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                            </div>
                        </div>
                    </div>
                    <footer class="flex shrink-0 flex-wrap gap-2 border-t border-gray-100 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_14px_rgba(0,0,0,0.05)] dark:border-gray-800 dark:bg-gray-900 sm:px-6">
                        <button type="button" wire:click="{{ $barcodeStep === 'confirm' ? 'barcodeBackToCatalog' : "switchMode('lookup')" }}" class="min-h-11 flex-1 rounded-lg border border-gray-200 px-4 text-sm font-semibold dark:border-gray-700">{{ $barcodeStep === 'confirm' ? 'Back to catalog' : 'Cancel / Scan again' }}</button>
                        <button x-show="choice === 'new'" type="button" wire:click="createScannedItem" wire:loading.attr="disabled" wire:target="createScannedItem" class="min-h-11 flex-1 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white disabled:opacity-50">Create Item &amp; Link Barcode</button>
                        @if($barcodeStep === 'confirm' && $this->catalogSelectedItem)
                            <button x-show="choice === 'link'" type="button" wire:click="linkScannedBarcode({{ $catalogSelectedItemId }})" wire:loading.attr="disabled" wire:target="linkScannedBarcode" class="min-h-11 flex-1 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white disabled:opacity-50">Link to This Item</button>
                        @endif
                    </footer>
                </section>
            </div>
            @endteleport
        @endif

        @if($barcodeSuccess)
            <section class="mt-4 rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm dark:border-emerald-900 dark:bg-gray-900" role="status" aria-live="polite">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"><x-heroicon-o-check-circle class="h-7 w-7" /></div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-bold text-gray-950 dark:text-white">{{ $barcodeSuccess['action'] === 'created' ? 'Item created successfully!' : 'Barcode linked successfully!' }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $barcodeSuccess['name'] }} · Barcode {{ $barcodeSuccess['barcode'] }}</p>
                        @if($barcodeSuccess['stock'])
                            <p class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Added {{ number_format($barcodeSuccess['stock']['qty']) }} units to {{ $barcodeSuccess['stock']['location'] }}.</p>
                        @elseif($barcodeSuccess['stock_error'])
                            <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-200">Item saved, but stock was not added: {{ $barcodeSuccess['stock_error'] }}</p>
                        @else
                            <p class="mt-2 text-sm text-gray-500">No stock was added.</p>
                        @endif
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" wire:click="switchMode('lookup')" class="min-h-11 flex-1 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white">Scan another</button>
                    <button type="button" wire:click="$set('barcodeSuccess', null)" class="min-h-11 flex-1 rounded-lg border border-gray-200 px-4 text-sm font-semibold dark:border-gray-700">View item / Add stock</button>
                </div>
            </section>
        @endif

        @if($mode === 'quickadd' && $selectedItem && !$barcodeSuccess)
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-bold text-green-700">Item found</span>
                    <h2 class="mt-2 text-lg font-bold text-gray-950 dark:text-white">{{ $selectedItem['name'] }}</h2>
                    <p class="mt-1 text-xs text-gray-500">SKU {{ $selectedItem['sku'] }} · {{ number_format((float) $selectedItem['total_qty']) }} units on hand</p>
                </div>
                <div class="p-4 sm:p-5">
                    <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="flex items-start gap-3">
                            @php($photoItem = \App\Models\InventoryItem::find($selectedItemId))
                            @if($photoItem?->hasImage())
                                <img src="{{ $photoItem->imageUrl() }}" alt="Current item photo" class="h-16 w-16 shrink-0 rounded-lg object-cover" />
                            @else
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg bg-gray-200 text-gray-500 dark:bg-gray-700" aria-hidden="true"><x-heroicon-o-camera class="h-7 w-7" /></div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <label for="vx-scanned-item-photo" class="block text-sm font-semibold text-gray-900 dark:text-white">Item photo <span class="font-normal text-gray-500">(optional)</span></label>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Choose a photo from your device or camera. JPG, PNG or WebP, up to 5 MB.</p>
                                <input id="vx-scanned-item-photo" type="file" accept="image/jpeg,image/png,image/webp" wire:model="itemPhoto" class="mt-2 block w-full min-w-0 text-xs text-gray-700 file:mr-2 file:rounded-lg file:border-0 file:bg-violet-100 file:px-3 file:py-2 file:font-semibold file:text-violet-800 dark:text-gray-200" />
                                @error('itemPhoto')<p class="mt-1 text-xs font-semibold text-red-600" role="alert">{{ $message }}</p>@enderror
                                @if($photoFeedback)<p class="mt-1 text-xs font-semibold text-green-700" role="status">{{ $photoFeedback }}</p>@endif
                                <div wire:loading wire:target="itemPhoto" class="mt-1 text-xs text-violet-700" role="status">Uploading photo…</div>
                                @if($itemPhoto)
                                    <button type="button" wire:click="saveSelectedItemPhoto" wire:loading.attr="disabled" wire:target="itemPhoto,saveSelectedItemPhoto" class="mt-2 min-h-11 rounded-lg bg-violet-600 px-4 text-sm font-bold text-white disabled:opacity-60">
                                        <span wire:loading.remove wire:target="saveSelectedItemPhoto">Save item photo</span>
                                        <span wire:loading wire:target="saveSelectedItemPhoto">Saving photo…</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    <h3 class="mb-3 text-sm font-bold text-gray-950 dark:text-white">Add to Inventory</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block"><span class="mb-1 block text-xs font-bold">Quantity</span><input wire:model="qaQty" type="number" min="0.01" step="0.01" class="min-h-12 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800" /></label>
                        <label class="block"><span class="mb-1 block text-xs font-bold">Location</span><select wire:model="qaLocationId" class="min-h-12 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800">@foreach($this->locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    </div>
                    <button type="button" wire:click="addSelectedStock" wire:loading.attr="disabled" wire:target="addSelectedStock" class="mt-4 min-h-12 w-full rounded-lg bg-violet-600 px-4 text-sm font-bold text-white disabled:opacity-60"><span wire:loading.remove wire:target="addSelectedStock">Confirm Add Stock</span><span wire:loading wire:target="addSelectedStock">Adding stock…</span></button>
                </div>
            </section>
        @endif

        @if($errorMessage)<section class="rounded-xl border border-red-200 bg-red-50 p-3.5 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200"><strong>Not found.</strong> {{ $errorMessage }}</section>@endif

        @if($qaFlash)
            <section class="rounded-xl border p-3.5 sm:p-4 {{ isset($qaFlash['error']) ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200' : 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200' }}">
                @if(isset($qaFlash['error']))<div class="text-sm font-semibold">{{ $qaFlash['error'] }}</div>@else<div class="flex items-start gap-2.5"><x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0" /><div><div class="text-sm font-semibold">{{ $qaFlash['created'] ? 'Created and stocked' : 'Stock added' }}: {{ $qaFlash['name'] }}</div><div class="mt-0.5 text-xs">+{{ number_format((float)$qaFlash['qty']) }} at {{ $qaFlash['location'] }}</div><div class="mt-1 text-xs font-semibold">Now {{ number_format((float)($qaFlash['location_total'] ?? 0)) }} units at {{ $qaFlash['location'] }}</div></div></div>@endif
            </section>
        @endif

        @if($mode === 'lookup' && $result)
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><div class="mb-1 text-xs font-bold uppercase tracking-wider text-violet-600 dark:text-violet-300">Scan Result · Item Found</div><div class="text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $result['name'] }}</div><div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs"><span>SKU {{ $result['sku'] ?: '—' }}</span>@if($result['barcode'])<span>UPC {{ $result['barcode'] }}</span>@endif</div></div>
                        <span class="rounded-full px-2 py-1 text-[10px] font-semibold sm:text-xs {{ $result['is_low'] ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200' : 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' }}">{{ $result['is_low'] ? 'Low stock' : 'In stock' }}</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">On hand</div><div class="mt-0.5 text-xl font-semibold">{{ number_format((float)$result['total_qty']) }}</div></div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Avg cost</div><div class="mt-0.5 truncate text-base font-semibold sm:text-xl">${{ number_format((float)$result['avg_cost'], 2) }}</div></div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Sale price</div><div class="mt-0.5 truncate text-base font-semibold sm:text-xl">{{ $result['sale_price'] > 0 ? '$' . number_format((float)$result['sale_price'], 2) : '—' }}</div></div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Value</div><div class="mt-0.5 truncate text-base font-semibold sm:text-xl">${{ number_format((float)$result['inventory_value'], 2) }}</div></div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $result['id']]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">View Item</a>
                        <button type="button" wire:click="switchMode('quickadd')" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-violet-600 px-3 text-sm font-semibold text-white">Add Stock</button>
                    </div>
                </div>
                <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                    <h3 class="text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Stock by location</h3>
                    <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">@forelse($result['stock'] as $stock)<div class="flex items-center justify-between py-2.5 text-sm"><span class="text-gray-600 dark:text-gray-300">{{ $stock['location'] }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ number_format((float)$stock['qty']) }}</span></div>@empty<div class="py-4 text-xs text-gray-500">No stock currently recorded.</div>@endforelse</div>
                </div>
                @if($costWarnings)<div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">@foreach($costWarnings as $warning)<div class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 last:mb-0 dark:bg-amber-950/30 dark:text-amber-200"><strong>{{ $warning['title'] }}:</strong> {{ $warning['message'] }}</div>@endforeach</div>@endif
            </section>
        @endif

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden" data-vx-mobile-actions><button data-camera-scan type="button" @click="openScanner()" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white"><x-heroicon-o-camera class="h-5 w-5" /> Open Camera Scanner</button></div>
    </div>
</x-filament-panels::page>

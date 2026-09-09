<div
    x-data
    x-on:inventory-quick-stock.window="$wire.openForProduct($event.detail.productId)"
    x-on:barcode-scanned.window="if ($wire.open) $wire.captureBarcode($event.detail.value)"
    x-on:keydown.escape.window="if ($wire.open) $wire.close()"
>
    @if($open)
        <div class="vx-quick-stock-overlay fixed inset-0 z-[9999] flex items-start justify-center overflow-y-auto bg-black/60 p-2 pt-[max(.5rem,env(safe-area-inset-top))] pb-[max(.5rem,env(safe-area-inset-bottom))] sm:items-center sm:p-4" wire:key="inventory-quick-stock-modal">
            <button type="button" class="absolute inset-0 cursor-default" wire:click="close" aria-label="Close Add Stock modal"></button>

            <div class="vx-quick-stock-modal relative z-10 flex max-h-[calc(100dvh-1rem)] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900 sm:max-h-[min(92dvh,860px)]">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-700 sm:px-5 sm:py-4">
                    <div class="min-w-0">
                        <div class="text-xs font-bold uppercase tracking-wide text-emerald-600">Add Stock</div>
                        <h2 class="mt-1 truncate text-base font-bold text-gray-950 dark:text-white sm:text-lg">{{ $productName }}</h2>
                    </div>
                    <button type="button" wire:click="close" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg p-0 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Close">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-4 sm:p-5">
                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Location</span>
                            <select wire:model="locationId" class="min-h-11 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <option value="">Choose location…</option>
                                @foreach($locations as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('locationId')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Quantity Counted / Added</span>
                            <input wire:model="quantity" type="number" min="0.01" step="0.01" inputmode="decimal" autofocus class="min-h-11 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                            @error('quantity')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                        </label>

                        <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 dark:border-sky-900 dark:bg-sky-950/30">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">Barcode</div>
                                    <div class="mt-0.5 break-all text-xs text-gray-500 dark:text-gray-400">
                                        @if($currentBarcode)
                                            Current: <span class="font-mono font-semibold">{{ $currentBarcode }}</span>
                                        @else
                                            No barcode attached yet.
                                        @endif
                                    </div>
                                </div>
                                <button type="button" wire:click="startBarcodeScan" class="inline-flex min-h-10 w-full shrink-0 items-center justify-center gap-2 rounded-lg bg-sky-600 px-3 text-xs font-bold text-white hover:bg-sky-700 sm:w-auto">
                                    <x-heroicon-o-qr-code class="h-4 w-4" />
                                    {{ $currentBarcode ? 'Replace Barcode' : 'Scan Barcode' }}
                                </button>
                            </div>

                            <div class="mt-3 flex min-w-0 flex-col gap-2 sm:flex-row">
                                <input wire:model="barcode" type="text" inputmode="numeric" autocomplete="off" placeholder="Scan or enter barcode" class="min-h-11 min-w-0 flex-1 rounded-lg border-gray-300 bg-white font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                                @if($barcode)
                                    <button type="button" wire:click="clearBarcode" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200 sm:w-auto">Clear</button>
                                @endif
                            </div>
                            @error('barcode')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                            @if($barcode && $barcode !== $currentBarcode)
                                <div class="mt-2 break-words text-xs font-semibold text-emerald-600">Scanned barcode {{ $barcode }} will be saved with this stock update.</div>
                            @endif
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Vendor <span class="font-normal text-gray-400">optional</span></span>
                                <select wire:model="vendorId" class="min-h-11 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                    <option value="">No vendor</option>
                                    @foreach($vendors as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Unit Cost ($) <span class="font-normal text-gray-400">optional</span></span>
                                <input wire:model="unitCost" type="number" min="0" step="0.01" inputmode="decimal" class="min-h-11 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                                @error('unitCost')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-gray-700 dark:text-gray-200">Note / Reason <span class="font-normal text-gray-400">optional</span></span>
                            <textarea wire:model="reason" rows="2" class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Restock, count correction, vendor shipment…"></textarea>
                            @error('reason')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                        </label>
                    </div>

                    <div class="vx-quick-stock-footer grid shrink-0 grid-cols-[minmax(0,1fr)_auto] gap-2 border-t border-gray-200 bg-white p-3 pb-[max(.75rem,env(safe-area-inset-bottom))] dark:border-gray-700 dark:bg-gray-900 sm:p-4">
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-11 min-w-0 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Add Stock</span>
                            <span wire:loading wire:target="save">Adding…</span>
                        </button>
                        <button type="button" wire:click="close" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

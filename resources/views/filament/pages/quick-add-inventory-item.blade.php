<x-filament-panels::page>
    <div
        class="mx-auto max-w-3xl space-y-3 pb-24 sm:space-y-5 sm:pb-0"
        data-vx-page="inventory-quick-add"
        x-data="{
            openScanner() { window.dispatchEvent(new CustomEvent('open-camera-scanner')); },
            useScan(event) {
                const value = event?.detail?.value;
                if (value) $wire.setScannedBarcode(value);
            }
        }"
        x-on:barcode-scanned.window="useScan($event)"
        x-on:quick-add-ready.window="setTimeout(() => document.querySelector('[data-quick-add-name]')?.focus(), 100)"
    >
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="p-4 sm:p-5">
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Quick Add</div>
                <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">Add an item without the long form</h2>
                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Enter what you know now. Name is the only product field you must have; barcode, SKU, cost, vendor, and starting stock can be added when available.</p>
            </div>
        </section>

        <section data-tour="quick-add-main" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="sm:col-span-2">
                    <span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Item name <span class="text-red-500">*</span></span>
                    <input data-quick-add-name wire:model="data.name" type="text" autocomplete="off" autofocus placeholder="e.g. 2024 Topps Chrome Hobby Box" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />
                    @error('data.name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label class="sm:col-span-2">
                    <span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Barcode / UPC <span class="font-normal text-gray-400">optional</span></span>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                        <input wire:model="data.barcode" type="text" inputmode="numeric" autocomplete="off" placeholder="Scan or type barcode" class="min-h-11 min-w-0 rounded-lg border-gray-300 font-mono text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />
                        <button id="quickadd-scan-btn" type="button" @click="openScanner()" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white"><x-heroicon-o-camera class="h-5 w-5" /> Scan</button>
                    </div>
                    @error('data.barcode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">SKU <span class="font-normal text-gray-400">optional</span></span>
                    <input wire:model="data.sku" type="text" autocomplete="off" placeholder="Internal SKU" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />
                    @error('data.sku')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </label>

                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Category <span class="font-normal text-gray-400">optional</span></span>
                    <input wire:model="data.category" type="text" placeholder="e.g. Pokémon Sealed" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" list="inventory-category-list" />
                    <datalist id="inventory-category-list">@foreach(\App\Models\InventoryItem::whereNotNull('category')->distinct()->orderBy('category')->pluck('category') as $category)<option value="{{ $category }}"></option>@endforeach</datalist>
                </label>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
            <div class="mb-3">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Starting stock</h3>
                <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">Skip this when you only need the catalog item. For vendor pallets, use Receive Shipment so costs and paperwork stay with the delivery.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Location</span>
                    <select wire:model="data.location_id" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm"><option value="">No starting stock</option>@foreach(\App\Models\InventoryLocation::activeOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Quantity</span>
                    <input wire:model="data.quantity" type="number" step="0.01" min="0" inputmode="decimal" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Unit cost <span class="font-normal text-gray-400">optional</span></span>
                    <input wire:model="data.unit_cost" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0.00" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" />
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Vendor <span class="font-normal text-gray-400">optional</span></span>
                    <select wire:model="data.preferred_vendor_id" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm"><option value="">No preferred vendor</option>@foreach(\App\Models\Vendor::activeOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </label>
            </div>
        </section>

        <details class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-semibold text-gray-800 dark:text-gray-100 sm:px-5"><span>More cost details</span><x-heroicon-m-chevron-down class="h-4 w-4 text-gray-400" /></summary>
            <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                <label><span class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Cost for this starting stock</span><input wire:model="data.cost" type="number" step="0.01" min="0" inputmode="decimal" placeholder="Leave blank to use unit cost" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" /><p class="mt-1 text-[10px] text-gray-500 sm:text-xs">Only use this when the opening stock came in at a different cost from the normal unit cost.</p></label>
            </div>
        </details>

        <div class="hidden items-center justify-end gap-2 sm:flex">
            <button type="button" wire:click="resetQuickAdd" class="min-h-11 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Clear</button>
            <button type="button" wire:click="submit(true)" wire:loading.attr="disabled" class="min-h-11 rounded-lg border border-primary-300 px-4 text-sm font-semibold text-primary-700 disabled:opacity-60 dark:border-primary-800 dark:text-primary-300">Save & Add Another</button>
            <button type="button" wire:click="submit(false)" wire:loading.attr="disabled" class="min-h-11 rounded-lg bg-primary-600 px-5 text-sm font-semibold text-white disabled:opacity-60">Save Item</button>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden" data-vx-mobile-actions><div class="flex gap-2"><button type="button" @click="openScanner()" class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border border-primary-300 bg-white px-3 text-sm font-semibold text-primary-700 dark:border-primary-800 dark:bg-gray-800 dark:text-primary-300"><x-heroicon-o-camera class="h-5 w-5" /> Scan</button><button type="button" wire:click="submit(false)" wire:loading.attr="disabled" class="inline-flex min-h-11 flex-[1.35] items-center justify-center rounded-lg bg-primary-600 px-3 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove>Save Item</span><span wire:loading>Saving…</span></button></div></div>
    </div>
</x-filament-panels::page>

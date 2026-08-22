<x-filament-panels::page>
    <div
        class="mx-auto max-w-4xl space-y-3 pb-24 sm:space-y-5 sm:pb-0"
        data-vx-page="inventory-scanner"
        x-data="{
            openScanner() { window.dispatchEvent(new CustomEvent('open-camera-scanner')); },
            useScan(event) {
                const value = event?.detail?.value;
                if (!value) return;
                $wire.set('scanInput', value).then(() => $wire.submitScan());
            }
        }"
        x-on:barcode-scanned.window="useScan($event)"
    >
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="p-4 sm:p-5">
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Quick Scan</div>
                <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">Scan an item, then decide what you need</h2>
                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Lookup is read-only. Add Stock increases inventory in the location you choose. Vendor deliveries should be received from the pallet instead.</p>
            </div>
            <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800">
                <button type="button" wire:click="switchMode('lookup')" class="min-h-12 bg-white px-3 py-3 text-sm font-semibold dark:bg-gray-900 {{ $mode === 'lookup' ? 'text-primary-600 shadow-[inset_0_-2px_0_currentColor]' : 'text-gray-500 dark:text-gray-400' }}"><x-heroicon-o-magnifying-glass class="mr-1 inline h-4 w-4" /> Look Up</button>
                <button type="button" wire:click="switchMode('quickadd')" class="min-h-12 bg-white px-3 py-3 text-sm font-semibold dark:bg-gray-900 {{ $mode === 'quickadd' ? 'text-emerald-600 shadow-[inset_0_-2px_0_currentColor]' : 'text-gray-500 dark:text-gray-400' }}"><x-heroicon-o-plus-circle class="mr-1 inline h-4 w-4" /> Add Stock</button>
            </div>
        </section>

        @if($mode === 'quickadd')
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_130px]">
                    <label><span class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-200">Destination location</span><select wire:model="qaLocationId" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm">@foreach($this->locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    <label><span class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-200">Quantity</span><input wire:model="qaQty" type="number" step="0.01" min="0.01" inputmode="decimal" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" /></label>
                </div>
                <label class="mt-3 block"><span class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-200">Name only if this is a brand-new barcode</span><input wire:model="qaName" type="text" autocomplete="off" placeholder="Leave blank for an item already in inventory" class="min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" /><p class="mt-1 text-[10px] leading-4 text-gray-500 sm:text-xs">If the barcode is unknown, VortexOps can create the product using this name and add the scanned quantity.</p></label>
            </section>
        @endif

        <section data-tour="scanner-input" class="rounded-xl border-2 {{ $mode === 'lookup' ? 'border-primary-300 bg-primary-50/40 dark:border-primary-800 dark:bg-primary-950/20' : 'border-emerald-300 bg-emerald-50/40 dark:border-emerald-800 dark:bg-emerald-950/20' }} p-4 sm:rounded-2xl sm:p-5">
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-200 sm:text-sm">Barcode, UPC, or SKU</label>
            <div class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                <input wire:model.live.debounce.300ms="scanInput" wire:keydown.enter="submitScan" type="text" inputmode="text" autocomplete="off" autocapitalize="none" autofocus placeholder="Scan or type a code…" class="min-h-12 min-w-0 rounded-lg border-gray-300 bg-white px-3 font-mono text-base dark:border-gray-600 dark:bg-gray-900" />
                <button data-camera-scan type="button" @click="openScanner()" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"><x-heroicon-o-camera class="h-5 w-5" /> Camera</button>
                <button type="button" wire:click="submitScan" wire:loading.attr="disabled" class="inline-flex min-h-12 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white disabled:opacity-60 {{ $mode === 'lookup' ? 'bg-primary-600' : 'bg-emerald-600' }}"><span wire:loading.remove>{{ $mode === 'lookup' ? 'Look Up' : 'Add Stock' }}</span><span wire:loading>Working…</span></button>
            </div>
            <p class="mt-2 text-[10px] leading-4 text-gray-500 sm:text-xs">USB/Bluetooth scanner: scan directly into this field. Camera: tap Camera and center the barcode in the scan box.</p>
        </section>

        @if($errorMessage)<section class="rounded-xl border border-red-200 bg-red-50 p-3.5 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200"><strong>Not found.</strong> {{ $errorMessage }}</section>@endif

        @if($qaFlash)
            <section class="rounded-xl border p-3.5 sm:p-4 {{ isset($qaFlash['error']) ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200' : 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200' }}">
                @if(isset($qaFlash['error']))<div class="text-sm font-semibold">{{ $qaFlash['error'] }}</div>@else<div class="flex items-start gap-2.5"><x-heroicon-o-check-circle class="mt-0.5 h-5 w-5 shrink-0" /><div><div class="text-sm font-semibold">{{ $qaFlash['created'] ? 'Created and stocked' : 'Stock added' }}: {{ $qaFlash['name'] }}</div><div class="mt-0.5 text-xs">+{{ number_format((float)$qaFlash['qty']) }} at {{ $qaFlash['location'] }}</div></div></div>@endif
            </section>
        @endif

        @if($mode === 'lookup' && $result)
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><div class="text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $result['name'] }}</div><div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs"><span>SKU {{ $result['sku'] ?: '—' }}</span>@if($result['barcode'])<span>UPC {{ $result['barcode'] }}</span>@endif</div></div>
                        <span class="rounded-full px-2 py-1 text-[10px] font-semibold sm:text-xs {{ $result['is_low'] ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200' : 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' }}">{{ $result['is_low'] ? 'Low stock' : 'In stock' }}</span>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 sm:gap-3">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">On hand</div><div class="mt-0.5 text-xl font-semibold">{{ number_format((float)$result['total_qty']) }}</div></div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Avg cost</div><div class="mt-0.5 truncate text-base font-semibold sm:text-xl">${{ number_format((float)$result['avg_cost'], 2) }}</div></div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Value</div><div class="mt-0.5 truncate text-base font-semibold sm:text-xl">${{ number_format((float)$result['inventory_value'], 2) }}</div></div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $result['id']]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">View Item</a>
                        <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('stock', ['record' => $result['id']]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-violet-600 px-3 text-sm font-semibold text-white">Move / Correct Stock</a>
                    </div>
                </div>
                <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                    <h3 class="text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Stock by location</h3>
                    <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">@forelse($result['stock'] as $stock)<div class="flex items-center justify-between py-2.5 text-sm"><span class="text-gray-600 dark:text-gray-300">{{ $stock['location'] }}</span><span class="font-semibold text-gray-950 dark:text-white">{{ number_format((float)$stock['qty']) }}</span></div>@empty<div class="py-4 text-xs text-gray-500">No stock currently recorded.</div>@endforelse</div>
                </div>
                @if($costWarnings)<div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">@foreach($costWarnings as $warning)<div class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 last:mb-0 dark:bg-amber-950/30 dark:text-amber-200"><strong>{{ $warning['title'] }}:</strong> {{ $warning['message'] }}</div>@endforeach</div>@endif
            </section>
        @endif

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden" data-vx-mobile-actions><button data-camera-scan type="button" @click="openScanner()" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg {{ $mode === 'lookup' ? 'bg-primary-600' : 'bg-emerald-600' }} px-4 text-sm font-semibold text-white"><x-heroicon-o-camera class="h-5 w-5" /> {{ $mode === 'lookup' ? 'Scan Item' : 'Scan & Add Stock' }}</button></div>
    </div>
</x-filament-panels::page>

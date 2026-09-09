<div
    x-data
    x-on:inventory-quick-stock.window="$wire.openForProduct($event.detail.productId)"
    x-on:keydown.escape.window="if ($wire.open) $wire.close()"
>
    @if($open)
        <div class="fixed inset-0 z-[9999] flex items-end justify-center bg-black/60 sm:items-center sm:p-4" wire:key="inventory-quick-stock-modal">
            <button type="button" class="absolute inset-0 cursor-default" wire:click="close" aria-label="Close Add Stock modal"></button>

            <div class="relative z-10 w-full max-w-lg rounded-t-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-emerald-600">Add Stock</div>
                        <h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $productName }}</h2>
                    </div>
                    <button type="button" wire:click="close" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" aria-label="Close">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4 p-5">
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

                    <div class="flex gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Add Stock</span>
                            <span wire:loading wire:target="save">Adding…</span>
                        </button>
                        <button type="button" wire:click="close" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

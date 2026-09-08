<x-filament-panels::page>
    <div class="space-y-4" x-data x-on:barcode-scanned.window="$wire.incrementScan($event.detail.value)">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid gap-3 lg:grid-cols-[minmax(14rem,22rem)_minmax(0,1fr)_auto] lg:items-end">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Count location</span>
                    <select wire:model.live="locationId" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                        @foreach($this->locations as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Find item</span>
                    <input wire:model.live.debounce.250ms="search" type="search" placeholder="Name, SKU, UPC or barcode…" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-950" />
                </label>

                <button
                    type="button"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-camera-scanner', { detail: { title: 'Scan inventory item', helper: 'Each scan adds one to the physical count. Scan the same barcode repeatedly for multiple units.' } }))"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-500"
                >
                    Scan +1
                </button>
            </div>

            <div class="mt-3 rounded-xl bg-primary-50 px-3 py-2 text-sm text-primary-800 dark:bg-primary-950/30 dark:text-primary-200">
                <strong>Fast count:</strong> choose the location, then either type the physical count in the Counted column or scan a product barcode. Every barcode scan adds <strong>1</strong>. Save All Changes when the shelf/location is finished.
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950/60">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Item</th>
                            <th class="px-4 py-3 text-left font-semibold">SKU / Barcode</th>
                            <th class="px-4 py-3 text-right font-semibold">System</th>
                            <th class="px-4 py-3 text-right font-semibold">Counted</th>
                            <th class="px-4 py-3 text-right font-semibold">Difference</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->items as $item)
                            @php
                                $system = (float) ($systemCounts[$item->id] ?? 0);
                                $counted = (float) ($counts[$item->id] ?? 0);
                                $diff = $counted - $system;
                            @endphp
                            <tr wire:key="count-row-{{ $item->id }}" class="align-middle">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-950 dark:text-white">{{ $item->name }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    <div>{{ $item->sku ?: 'No SKU' }}</div>
                                    <div class="font-mono">{{ $item->barcode ?: $item->upc ?: 'No barcode' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format($system) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        wire:model.live="counts.{{ $item->id }}"
                                        type="number"
                                        min="0"
                                        step="1"
                                        inputmode="numeric"
                                        class="ml-auto w-24 rounded-lg border-gray-300 text-right font-semibold dark:border-gray-700 dark:bg-gray-950"
                                    />
                                </td>
                                <td class="px-4 py-3 text-right font-semibold {{ $diff === 0.0 ? 'text-gray-400' : ($diff > 0 ? 'text-emerald-600' : 'text-rose-600') }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="saveCount({{ $item->id }})" type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Save</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">No items match this search.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
                <div class="text-xs text-gray-500">Showing up to 500 active items. Search narrows the list instantly.</div>
                <button wire:click="saveAll" type="button" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-600 px-5 text-sm font-semibold text-white hover:bg-primary-500">Save All Changes</button>
            </div>
        </div>
    </div>
</x-filament-panels::page>

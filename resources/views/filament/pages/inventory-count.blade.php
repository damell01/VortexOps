<x-filament-panels::page>
    <div
        class="space-y-4"
        x-data
        x-on:barcode-scanned.window="$wire.scan($event.detail.value)"
        x-on:inventory-count-focus.window="setTimeout(() => { const el = document.getElementById('count-' + $event.detail.itemId); if (el) { el.focus(); el.select(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } }, 120)"
    >
        @php($progress = $this->progress)

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="grid min-w-0 flex-1 gap-3 md:grid-cols-2 xl:grid-cols-[minmax(14rem,20rem)_minmax(16rem,1fr)_minmax(11rem,14rem)_minmax(11rem,14rem)]">
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
                        <div class="flex gap-2">
                            <input wire:model.live.debounce.250ms="search" type="search" placeholder="Name, SKU, UPC or barcode…" class="min-w-0 flex-1 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-950" />
                            @if($search !== '')
                                <button wire:click="clearSearch" type="button" class="rounded-xl border border-gray-300 px-3 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Clear</button>
                            @endif
                        </div>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Count status</span>
                        <select wire:model.live="countFilter" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                            <option value="all">All items</option>
                            <option value="uncounted">Not counted yet</option>
                            <option value="counted">Counted</option>
                            <option value="changed">Different from system</option>
                            <option value="matching">Matches system</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Category</span>
                        <select wire:model.live="categoryFilter" class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                            <option value="">All categories</option>
                            @foreach($this->categories as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <button
                    type="button"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-camera-scanner', { detail: { title: 'Scan inventory item', helper: 'Scan the box to find it, then enter the total quantity you physically counted.' } }))"
                    class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 px-5 text-sm font-semibold text-white hover:bg-primary-500"
                >
                    Scan Item
                </button>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-950/60">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Items Counted</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($progress['counted']) }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-950/60">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Remaining</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($progress['remaining']) }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-950/60">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Differences</div>
                    <div class="mt-1 text-2xl font-bold {{ $progress['changed'] > 0 ? 'text-amber-600' : 'text-gray-950 dark:text-white' }}">{{ number_format($progress['changed']) }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-950/60">
                    <div class="flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <span>Progress</span><span>{{ $progress['percent'] }}%</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-primary-600" style="width: {{ min(100, max(0, $progress['percent'])) }}%"></div>
                    </div>
                </div>
            </div>

            <div class="mt-3 rounded-xl bg-primary-50 px-3 py-2 text-sm text-primary-800 dark:bg-primary-950/30 dark:text-primary-200">
                <strong>How to count:</strong> scan a box to jump straight to that product, or search/filter the list. Then enter the <strong>total physical quantity</strong> you counted. A scan never assumes the quantity is 1.
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="max-h-[68vh] overflow-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="sticky top-0 z-10 bg-gray-50/95 backdrop-blur dark:bg-gray-950/95">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Item</th>
                            <th class="px-4 py-3 text-left font-semibold">SKU / Barcode</th>
                            <th class="px-4 py-3 text-right font-semibold">System Qty</th>
                            <th class="px-4 py-3 text-right font-semibold">Physical Count</th>
                            <th class="px-4 py-3 text-right font-semibold">Difference</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->items as $item)
                            @php
                                $system = (float) ($systemCounts[$item->id] ?? 0);
                                $hasCount = array_key_exists($item->id, $counts) && $counts[$item->id] !== '' && $counts[$item->id] !== null;
                                $counted = $hasCount ? (float) $counts[$item->id] : null;
                                $diff = $hasCount ? $counted - $system : null;
                                $selected = $selectedItemId === $item->id;
                            @endphp
                            <tr wire:key="count-row-{{ $item->id }}" class="align-middle {{ $selected ? 'bg-primary-50/70 dark:bg-primary-950/20' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-950 dark:text-white">{{ $item->name }}</div>
                                    @if($item->category)
                                        <div class="mt-0.5 text-xs text-gray-500">{{ $item->category }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    <div>{{ $item->sku ?: 'No SKU' }}</div>
                                    <div class="font-mono">{{ $item->barcode ?: $item->upc ?: 'No barcode' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">{{ number_format($system) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input
                                        id="count-{{ $item->id }}"
                                        wire:model.live.debounce.300ms="counts.{{ $item->id }}"
                                        type="number"
                                        min="0"
                                        step="1"
                                        inputmode="numeric"
                                        placeholder="Count"
                                        class="ml-auto w-28 rounded-lg border-gray-300 text-right text-base font-bold dark:border-gray-700 dark:bg-gray-950"
                                    />
                                </td>
                                <td class="px-4 py-3 text-right font-semibold {{ $diff === null ? 'text-gray-400' : ($diff === 0.0 ? 'text-emerald-600' : 'text-amber-600') }}">
                                    @if($diff === null)
                                        —
                                    @elseif($diff === 0.0)
                                        Match
                                    @else
                                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        @if($hasCount)
                                            <button wire:click="clearCount({{ $item->id }})" type="button" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Clear</button>
                                            <button wire:click="saveCount({{ $item->id }})" type="button" class="rounded-lg border border-primary-300 bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950/30 dark:text-primary-300">Save</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">No inventory items match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="sticky bottom-0 flex flex-col gap-3 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900/95">
                <div class="text-xs text-gray-500">Only quantities you actually enter are treated as counted. Use Tab to move through the quantity cells quickly.</div>
                <button wire:click="saveAll" type="button" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-primary-600 px-5 text-sm font-semibold text-white hover:bg-primary-500">Save All Counted Items</button>
            </div>
        </div>
    </div>
</x-filament-panels::page>

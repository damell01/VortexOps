<x-filament-panels::page>
@php($totals = $this->totals)

<div class="space-y-4">

    {{-- What this pallet came to, so the table below has something to be
         checked against. --}}
    <div class="flex flex-wrap gap-6 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Lines</dt>
            <dd class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $totals['lines'] }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Units received</dt>
            <dd class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($totals['units']) }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Goods cost</dt>
            <dd class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">${{ number_format($totals['cost'], 2) }}</dd>
        </div>
        @if ($totals['outstanding'] > 0)
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Still outstanding</dt>
                <dd class="text-xl font-semibold tabular-nums text-amber-600 dark:text-amber-400">{{ $totals['outstanding'] }} {{ Str::plural('line', $totals['outstanding']) }}</dd>
            </div>
        @endif
    </div>

    {{-- A <table min-w-[64rem]> here forced horizontal scrolling on any
         phone — the same bug the manifest-entry page had. Rows instead of
         columns, same fix. --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($rows as $row)
            @if ($editingLineId === $row['line_id'])
                {{-- The row becomes the form. Editing in a dialog would hide
                     the rows either side, which are the thing you are
                     checking this one against. --}}
                <div wire:key="edit-{{ $row['line_id'] }}" class="bg-violet-50/60 p-4 dark:bg-violet-950/30">
                    <div class="mb-2 text-xs font-mono text-gray-400 tabular-nums">#{{ $row['line_number'] }}</div>

                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Item name</span>
                            <input type="text" wire:model="draft.name"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-sm" />
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Unit cost</span>
                            <input type="number" step="0.01" min="0" wire:model="draft.unit_cost"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-sm tabular-nums" />
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Units in stock here</span>
                            <input type="number" step="0.01" min="0" wire:model="draft.units"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-sm tabular-nums" />
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Location</span>
                            <select wire:model="draft.location_id"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm">
                                @foreach ($this->locationOptions as $locId => $locName)
                                    <option value="{{ $locId }}">{{ $locName }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="mt-3 block">
                        <span class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                            Why (used only if the count or location changes)
                        </span>
                        <input type="text" wire:model="draft.reason" placeholder="e.g. two boxes were crushed"
                            class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-sm" />
                    </label>

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Cost and name are corrections to what was recorded, so they replace it.
                        Changing the count or the location moves stock, so those are written to
                        this item’s history as an adjustment and a transfer — the totals stay
                        the sum of what moved.
                    </p>

                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="save" wire:loading.attr="disabled"
                            class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save changes</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                        <button type="button" wire:click="cancelEdit"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                            Cancel
                        </button>
                    </div>
                </div>
            @else
                <div wire:key="row-{{ $row['line_id'] }}" class="flex items-start gap-3 p-4">
                    <span class="mt-0.5 flex-shrink-0 text-xs font-mono text-gray-400 tabular-nums">{{ $row['line_number'] }}</span>

                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $row['name'] }}</p>
                        <p class="text-xs text-gray-400 truncate">
                            {{ $row['sku'] ?: 'No SKU' }}
                            @if ($row['barcode']) · {{ $row['barcode'] }} @endif
                            @if ($row['staged_as'] !== $row['name'])
                                · staged as “{{ $row['staged_as'] }}”
                            @endif
                        </p>

                        <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-4">
                            <div>
                                <span class="text-gray-400">Received </span>
                                <span class="tabular-nums {{ $row['complete'] ? 'text-gray-700 dark:text-gray-300' : 'text-amber-600 dark:text-amber-400 font-medium' }}">{{ $row['received_cases'] }}</span>
                                <span class="text-gray-400">/ {{ $row['expected_cases'] }}</span>
                            </div>
                            <div><span class="text-gray-400">In stock </span><span class="tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['in_stock']) }}</span></div>
                            <div><span class="text-gray-400">Unit cost </span><span class="tabular-nums text-gray-700 dark:text-gray-300">${{ number_format($row['unit_cost'], 2) }}</span></div>
                            <div><span class="text-gray-400">Location </span><span class="text-gray-700 dark:text-gray-300">{{ $row['location'] ?? '—' }}</span></div>
                        </div>
                    </div>

                    <div class="flex flex-shrink-0 flex-col items-end gap-2">
                        <span class="text-sm font-medium tabular-nums text-gray-500 dark:text-gray-400">
                            {{ $row['line_total'] > 0 ? '$' . number_format($row['line_total'], 2) : '—' }}
                        </span>
                        @if ($row['linked'])
                            <button type="button" wire:click="edit({{ $row['line_id'] }})"
                                class="rounded-md border border-gray-300 dark:border-gray-600 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                Fix
                            </button>
                        @else
                            <span class="text-xs text-gray-400 text-right" title="Scan it in on the pallet first">Not in inventory</span>
                        @endif
                    </div>
                </div>
            @endif
        @empty
            <div class="px-3 py-10 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Nothing has been received from this pallet yet.</p>
                <p class="mt-1 text-xs text-gray-400">Items appear here as they are scanned in.</p>
            </div>
        @endforelse
    </div>
</div>
</x-filament-panels::page>

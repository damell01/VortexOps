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

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-x-auto">
        {{-- table-fixed: on auto layout the browser hands the slack to
             whichever column it likes and headers stop sitting over their own
             cells. --}}
        <table class="w-full min-w-[64rem] text-sm table-fixed">
            <colgroup>
                <col class="w-[4%]" /><col class="w-[26%]" /><col class="w-[13%]" />
                <col class="w-[11%]" /><col class="w-[11%]" /><col class="w-[16%]" />
                <col class="w-[10%]" /><col class="w-[9%]" />
            </colgroup>
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Item</th>
                    <th class="px-3 py-2 text-right">Received</th>
                    <th class="px-3 py-2 text-right">In stock</th>
                    <th class="px-3 py-2 text-right">Unit cost</th>
                    <th class="px-3 py-2">Location</th>
                    <th class="px-3 py-2 text-right">Line total</th>
                    <th class="px-3 py-2 text-right"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @if ($editingLineId === $row['line_id'])
                        {{-- The row becomes the form. Editing in a dialog would
                             hide the rows either side, which are the thing you
                             are checking this one against. --}}
                        <tr wire:key="edit-{{ $row['line_id'] }}" class="bg-violet-50/60 dark:bg-violet-950/30">
                            <td class="px-3 py-3 align-top text-gray-400 tabular-nums">{{ $row['line_number'] }}</td>
                            <td class="px-3 py-3 align-top" colspan="7">
                                <div class="grid gap-3 md:grid-cols-4">
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
                            </td>
                        </tr>
                    @else
                        <tr wire:key="row-{{ $row['line_id'] }}" class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="px-3 py-2.5 text-gray-400 tabular-nums">{{ $row['line_number'] }}</td>
                            <td class="px-3 py-2.5">
                                <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $row['name'] }}</p>
                                <p class="text-xs text-gray-400 truncate">
                                    {{ $row['sku'] ?: 'No SKU' }}
                                    @if ($row['barcode']) · {{ $row['barcode'] }} @endif
                                    @if ($row['staged_as'] !== $row['name'])
                                        · staged as “{{ $row['staged_as'] }}”
                                    @endif
                                </p>
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                <span class="{{ $row['complete'] ? 'text-gray-900 dark:text-gray-100' : 'text-amber-600 dark:text-amber-400 font-medium' }}">
                                    {{ $row['received_cases'] }}
                                </span>
                                <span class="text-gray-400">/ {{ $row['expected_cases'] }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{ number_format($row['in_stock']) }}
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                ${{ number_format($row['unit_cost'], 2) }}
                            </td>
                            <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300 truncate">{{ $row['location'] ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $row['line_total'] > 0 ? '$' . number_format($row['line_total'], 2) : '—' }}
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                @if ($row['linked'])
                                    <button type="button" wire:click="edit({{ $row['line_id'] }})"
                                        class="rounded-md border border-gray-300 dark:border-gray-600 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                        Fix
                                    </button>
                                @else
                                    <span class="text-xs text-gray-400" title="Scan it in on the pallet first">Not in inventory</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-10 text-center">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nothing has been received from this pallet yet.</p>
                            <p class="mt-1 text-xs text-gray-400">Items appear here as they are scanned in.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</x-filament-panels::page>

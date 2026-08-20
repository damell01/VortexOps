<x-filament-panels::page>
@php($totals = $this->totals)

<div class="space-y-4">

    {{-- Batch settings + running totals. Asked once for the delivery rather
         than once per line: a pallet lands in one place, and answering the same
         question twelve times is how people stop answering it. --}}
    <div class="flex flex-wrap items-end justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
        <div class="min-w-[16rem]">
            <label for="vx-location" class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                Receive everything into
            </label>
            <select id="vx-location" wire:model="locationId"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                <option value="">Decide when it lands</option>
                @foreach(\App\Models\InventoryLocation::activeOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <dl class="flex gap-6 text-sm">
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Lines</dt>
                <dd class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $totals['lines'] }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Units</dt>
                <dd class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format($totals['units'], 2), '0'), '.') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Est. cost</dt>
                <dd class="text-xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">${{ number_format($totals['cost'], 2) }}</dd>
            </div>
        </dl>
    </div>

    {{-- The table. Wide content scrolls inside its own container so the page
         body never scrolls sideways on a phone. --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-x-auto">
        <table class="w-full min-w-[66rem] text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                    <th class="w-10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">#</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Item <span class="text-danger-600">*</span></th>
                    <th class="w-52 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Already stock this?</th>
                    <th class="w-36 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Case or single</th>
                    <th class="w-24 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cases</th>
                    <th class="w-28 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Units / case</th>
                    <th class="w-28 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Unit cost</th>
                    <th class="w-28 px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Line total</th>
                    <th class="w-12 px-3 py-2"></th>
                </tr>
            </thead>
            <tbody id="vx-lines">
                @foreach($rows as $i => $row)
                    @php($lineTotal = (float) ($row['case_count'] ?: 0) * (float) ($row['quantity_per_case'] ?: 0) * (float) ($row['unit_cost'] ?: 0))
                    <tr wire:key="line-{{ $i }}" class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                        <td class="px-3 py-1.5 text-gray-400 tabular-nums">{{ $i + 1 }}</td>

                        <td class="px-3 py-1.5">
                            <input type="text" wire:model.blur="rows.{{ $i }}.description"
                                data-vx-line-input
                                placeholder="e.g. 2026 Topps Chrome Hobby"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2.5 py-1.5 text-sm text-gray-900 dark:text-gray-100" />
                        </td>

                        <td class="px-3 py-1.5">
                            <select wire:model="rows.{{ $i }}.inventory_item_id"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm text-gray-900 dark:text-gray-100">
                                <option value="">Something new</option>
                                @foreach($this->itemOptions as $itemId => $itemName)
                                    <option value="{{ $itemId }}">{{ $itemName }}</option>
                                @endforeach
                            </select>
                        </td>

                        <td class="px-3 py-1.5">
                            <select wire:model="rows.{{ $i }}.is_container"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm text-gray-900 dark:text-gray-100">
                                <option value="">Not sure</option>
                                <option value="1">Case / box</option>
                                <option value="0">Single item</option>
                            </select>
                        </td>

                        <td class="px-3 py-1.5">
                            <input type="number" min="1" step="1" wire:model.blur="rows.{{ $i }}.case_count"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm tabular-nums text-gray-900 dark:text-gray-100" />
                        </td>

                        <td class="px-3 py-1.5">
                            <input type="number" min="0.01" step="any" wire:model.blur="rows.{{ $i }}.quantity_per_case"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm tabular-nums text-gray-900 dark:text-gray-100" />
                        </td>

                        <td class="px-3 py-1.5">
                            <input type="number" min="0" step="0.01" placeholder="—" wire:model.blur="rows.{{ $i }}.unit_cost"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm tabular-nums text-gray-900 dark:text-gray-100" />
                        </td>

                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500 dark:text-gray-400">
                            {{ $lineTotal > 0 ? '$' . number_format($lineTotal, 2) : '—' }}
                        </td>

                        <td class="px-3 py-1.5 text-right">
                            <button type="button" wire:click="removeRow({{ $i }})" title="Remove line"
                                class="rounded-md p-1.5 text-gray-400 hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-950">
                                <x-heroicon-o-trash class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="border-t border-gray-200 dark:border-gray-700 px-3 py-2">
            <button type="button" wire:click="addRow"
                class="w-full rounded-lg border border-dashed border-gray-300 dark:border-gray-600 py-2 text-sm font-medium text-gray-500 hover:border-violet-400 hover:text-violet-600 dark:text-gray-400 dark:hover:text-violet-400 transition">
                + Add another line
            </button>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            <strong>Enter</strong> adds a row · <strong>Tab</strong> moves across · empty rows are ignored
        </p>
        <div class="flex gap-2">
            <a href="{{ \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]) }}"
                class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                Cancel
            </a>
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-violet-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Add {{ $totals['lines'] }} {{ Str::plural('line', $totals['lines']) }} to pallet</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>

</div>

<script>
(function () {
    const table = document.getElementById('vx-lines');
    if (! table || table.dataset.bound) return;
    table.dataset.bound = '1';

    // Enter on the last row's item field adds a row and moves into it, so a
    // slip can be typed straight down without reaching for the mouse. On any
    // other row it just moves to the next one, which is what a spreadsheet does.
    table.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;

        const inputs = [...table.querySelectorAll('[data-vx-line-input]')];
        const index = inputs.indexOf(e.target);
        if (index === -1) return;

        e.preventDefault();

        if (index < inputs.length - 1) {
            inputs[index + 1].focus();
            return;
        }

        // Last row: let Livewire append, then focus what it rendered.
        window.Livewire.find(
            e.target.closest('[wire\\:id]').getAttribute('wire:id')
        ).call('addRow').then(() => {
            const refreshed = table.querySelectorAll('[data-vx-line-input]');
            refreshed[refreshed.length - 1]?.focus();
        });
    });
})();
</script>
</x-filament-panels::page>

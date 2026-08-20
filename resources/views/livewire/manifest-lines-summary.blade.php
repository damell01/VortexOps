<div class="space-y-3">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $total }} {{ Str::plural('line', $total) }} on this pallet
        </p>
        <a href="{{ \App\Filament\Resources\PalletResource::getUrl('add-lines', ['record' => $pallet]) }}"
            class="flex-shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-violet-700 transition">
            {{ $total > 0 ? 'Edit manifest lines' : 'Add manifest lines' }}
        </a>
    </div>

    @if($total === 0)
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 px-5 py-8 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nothing listed yet. Lines are typed as a table — one row each, with running totals.
            </p>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                        <th class="w-10 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">#</th>
                        <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Item</th>
                        <th class="w-40 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Linked to</th>
                        <th class="w-28 px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Qty</th>
                        <th class="w-28 px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Unit cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $line)
                        @php($units = (float) $line->case_count * ($line->is_container ? (float) $line->quantity_per_case : 1))
                        <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <td class="px-3 py-2 text-gray-400 tabular-nums">{{ $line->line_number }}</td>
                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                                {{ $line->description }}
                                @if($line->is_container)
                                    <span class="ml-1 text-xs text-gray-400">
                                        {{ rtrim(rtrim(number_format($line->case_count, 2), '0'), '.') }} ×
                                        {{ rtrim(rtrim(number_format($line->quantity_per_case, 2), '0'), '.') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                {{ $line->inventoryItem?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                {{ rtrim(rtrim(number_format($units, 2), '0'), '.') }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $line->unit_cost === null ? '—' : '$' . number_format($line->unit_cost, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($this->totalPages() > 1)
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Page {{ $page }} of {{ $this->totalPages() }}
                </p>
                <div class="flex gap-2">
                    <button type="button" wire:click="previousPage" @disabled($page <= 1)
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 disabled:opacity-40">
                        Previous
                    </button>
                    <button type="button" wire:click="nextPage" @disabled($page >= $this->totalPages())
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 disabled:opacity-40">
                        Next
                    </button>
                </div>
            </div>
        @endif
    @endif

</div>

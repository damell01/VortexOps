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
        {{-- A <table min-w-[40rem]> here forced horizontal scrolling on any
             phone, the same bug as the manifest-entry page it links to. Rows
             instead of columns, same as that page's fix. --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($lines as $line)
                @php($units = (float) $line->case_count * ($line->is_container ? (float) $line->quantity_per_case : 1))
                <div class="flex items-start gap-3 px-3 py-2.5">
                    <span class="mt-0.5 flex-shrink-0 text-xs font-mono text-gray-400 tabular-nums">{{ $line->line_number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-900 dark:text-gray-100">
                            {{ $line->description }}
                            @if($line->is_container)
                                <span class="text-xs text-gray-400">
                                    {{ rtrim(rtrim(number_format($line->case_count, 2), '0'), '.') }} ×
                                    {{ rtrim(rtrim(number_format($line->quantity_per_case, 2), '0'), '.') }}
                                </span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $line->inventoryItem?->name ?? 'Not linked' }}
                        </p>
                    </div>
                    <div class="flex-shrink-0 text-right text-sm tabular-nums">
                        <div class="text-gray-900 dark:text-gray-100">{{ rtrim(rtrim(number_format($units, 2), '0'), '.') }} units</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $line->unit_cost === null ? '—' : '$' . number_format($line->unit_cost, 2) }}</div>
                    </div>
                </div>
            @endforeach
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

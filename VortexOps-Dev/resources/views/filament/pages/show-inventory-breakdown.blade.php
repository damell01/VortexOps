<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Show Header ──────────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 flex flex-wrap gap-6 items-start">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Show</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->title ?? 'Show #' . $this->record->id }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Date</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->show_date?->format('M j, Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Channel</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->channel?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Streamers</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->streamers->pluck('name')->join(', ') ?: '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Units Sold</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->record->units_sold ?? 0) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Revenue</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">${{ number_format((float) $this->record->gross_revenue, 2) }}</p>
            </div>
        </div>

        @if ($this->totalLines === 0)
            {{-- ── No deduction data yet ─────────────────────────────────────── --}}
            <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 px-8 py-12 text-center">
                <x-heroicon-o-clipboard-document-list class="mx-auto h-10 w-10 text-gray-400 mb-3" />
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">No inventory lines yet</p>
                <p class="text-xs text-gray-400 mt-1">Use "Map Items Manually" on the show to generate inventory deduction lines.</p>
            </div>
        @else
            {{-- ── Summary Stats ────────────────────────────────────────────── --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $this->totalLines }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Line Items</p>
                </div>
                <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $this->matched }}</p>
                    <p class="text-xs text-green-600 dark:text-green-500 mt-0.5">Matched to Inventory</p>
                </div>
                <div class="rounded-xl border px-4 py-3 text-center
                    {{ $this->unmatched > 0
                        ? 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950'
                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' }}">
                    <p class="text-2xl font-bold {{ $this->unmatched > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $this->unmatched }}</p>
                    <p class="text-xs mt-0.5 {{ $this->unmatched > 0 ? 'text-amber-600 dark:text-amber-500' : 'text-gray-500' }}">Unmatched</p>
                </div>
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 text-center">
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">${{ number_format($this->totalCogs, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Total COGS</p>
                </div>
                <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950 px-4 py-3 text-center">
                    <p class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ number_format($this->totalDeducted) }}</p>
                    <p class="text-xs text-blue-600 dark:text-blue-500 mt-0.5">Units Deducted</p>
                </div>
                <div class="rounded-xl border px-4 py-3 text-center
                    {{ $this->deductionStatus === 'processed'
                        ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950'
                        : 'border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950' }}">
                    @php
                        $drStatusLabel = match($this->deductionStatus) {
                            'pending'   => 'Pending',
                            'approved'  => 'Approved',
                            'processed' => 'Processed',
                            'rejected'  => 'Rejected',
                            default     => ucfirst($this->deductionStatus ?? 'None'),
                        };
                    @endphp
                    <p class="text-sm font-bold mt-1
                        {{ $this->deductionStatus === 'processed'
                            ? 'text-green-700 dark:text-green-400'
                            : 'text-yellow-700 dark:text-yellow-400' }}">
                        {{ $drStatusLabel }}
                    </p>
                    <p class="text-xs mt-0.5
                        {{ $this->deductionStatus === 'processed'
                            ? 'text-green-600 dark:text-green-500'
                            : 'text-yellow-600 dark:text-yellow-500' }}">
                        Deduction Status
                    </p>
                </div>
            </div>

            {{-- ── Breakdown Table ──────────────────────────────────────────── --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Sold Items vs Inventory</h2>
                    <button wire:click="refresh" type="button"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center gap-1">
                        <x-heroicon-o-arrow-path class="h-3.5 w-3.5" wire:loading.class="animate-spin" />
                        Refresh
                    </button>
                </div>

                {{-- Mobile: card view --}}
                <div class="divide-y divide-gray-100 dark:divide-gray-800 lg:hidden">
                    @foreach ($this->breakdown as $row)
                        <div class="px-5 py-4 space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    @if ($row['item_name'])
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $row['item_name'] }}</p>
                                        <p class="text-xs text-gray-400">{{ $row['raw_description'] }}</p>
                                    @else
                                        <p class="text-sm text-amber-700 dark:text-amber-400 italic">{{ $row['raw_description'] }}</p>
                                        <p class="text-xs text-amber-500">Unmatched — not in inventory catalogue</p>
                                    @endif
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <p class="text-gray-400">Approved</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $row['qty_approved'] }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-400">In Stock</p>
                                    <p class="font-medium {{ $row['current_stock'] !== null ? ($row['current_stock'] < $row['qty_approved'] ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200') : 'text-gray-400' }}">
                                        {{ $row['current_stock'] !== null ? number_format($row['current_stock']) : '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Deducted</p>
                                    <p class="font-medium {{ $row['qty_pending'] > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-700 dark:text-green-400' }}">
                                        {{ number_format($row['qty_deducted']) }}
                                        @if ($row['qty_pending'] > 0)
                                            <span class="text-gray-400">({{ number_format($row['qty_pending']) }} pending)</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-400">{{ $row['location'] }}</span>
                                <span class="font-medium text-gray-700 dark:text-gray-300">${{ number_format($row['line_total'], 2) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop: table view --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Sold As</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Inventory Item</th>
                                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Approved Qty</th>
                                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Current Stock</th>
                                <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Deducted</th>
                                <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">COGS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($this->breakdown as $i => $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 text-xs max-w-[180px]">
                                        <span class="truncate block" title="{{ $row['raw_description'] }}">{{ $row['raw_description'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 max-w-[200px]">
                                        @if ($row['item_name'])
                                            <p class="font-medium text-gray-900 dark:text-gray-100 truncate" title="{{ $row['item_name'] }}">{{ $row['item_name'] }}</p>
                                            @if ($row['item_sku'])
                                                <p class="text-xs text-gray-400 font-mono">{{ $row['item_sku'] }}</p>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 italic">
                                                <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5 flex-shrink-0" />
                                                Unmatched
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono tabular-nums text-sm text-gray-800 dark:text-gray-200">
                                        {{ number_format($row['qty_approved']) }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono text-sm">
                                        @if ($row['current_stock'] !== null)
                                            <span class="{{ $row['current_stock'] < $row['qty_approved'] ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-700 dark:text-gray-300' }}">
                                                {{ number_format($row['current_stock']) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono text-sm text-gray-700 dark:text-gray-300">
                                        {{ number_format($row['qty_deducted']) }}
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @if (! $row['item_id'])
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300">
                                                Unmatched
                                            </span>
                                        @elseif ($row['qty_approved'] == 0)
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                                Skipped
                                            </span>
                                        @elseif ($row['qty_pending'] == 0)
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                                                ✓ Deducted
                                            </span>
                                        @elseif ($row['qty_deducted'] > 0)
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                                Partial
                                            </span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-sm text-gray-700 dark:text-gray-300">
                                        ${{ number_format($row['line_total'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase">
                                    Totals — {{ $this->totalLines }} lines
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ number_format($this->totalQtyApproved) }}
                                </td>
                                <td class="px-3 py-3"></td>
                                <td class="px-3 py-3 text-right font-mono text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    {{ number_format($this->totalDeducted) }}
                                </td>
                                <td class="px-3 py-3"></td>
                                <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-gray-800 dark:text-gray-200">
                                    ${{ number_format($this->totalCogs, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>

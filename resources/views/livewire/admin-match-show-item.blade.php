<div class="mt-3" wire:key="match-show-line-{{ $line->id }}">
    @if($line->inventory_item_id)
        <div class="rounded-lg bg-green-50 px-3 py-2 text-xs text-green-700 dark:bg-green-950/30 dark:text-green-200">✓ Matched to {{ $line->inventoryItem?->name ?? 'inventory' }}</div>
    @else
        <button type="button" wire:click="toggle" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            <x-heroicon-m-link class="h-4 w-4" />
            {{ $open ? 'Close Matcher' : 'Match to Inventory' }}
        </button>

        @if($open)
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-900 dark:bg-amber-950/20">
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search product, SKU, barcode, brand…"
                    class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />

                <div class="mt-3 max-h-72 space-y-2 overflow-y-auto">
                    @forelse($results as $item)
                        <button type="button" wire:click="matchItem({{ $item->id }})"
                            class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-3 text-left hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $item->name }}</div>
                                <div class="mt-0.5 text-xs text-gray-500">SKU {{ $item->sku ?: '—' }} @if($item->barcode) · {{ $item->barcode }} @endif</div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ number_format((float)($item->stock_sum_quantity ?? 0)) }}</div>
                                <div class="text-[10px] text-gray-500">on hand</div>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 p-5 text-center text-xs text-gray-500 dark:border-gray-700">No catalog products match this search.</div>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
</div>

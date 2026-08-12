<div>
    @if ($isOpen && $show)
        <div class="fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="panel-title">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50" wire:click="closePanel()"></div>

            <!-- Panel -->
            <div class="absolute right-0 top-0 h-full w-full max-w-2xl bg-white dark:bg-gray-900 shadow-xl overflow-y-auto">
                <!-- Header -->
                <div class="sticky top-0 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 flex items-center justify-between z-10">
                    <div>
                        <h2 id="panel-title" class="text-lg font-semibold text-gray-900 dark:text-white">{{ $show->title }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $show->show_date?->format('M j, Y') }} • {{ $show->channel->name }}</p>
                    </div>
                    <button wire:click="closePanel()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 group relative" title="Close (Esc)">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span class="absolute bottom-full right-0 mb-2 px-2 py-1 text-xs bg-gray-900 text-white rounded whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-20">Close (Esc)</span>
                    </button>
                </div>

                <!-- Content -->
                <div class="px-6 py-6 space-y-6">
                    <!-- Show Stats -->
                    <div class="grid grid-cols-3 gap-4">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Gross Revenue</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($show->gross_revenue, 2) }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Items Sold</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $show->orders->count() }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium uppercase">Mapped</p>
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $show->orders->filter(fn($o) => $o->inventory_item_id)->count() }}</p>
                        </div>
                    </div>

                    <!-- Items to Map -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Items Sold</h3>
                        <div class="space-y-2">
                            @forelse ($orders as $order)
                                <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate">{{ $order->item_name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $order->quantity }} × ${{ number_format($order->total_price / $order->quantity, 2) }} = ${{ number_format($order->total_price, 2) }}</p>
                                        @if ($order->inventory_item_id)
                                            <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium mt-1">✓ {{ $order->inventoryItem?->name }}</p>
                                        @endif
                                    </div>
                                    @if (!$order->inventory_item_id)
                                        <button wire:click="openInventoryMapper({{ $order->id }})" class="ml-4 px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition whitespace-nowrap">
                                            Map
                                        </button>
                                    @else
                                        <span class="ml-4 text-emerald-600 dark:text-emerald-400 font-medium">✓</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-center text-gray-500 dark:text-gray-400 py-6">No items for this show</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Notes -->
                    @if ($logEntry)
                        <div>
                            <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Notes</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white" rows="3" placeholder="Add any notes..."></textarea>
                        </div>
                    @endif

                    <!-- Actions -->
                    <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button wire:click="closePanel()" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            Close
                        </button>
                        @if ($orders->filter(fn($o) => !$o->inventory_item_id)->isEmpty())
                            <button wire:click="markReadyForFulfillment" wire:loading.attr="disabled" class="flex-1 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg wire:loading.remove class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <svg wire:loading class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove>Ready for Fulfillment</span>
                                <span wire:loading>Marking...</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Inventory Mapper Modal -->
    <livewire:inventory-item-mapper />
</div>

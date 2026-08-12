<div>
    @if ($isOpen && $order)
        <div class="fixed inset-0 z-50 overflow-hidden flex items-center justify-center bg-black/50" role="dialog" aria-modal="true" aria-labelledby="mapper-title">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50" wire:click="closeMapper()"></div>

        <!-- Modal -->
        <div class="relative bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
                <!-- Header -->
                <div class="sticky top-0 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 flex items-center justify-between z-10">
                    <h3 id="mapper-title" class="text-lg font-semibold text-gray-900 dark:text-white">Map Inventory</h3>
                    <button wire:click="closeMapper()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 group relative" title="Close (Esc)">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span class="absolute bottom-full right-0 mb-2 px-2 py-1 text-xs bg-gray-900 text-white rounded whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity">Close (Esc)</span>
                    </button>
                </div>

                <!-- Content -->
                <div class="px-6 py-4 space-y-4">
                    <!-- Item being mapped -->
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Mapping item</p>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $order->item_name }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $order->quantity }} × ${{ number_format($order->total_price / $order->quantity, 2) }}</p>
                    </div>

                    <!-- Errors -->
                    @if ($errors->has('mapper'))
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                            <p class="text-sm text-red-700 dark:text-red-300">{{ $errors->first('mapper') }}</p>
                        </div>
                    @endif

                    <!-- Item Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Inventory Item</label>
                        <div class="relative">
                            <input
                                type="text"
                                wire:model.debounce="itemSearch"
                                placeholder="Search inventory items..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400"
                            />

                            <!-- Search Results Dropdown -->
                            @if (count($searchResults) > 0)
                                <div class="absolute z-10 top-full left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    @foreach ($searchResults as $id => $name)
                                        <button
                                            type="button"
                                            wire:click="$set('selectedItemId', {{ $id }}); $set('itemSearch', '{{ addslashes($name) }}')"
                                            class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-700 last:border-b-0 transition"
                                        >
                                            {{ $name }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @if ($selectedItemId)
                            <p class="mt-2 text-sm text-emerald-600 dark:text-emerald-400">✓ Selected</p>
                        @endif
                    </div>

                    <!-- Location Dropdown -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Storage Location</label>
                        <select
                            wire:model="selectedLocationId"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                        >
                            <option value="">Select a location...</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 dark:text-white mb-2">Quantity</label>
                        <input
                            type="number"
                            wire:model="quantity"
                            min="1"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
                        />
                    </div>
                </div>

                <!-- Footer -->
                <div class="sticky bottom-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 flex gap-3 z-10">
                    <button
                        wire:click="closeMapper()"
                        wire:loading.attr="disabled"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Cancel
                    </button>
                    <button
                        wire:click="mapItem()"
                        wire:loading.attr="disabled"
                        class="flex-1 px-4 py-2 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg wire:loading.remove class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <svg wire:loading class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove>Map Item</span>
                        <span wire:loading>Mapping...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

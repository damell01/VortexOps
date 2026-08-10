<div style="display: {{ $isOpen ? 'block' : 'none' }};">
    <!-- Backdrop -->
    <div class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm transition-colors duration-300" wire:click="close()"></div>

    <!-- Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modal-title">

    <div class="w-full max-w-5xl max-h-[90vh] rounded-2xl bg-white dark:bg-gray-900 shadow-2xl flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="flex-shrink-0 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-emerald-500 to-teal-500 px-6 py-6 text-white">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-bold">Add Inventory Items</h2>
                    <p class="text-sm text-emerald-100 mt-1">Select location and items to add to this show</p>
                </div>
                <button wire:click="close()" class="rounded-lg bg-white/20 p-2 hover:bg-white/30 transition">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Location Selection -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-emerald-50 mb-2">Inventory Location</label>
                <select wire:model.live="selectedLocationId"
                    class="w-full rounded-lg bg-white/90 text-gray-900 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-white/50">
                    <option value="">Choose a location...</option>
                    @foreach($locations as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Search Bar -->
            @if($selectedLocationId)
                <div class="mt-4 relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce-300ms="search"
                        placeholder="Search items by name or SKU..."
                        class="w-full rounded-lg bg-white/90 py-2 pl-10 pr-4 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/50"
                    >
                </div>
            @endif
        </div>

        <!-- Content Area -->
        <div class="flex-grow overflow-y-auto p-6">
            @if(!$selectedLocationId)
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <svg class="h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300">Select a location</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Choose an inventory location to see available items</p>
                </div>
            @elseif(count($items) === 0)
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <svg class="h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300">No items found</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">This location has no stock available</p>
                </div>
            @else
                <!-- Selected Items Section -->
                @if(!empty($selectedItems))
                    <div class="mb-8 pb-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Selected Items ({{ count($selectedItems) }})</h3>
                        <div class="space-y-3">
                            @foreach($selectedItems as $itemId => $item)
                                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border-2 border-emerald-200 dark:border-emerald-800 p-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</h4>
                                            @if($item['sku'])
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">SKU: {{ $item['sku'] }}</p>
                                            @endif
                                        </div>
                                        <button wire:click="toggleItem({{ $itemId }})"
                                            class="px-3 py-1 rounded bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm font-medium hover:bg-red-200 dark:hover:bg-red-900/50 transition">
                                            Remove
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Quantity</label>
                                            <input type="number"
                                                value="{{ $item['quantity'] }}"
                                                wire:change="updateQuantity({{ $itemId }}, $event.target.value)"
                                                min="1"
                                                step="1"
                                                class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1 text-sm text-gray-900 dark:text-white focus:border-emerald-500 focus:ring-emerald-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Unit Cost ($)</label>
                                            <input type="number"
                                                value="{{ number_format($item['unit_cost'], 2, '.', '') }}"
                                                wire:change="updateCost({{ $itemId }}, $event.target.value)"
                                                step="0.01"
                                                min="0"
                                                class="w-full rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1 text-sm text-gray-900 dark:text-white focus:border-emerald-500 focus:ring-emerald-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Total</label>
                                            <div class="px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-sm font-medium text-gray-900 dark:text-white">
                                                ${{ number_format($item['quantity'] * $item['unit_cost'], 2) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Available Items -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Available Items</h3>
                    <div class="grid gap-3">
                        @foreach($items as $item)
                            <button wire:click="toggleItem({{ $item['id'] }})"
                                class="group relative overflow-hidden rounded-lg border-2 px-4 py-4 text-left transition-all {{ isset($selectedItems[$item['id']]) ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/30' : 'border-gray-200 dark:border-gray-700 hover:border-emerald-300' }}">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</h3>
                                            @if(isset($selectedItems[$item['id']]))
                                                <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @if($item['sku'])
                                                <span class="inline-block bg-gray-200 dark:bg-gray-700 px-2 py-1 text-xs font-mono text-gray-700 dark:text-gray-300 rounded">SKU: {{ $item['sku'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $item['quantity_available'] }} in stock
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            @if($item['average_cost'] > 0)
                                                Cost: ${{ number_format($item['average_cost'], 2) }}
                                            @else
                                                No cost set
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Footer -->
        @if($selectedLocationId && !empty($selectedItems))
            <div class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-6 py-6">
                <div class="mb-4 flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Items selected:</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ count($selectedItems) }} item(s)</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total value:</p>
                        <p class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">
                            ${{ number_format(array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_cost'], $selectedItems)), 2) }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-3 justify-end">
                    <button wire:click="close()"
                        class="px-6 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        Cancel
                    </button>
                    <button wire:click="save()"
                        class="px-6 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-medium transition flex items-center gap-2">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Add {{ count($selectedItems) }} Item(s)
                    </button>
                </div>
            </div>
        @endif
        </div>
    </div>
</div>

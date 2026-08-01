<div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center bg-black/50 px-4 py-12 backdrop-blur-sm">
        <div class="w-full max-w-4xl transform rounded-2xl bg-white dark:bg-gray-900 shadow-2xl transition-all">
            <!-- Header -->
            <div class="sticky top-0 z-10 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-500 to-cyan-500 px-6 py-6 text-white rounded-t-2xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-2xl font-bold">Select Inventory Item</h2>
                        @if($show)
                            <p class="text-sm text-blue-100 mt-1">{{ $show->title }}</p>
                        @endif
                    </div>
                    <button wire:click="$parent.close()" class="rounded-lg bg-white/20 p-2 hover:bg-white/30 transition">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Search Bar -->
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input
                        type="text"
                        wire:model.live="search"
                        placeholder="Search by item name, SKU, or barcode..."
                        class="w-full rounded-lg bg-white/90 py-3 pl-10 pr-4 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/50"
                        autofocus
                    >
                </div>
            </div>

            <!-- Content Area -->
            <div class="max-h-96 overflow-y-auto p-6">
                @if(empty($search))
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <svg class="h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">Start searching</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Type at least 2 characters to search your inventory</p>
                    </div>
                @elseif(count($searchResults) === 0)
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <svg class="h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">No items found</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Try searching with different keywords</p>
                    </div>
                @else
                    <div class="grid gap-3">
                        @foreach($searchResults as $item)
                            <button
                                wire:click="selectItem({{ $item->id }})"
                                class="group relative overflow-hidden rounded-lg border-2 px-4 py-4 text-left transition-all {{ $selectedItemId === $item->id ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-gray-200 dark:border-gray-700 hover:border-blue-300' }}"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $item->name }}</h3>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @if($item->sku)
                                                <span class="inline-block bg-gray-200 dark:bg-gray-700 px-2 py-1 text-xs font-mono text-gray-700 dark:text-gray-300 rounded">SKU: {{ $item->sku }}</span>
                                            @endif
                                            @if($item->category)
                                                <span class="inline-block bg-blue-200 dark:bg-blue-900 px-2 py-1 text-xs font-medium text-blue-900 dark:text-blue-200 rounded">{{ $item->category }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($selectedItemId === $item->id)
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="mt-3 flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">
                                        Avg Cost: <span class="font-mono font-semibold">${{ number_format($item->average_cost ?? $item->unit_cost ?? 0, 2) }}</span>
                                    </span>
                                    @if($item->stock_sum_quantity)
                                        <span class="text-green-600 dark:text-green-400 font-medium">
                                            {{ $item->stock_sum_quantity }} in stock
                                        </span>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Footer with Cost and Location -->
            @if($selectedItemId)
                <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-6 py-6 rounded-b-2xl">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <!-- Item Selected Indicator -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Item</label>
                            @php $selected = InventoryItem::find($selectedItemId); @endphp
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-gray-900 border border-green-300 dark:border-green-700">
                                <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $selected?->name }}</span>
                            </div>
                        </div>

                        <!-- Unit Cost -->
                        <div>
                            <label for="unitCost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Unit Cost ($)</label>
                            <input
                                type="number"
                                id="unitCost"
                                wire:model.live="unitCost"
                                step="0.01"
                                min="0"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                            >
                        </div>

                        <!-- Location -->
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ship From Location</label>
                            <select
                                id="location"
                                wire:model.live="selectedLocationId"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">Select a location...</option>
                                @foreach($availableLocations as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3 justify-end">
                        <button
                            wire:click="reset"
                            class="px-6 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        >
                            Clear Selection
                        </button>
                        <button
                            wire:click="save"
                            class="px-6 py-2.5 rounded-lg bg-blue-500 hover:bg-blue-600 text-white font-medium transition flex items-center gap-2"
                        >
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Save Selection
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

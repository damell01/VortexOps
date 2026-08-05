<div class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4" @keydown.escape="$dispatch('closeModal')">
    <div class="bg-white dark:bg-gray-900 rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Add Items to Show</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Search inventory, select items, and set quantities</p>
            </div>
            <button
                @click="$dispatch('closeModal')"
                class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
                aria-label="Close"
            >
                <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- Search & Filter Controls -->
            <div class="space-y-3 sticky top-0 bg-white dark:bg-gray-900 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex gap-2">
                    <input
                        wire:model.live="search"
                        type="text"
                        placeholder="🔍 Search items by name or SKU..."
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                    />
                    <button
                        wire:click="$toggle('showingCreateForm')"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition font-medium inline-flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                        </svg>
                        New Item
                    </button>
                </div>

                @if (count($categories) > 0)
                    <div class="flex flex-wrap gap-2">
                        <button
                            wire:click="$set('selectedCategory', '')"
                            @class(['px-3 py-1 rounded text-sm transition font-medium',
                                'bg-blue-600 text-white' => $selectedCategory === '',
                                'bg-gray-200 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600' => $selectedCategory !== ''
                            ])
                        >
                            All Categories
                        </button>
                        @foreach ($categories as $category)
                            <button
                                wire:click="$set('selectedCategory', '{{ $category }}')"
                                @class(['px-3 py-1 rounded text-sm transition font-medium',
                                    'bg-blue-600 text-white' => $selectedCategory === $category,
                                    'bg-gray-200 dark:bg-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600' => $selectedCategory !== $category
                                ])
                            >
                                {{ $category }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Create New Item Form -->
            @if ($showingCreateForm)
                <div class="border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20 p-4 rounded">
                    <h4 class="font-semibold mb-3 text-green-900 dark:text-green-100">Create New Item</h4>
                    <div class="space-y-3">
                        <input
                            wire:model="newItemName"
                            type="text"
                            placeholder="Item name"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-green-500 outline-none"
                        />
                        <input
                            wire:model.number="newItemCost"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Cost (optional)"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-green-500 outline-none"
                        />
                        <div class="flex gap-2">
                            <button
                                wire:click="createNewItem"
                                wire:loading.attr="disabled"
                                class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition font-medium inline-flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg wire:loading.remove class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                                </svg>
                                <svg wire:loading class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove>Create & Select</span>
                                <span wire:loading>Creating...</span>
                            </button>
                            <button
                                wire:click="$toggle('showingCreateForm')"
                                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white rounded-lg transition"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Two Column Layout: Inventory Items + Selected Items -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column: Inventory Catalog -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800/50">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Available Inventory</h3>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @forelse ($items as $item)
                            @php
                                $totalStock = $item->stock->sum('quantity_on_hand') ?? 0;
                                $isSelected = isset($selectedItems[$item->id]);
                            @endphp
                            <label class="flex items-start p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-white dark:hover:bg-gray-700 cursor-pointer transition">
                                <input
                                    type="checkbox"
                                    wire:click="toggleItem({{ $item->id }})"
                                    @checked($isSelected)
                                    class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500 mt-0.5 flex-shrink-0"
                                />
                                <div class="ml-3 flex-1">
                                    <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ $item->name }}</p>
                                    @if ($item->sku)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ $item->sku }}</p>
                                    @endif
                                    @if ($item->category)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->category }}</p>
                                    @endif
                                    <div class="mt-2 flex gap-4 text-xs">
                                        <div>
                                            <p class="text-gray-600 dark:text-gray-400">Stock:</p>
                                            <p class="font-semibold @if ($totalStock <= 0) text-red-600 dark:text-red-400 @endif">{{ $totalStock }}</p>
                                        </div>
                                        @if ($item->unit_cost)
                                            <div>
                                                <p class="text-gray-600 dark:text-gray-400">Cost:</p>
                                                <p class="font-semibold">${{ number_format($item->unit_cost, 2) }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @empty
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                <p>No items found</p>
                                @if ($search)
                                    <p class="text-xs">Try a different search term</p>
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Right Column: Selected Items with Quantity/Cost -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800/50">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                        Selected Items
                        @if (!empty($selectedItems))
                            <span class="text-sm font-normal text-gray-600 dark:text-gray-400">({{ count($selectedItems) }})</span>
                        @endif
                    </h3>

                    @if (!empty($selectedItems))
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            @foreach ($selectedItems as $itemId => $item)
                                <div class="border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                                    <div class="flex justify-between items-start mb-2">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                        <button
                                            wire:click="toggleItem({{ $itemId }})"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Quantity</label>
                                            <input
                                                type="number"
                                                min="1"
                                                wire:change="updateQuantity({{ $itemId }}, $event.target.value)"
                                                wire:model="selectedItems.{{ $itemId }}.quantity"
                                                class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                            />
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-600 dark:text-gray-400 block mb-1">Unit Cost ($)</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                wire:change="updateUnitCost({{ $itemId }}, $event.target.value)"
                                                wire:model="selectedItems.{{ $itemId }}.unit_cost"
                                                class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                                            />
                                        </div>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-blue-200 dark:border-blue-800 text-sm">
                                        <p class="text-right text-blue-900 dark:text-blue-100 font-semibold">
                                            Total: ${{ number_format((float)$item['unit_cost'] * (int)$item['quantity'], 2) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p>No items selected yet</p>
                            <p class="text-xs">Select items from the left to add them</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0 bg-gray-50 dark:bg-gray-800/50">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                @if (!empty($selectedItems))
                    <span class="font-semibold text-gray-900 dark:text-white">{{ count($selectedItems) }} item(s)</span>
                    selected for {{ count($selectedItems) }}
                @else
                    Select items to add to this show
                @endif
            </p>
            <div class="flex gap-3">
                <button
                    @click="$dispatch('closeModal')"
                    class="px-6 py-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white rounded-lg transition font-medium"
                >
                    Cancel
                </button>
                <button
                    wire:click="confirmSelection"
                    @disabled(empty($selectedItems))
                    wire:loading.attr="disabled"
                    @class([
                        'px-6 py-2 rounded-lg transition font-medium inline-flex items-center gap-2',
                        'bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50 disabled:cursor-not-allowed' => !empty($selectedItems),
                        'bg-gray-300 dark:bg-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed' => empty($selectedItems)
                    ])
                >
                    <svg wire:loading.remove class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <svg wire:loading class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove>Add Selected Items</span>
                    <span wire:loading>Adding...</span>
                </button>
            </div>
        </div>
    </div>
</div>

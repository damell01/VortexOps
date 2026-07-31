<div class="space-y-4">
    <!-- Search & Filter Controls -->
    <div class="space-y-3">
        <div class="flex gap-2">
            <input
                wire:model.live="search"
                type="text"
                placeholder="🔍 Search items by name or SKU..."
                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-900 dark:text-white"
            />
            <button
                wire:click="$toggle('showingCreateForm')"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
            >
                ➕ New Item
            </button>
        </div>

        @if (count($categories) > 0)
            <div class="flex flex-wrap gap-2">
                <button
                    wire:click="$set('selectedCategory', '')"
                    @class(['px-3 py-1 rounded text-sm transition',
                        'bg-blue-600 text-white' => $selectedCategory === '',
                        'bg-gray-200 dark:bg-gray-700 dark:text-gray-200' => $selectedCategory !== ''
                    ])
                >
                    All
                </button>
                @foreach ($categories as $category)
                    <button
                        wire:click="$set('selectedCategory', '{{ $category }}')"
                        @class(['px-3 py-1 rounded text-sm transition',
                            'bg-blue-600 text-white' => $selectedCategory === $category,
                            'bg-gray-200 dark:bg-gray-700 dark:text-gray-200' => $selectedCategory !== $category
                        ])
                    >
                        {{ $category }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Create New Item Form (if showing) -->
    @if ($showingCreateForm)
        <div class="border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20 p-4 rounded">
            <h4 class="font-semibold mb-3 text-green-900 dark:text-green-100">Create New Item</h4>
            <div class="space-y-3">
                <input
                    wire:model="newItemName"
                    type="text"
                    placeholder="Item name"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded lg dark:bg-gray-900 dark:text-white"
                />
                <input
                    wire:model.number="newItemCost"
                    type="number"
                    step="0.01"
                    placeholder="Cost (optional)"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded lg dark:bg-gray-900 dark:text-white"
                />
                <div class="flex gap-2">
                    <button
                        wire:click="createNewItem"
                        class="flex-1 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition font-medium"
                    >
                        Create & Select
                    </button>
                    <button
                        wire:click="$toggle('showingCreateForm')"
                        class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-white rounded hover:bg-gray-400 dark:hover:bg-gray-500 transition"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Inventory Catalog (Card Grid) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-96 overflow-y-auto">
        @forelse ($items as $item)
            @php
                $totalStock = $item->stock->sum('quantity_on_hand') ?? 0;
                $isOutOfStock = $totalStock <= 0;
            @endphp
            <button
                wire:click="selectItem({{ $item->id }})"
                @class([
                    'p-3 border-2 rounded-lg text-left transition-all',
                    'border-blue-500 bg-blue-50 dark:bg-blue-900/20' => $selectedItemId === $item->id,
                    'border-gray-300 dark:border-gray-600 hover:border-blue-400 dark:hover:border-blue-500' => $selectedItemId !== $item->id,
                    'opacity-60' => $isOutOfStock
                ])
            >
                <h5 class="font-semibold text-sm leading-tight">{{ $item->name }}</h5>
                @if ($item->sku)
                    <p class="text-xs text-gray-600 dark:text-gray-400">SKU: {{ $item->sku }}</p>
                @endif
                @if ($item->category)
                    <p class="text-xs text-gray-500 dark:text-gray-500">{{ $item->category }}</p>
                @endif

                <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Stock:</p>
                        <p class="font-semibold text-sm @if ($isOutOfStock) text-red-600 dark:text-red-400 @endif">
                            {{ $totalStock }}
                        </p>
                    </div>
                    @if ($item->unit_cost)
                        <div class="text-right">
                            <p class="text-xs text-gray-600 dark:text-gray-400">Cost:</p>
                            <p class="font-semibold text-sm">${{ number_format($item->unit_cost, 2) }}</p>
                        </div>
                    @endif
                </div>

                @if ($isOutOfStock)
                    <p class="text-xs text-red-600 dark:text-red-400 mt-2 font-medium">Out of Stock</p>
                @endif

                @if ($selectedItemId === $item->id)
                    <div class="mt-2 text-center text-xs font-semibold text-blue-600 dark:text-blue-400">
                        ✓ Selected
                    </div>
                @endif
            </button>
        @empty
            <div class="col-span-full text-center py-8 text-gray-500 dark:text-gray-400">
                <p>No items found</p>
                @if ($search)
                    <p class="text-xs">Try a different search term</p>
                @endif
            </div>
        @endforelse
    </div>

    @if ($selectedItemId)
        <div class="flex justify-end pt-4 border-t dark:border-gray-700">
            <button
                type="button"
                onclick="Livewire.dispatch('confirm-item-selection', { itemId: {{ $selectedItemId }} }); @this.close()"
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition font-medium"
            >
                Confirm Selection
            </button>
        </div>
    @endif
</div>

<div class="bg-white dark:bg-slate-950 flex flex-col overflow-hidden">

    <!-- Header -->
    <div class="px-8 py-6 bg-gradient-to-r from-blue-600 to-blue-700 dark:from-blue-900 dark:to-blue-800 flex items-center justify-between shadow-lg">
        <div>
            <h1 class="text-3xl font-bold text-white">{{ $title ?? 'Inventory Catalog' }}</h1>
            @if (!empty($selectedItems))
                <p class="text-blue-100 text-sm mt-1">{{ count($selectedItems) }} item{{ count($selectedItems) !== 1 ? 's' : '' }} selected</p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            <button @click="$dispatch('closeModal')" class="px-5 py-2.5 bg-white dark:bg-slate-800 text-slate-900 dark:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition font-semibold shadow-md">
                Cancel
            </button>
            <button wire:click="confirmSelection" @disabled(empty($selectedItems)) @class(['px-6 py-2.5 rounded-lg font-bold text-white transition shadow-lg',
                'bg-green-600 hover:bg-green-700 active:bg-green-800' => !empty($selectedItems),
                'bg-slate-400 dark:bg-slate-600 cursor-not-allowed opacity-60' => empty($selectedItems)
            ])>
                ✓ Apply Items
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-1 overflow-hidden gap-6 p-6 bg-slate-100 dark:bg-slate-900">

        <!-- Left: Catalog -->
        <div class="flex-1 flex flex-col overflow-hidden bg-white dark:bg-slate-800 rounded-xl shadow-lg">

            <!-- Search & Categories -->
            <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700 space-y-4 bg-white dark:bg-slate-800">
                <div class="relative">
                    <svg class="absolute left-3 top-3 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        wire:model.live="search"
                        type="text"
                        placeholder="Search by name, SKU..."
                        class="w-full pl-10 pr-4 py-3 border-2 border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                    />
                </div>

                @if (count($categories) > 0)
                    <div class="flex gap-2 flex-wrap">
                        <button
                            wire:click="$set('selectedCategory', '')"
                            @class(['px-4 py-2 rounded-full text-sm font-semibold transition',
                                'bg-blue-600 text-white shadow-md' => $selectedCategory === '',
                                'bg-slate-100 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' => $selectedCategory !== ''
                            ])
                        >
                            All
                        </button>
                        @foreach ($categories as $category)
                            <button
                                wire:click="$set('selectedCategory', '{{ $category }}')"
                                @class(['px-4 py-2 rounded-full text-sm font-semibold transition',
                                    'bg-blue-600 text-white shadow-md' => $selectedCategory === $category,
                                    'bg-slate-100 dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600' => $selectedCategory !== $category
                                ])
                            >
                                {{ $category }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Items Grid -->
            <div class="flex-1 overflow-y-auto p-6 space-y-3">
                @forelse ($items as $item)
                    @php
                        $totalStock = $item->stock->sum('quantity_on_hand') ?? 0;
                        $isSelected = isset($selectedItems[$item->id]);
                        $selectedQty = $selectedItems[$item->id]['quantity'] ?? 1;
                    @endphp
                    <div class="p-4 border-2 rounded-lg cursor-pointer transition"
                        :class="{ 'border-green-500 bg-green-50 dark:bg-green-900/20 shadow-md': {{ $isSelected ? 'true' : 'false' }}, 'border-slate-300 dark:border-slate-700 hover:border-blue-400 dark:hover:border-blue-500': {{ $isSelected ? 'false' : 'true' }} }"
                    >
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-3 flex-1 cursor-pointer">
                                <input type="checkbox" wire:click="toggleItem({{ $item->id }})" @checked($isSelected) class="w-5 h-5 text-blue-600 rounded cursor-pointer" />
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-slate-900 dark:text-white truncate">{{ $item->name }}</p>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 font-mono mt-0.5">{{ $item->sku ?? 'N/A' }}</p>
                                    @if ($item->category)
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $item->category }}</p>
                                    @endif
                                    <div class="mt-2 flex gap-6 text-xs text-slate-600 dark:text-slate-400">
                                        <span>Stock: <span class="font-bold text-slate-900 dark:text-white">{{ $totalStock }}</span></span>
                                        @if ($item->unit_cost)
                                            <span>Cost: <span class="font-bold text-slate-900 dark:text-white">${{ number_format($item->unit_cost, 2) }}</span></span>
                                        @endif
                                    </div>
                                </div>
                            </label>

                            @if ($isSelected)
                                <div class="flex items-center gap-2 bg-white dark:bg-slate-700 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 flex-shrink-0">
                                    <button wire:click="updateQuantity({{ $item->id }}, {{ max(1, $selectedQty - 1) }})" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold px-1">−</button>
                                    <span class="w-8 text-center font-bold text-slate-900 dark:text-white">{{ $selectedQty }}</span>
                                    <button wire:click="updateQuantity({{ $item->id }}, {{ $selectedQty + 1 }})" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold px-1">+</button>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-slate-500 dark:text-slate-400">
                        <p class="text-lg font-medium">No items found</p>
                        @if ($search)
                            <p class="text-sm">Try a different search</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Right: Selection Summary -->
        <div class="w-96 bg-white dark:bg-slate-800 rounded-xl shadow-lg flex flex-col overflow-hidden border-2 border-green-500 dark:border-green-600">

            <div class="px-6 py-5 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/30 dark:to-emerald-900/30 border-b-2 border-green-200 dark:border-green-700">
                <h2 class="text-xl font-bold text-green-900 dark:text-green-300">✓ Selected Items</h2>
            </div>

            @if (!empty($selectedItems))
                <div class="flex-1 overflow-y-auto p-6 space-y-3">
                    @foreach ($selectedItems as $itemId => $item)
                        @php
                            $inventoryItem = \App\Models\InventoryItem::find($itemId);
                        @endphp
                        <div class="bg-slate-50 dark:bg-slate-700 rounded-lg p-4 border-2 border-green-200 dark:border-green-700">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-sm text-slate-900 dark:text-white truncate">{{ $item['name'] }}</p>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">{{ $inventoryItem?->category ?? 'Uncategorized' }}</p>
                                </div>
                                <button wire:click="toggleItem({{ $itemId }})" class="text-red-500 hover:text-red-700 dark:hover:text-red-400 font-bold text-lg ml-2 flex-shrink-0 w-6 h-6 flex items-center justify-center">✕</button>
                            </div>
                            <div class="flex justify-between text-xs text-slate-600 dark:text-slate-400 border-t border-slate-200 dark:border-slate-600 pt-2">
                                <span>Qty: <span class="font-bold text-slate-900 dark:text-white">{{ $item['quantity'] }}</span></span>
                                <span>Subtotal: <span class="font-bold text-green-600 dark:text-green-400">${{ number_format((float)$item['unit_cost'] * (int)$item['quantity'], 2) }}</span></span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t-2 border-slate-200 dark:border-slate-600 px-6 py-5 bg-slate-50 dark:bg-slate-700/50">
                    <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border-2 border-green-200 dark:border-green-700">
                        <p class="text-xs text-slate-600 dark:text-slate-400 mb-1">Total Value</p>
                        <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                            ${{ number_format(collect($selectedItems)->sum(fn($item) => (float)$item['unit_cost'] * (int)$item['quantity']), 2) }}
                        </p>
                    </div>
                    <button wire:click="$set('selectedItems', [])" class="w-full px-4 py-2 text-sm bg-slate-300 dark:bg-slate-600 hover:bg-slate-400 dark:hover:bg-slate-500 text-slate-700 dark:text-slate-200 rounded-lg font-medium transition">
                        Clear Selection
                    </button>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-slate-400 dark:text-slate-600 p-6">
                    <svg class="w-16 h-16 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="text-sm font-medium text-center text-slate-600 dark:text-slate-400">Select items from the left</p>
                </div>
            @endif
        </div>
    </div>
</div>

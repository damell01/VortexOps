<div class="bg-white dark:bg-slate-950 flex flex-col overflow-hidden">

    <!-- Header -->
    <div class="px-8 py-5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-white dark:bg-slate-900">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $title ?? 'INVENTORY CATALOG' }}</h1>
        <div class="flex items-center gap-4">
            @if (!empty($selectedItems))
                <span class="px-4 py-2 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full font-bold">
                    {{ count($selectedItems) }} Items Selected
                </span>
            @endif
            <button @click="$dispatch('closeModal')" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition font-medium">
                Close
            </button>
            <button wire:click="confirmSelection" @disabled(empty($selectedItems)) @class(['px-4 py-2 rounded-lg font-bold text-white transition',
                'bg-green-600 hover:bg-green-700' => !empty($selectedItems),
                'bg-slate-300 dark:bg-slate-700 cursor-not-allowed' => empty($selectedItems)
            ])>
                Apply to Log
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-1 overflow-hidden">

        <!-- Left: Catalog -->
        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- Search & Categories -->
            <div class="px-8 py-5 border-b border-slate-200 dark:border-slate-700 space-y-4 bg-slate-50 dark:bg-slate-900/50">
                <div class="relative">
                    <svg class="absolute left-3 top-3 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        wire:model.live="search"
                        type="text"
                        placeholder="Search items by name, SKU, condition..."
                        class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                    />
                </div>

                @if (count($categories) > 0)
                    <div class="flex gap-2 flex-wrap">
                        <button
                            wire:click="$set('selectedCategory', '')"
                            @class(['px-4 py-2 rounded-full text-sm font-semibold transition',
                                'bg-blue-600 text-white' => $selectedCategory === '',
                                'bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' => $selectedCategory !== ''
                            ])
                        >
                            All
                        </button>
                        @foreach ($categories as $category)
                            <button
                                wire:click="$set('selectedCategory', '{{ $category }}')"
                                @class(['px-4 py-2 rounded-full text-sm font-semibold transition',
                                    'bg-blue-600 text-white' => $selectedCategory === $category,
                                    'bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' => $selectedCategory !== $category
                                ])
                            >
                                {{ $category }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Items Grid -->
            <div class="flex-1 overflow-y-auto p-8">
                @forelse ($items as $item)
                    @php
                        $totalStock = $item->stock->sum('quantity_on_hand') ?? 0;
                        $isSelected = isset($selectedItems[$item->id]);
                        $selectedQty = $selectedItems[$item->id]['quantity'] ?? 1;
                    @endphp
                    <div class="mb-4 p-4 border-2 rounded-lg cursor-pointer transition"
                        :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/20': {{ $isSelected ? 'true' : 'false' }}, 'border-slate-200 dark:border-slate-700 hover:border-blue-300': {{ $isSelected ? 'false' : 'true' }} }"
                    >
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-3 flex-1">
                                <input type="checkbox" wire:click="toggleItem({{ $item->id }})" @checked($isSelected) class="w-5 h-5 text-blue-600 rounded cursor-pointer" />
                                <div class="flex-1">
                                    <p class="font-bold text-slate-900 dark:text-white">{{ $item->name }}</p>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 font-mono mt-0.5">SKU: {{ $item->sku ?? 'N/A' }}</p>
                                    @if ($item->category)
                                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ $item->category }}</p>
                                    @endif
                                    <div class="mt-2 flex gap-6 text-sm">
                                        <span class="text-slate-600 dark:text-slate-400">Stock: <span class="font-bold">{{ $totalStock }}</span></span>
                                        @if ($item->unit_cost)
                                            <span class="text-slate-600 dark:text-slate-400">Cost: <span class="font-bold">${{ number_format($item->unit_cost, 2) }}</span></span>
                                        @endif
                                    </div>
                                </div>
                            </label>

                            @if ($isSelected)
                                <div class="flex items-center gap-2 bg-white dark:bg-slate-800 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700">
                                    <button wire:click="updateQuantity({{ $item->id }}, {{ max(1, $selectedQty - 1) }})" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold">−</button>
                                    <span class="w-8 text-center font-bold text-slate-900 dark:text-white">{{ $selectedQty }}</span>
                                    <button wire:click="updateQuantity({{ $item->id }}, {{ $selectedQty + 1 }})" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold">+</button>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16 text-slate-500 dark:text-slate-400">
                        <p class="text-lg font-medium">No items found</p>
                        @if ($search)
                            <p class="text-sm">Try a different search</p>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Right: Selection Summary -->
        <div class="w-80 border-l border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex flex-col overflow-hidden">

            <div class="px-6 py-5 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Current Selection</h2>
            </div>

            @if (!empty($selectedItems))
                <div class="flex-1 overflow-y-auto p-6 space-y-3">
                    @foreach ($selectedItems as $itemId => $item)
                        @php
                            $inventoryItem = \App\Models\InventoryItem::find($itemId);
                        @endphp
                        <div class="bg-white dark:bg-slate-800 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <p class="font-bold text-sm text-slate-900 dark:text-white">{{ $item['name'] }}</p>
                                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">{{ $inventoryItem?->category ?? '' }}</p>
                                </div>
                                <button wire:click="toggleItem({{ $itemId }})" class="text-red-600 hover:text-red-700 font-bold text-sm ml-2">✕</button>
                            </div>
                            <div class="flex justify-between text-xs text-slate-600 dark:text-slate-400">
                                <span>Qty: <span class="font-bold text-slate-900 dark:text-white">{{ $item['quantity'] }}</span></span>
                                <span>Cost: <span class="font-bold text-slate-900 dark:text-white">${{ number_format((float)$item['unit_cost'] * (int)$item['quantity'], 2) }}</span></span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 dark:border-slate-700 p-6">
                    <div class="text-center">
                        <p class="text-sm text-slate-600 dark:text-slate-400">Total Value</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white">
                            ${{ number_format(collect($selectedItems)->sum(fn($item) => (float)$item['unit_cost'] * (int)$item['quantity']), 2) }}
                        </p>
                    </div>
                    <button wire:click="$set('selectedItems', [])" class="w-full mt-4 px-3 py-2 text-sm bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 rounded-lg font-medium transition">
                        Clear Selection
                    </button>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-slate-400 dark:text-slate-600 p-6">
                    <svg class="w-12 h-12 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <p class="text-sm font-medium text-center">Select items from the catalog</p>
                </div>
            @endif
        </div>
    </div>
</div>

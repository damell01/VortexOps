<div class="flex flex-col h-full">
    <!-- Header -->
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Select Items Sold</h1>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Search and select inventory items for this show</p>
            </div>
            <button @click="$dispatch('closeModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Search -->
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
        <div class="relative">
            <svg class="absolute left-3 top-3 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
                wire:model.live="search"
                type="text"
                placeholder="Search by name, SKU..."
                class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
            />
        </div>

        @if (count($categories) > 0)
            <div class="flex gap-2 flex-wrap mt-3">
                <button
                    wire:click="$set('selectedCategory', '')"
                    @class(['px-3 py-1.5 rounded-full text-xs font-semibold transition',
                        'bg-blue-600 text-white' => $selectedCategory === '',
                        'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600' => $selectedCategory !== ''
                    ])
                >
                    All
                </button>
                @foreach ($categories as $category)
                    <button
                        wire:click="$set('selectedCategory', '{{ $category }}')"
                        @class(['px-3 py-1.5 rounded-full text-xs font-semibold transition',
                            'bg-blue-600 text-white' => $selectedCategory === $category,
                            'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600' => $selectedCategory !== $category
                        ])
                    >
                        {{ $category }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Items Grid -->
    <div class="flex-1 overflow-y-auto p-6">
        @forelse ($items as $item)
            @php
                $totalStock = $item->stock->sum('quantity_on_hand') ?? 0;
                $isSelected = isset($selectedItems[$item->id]);
                $selectedQty = $selectedItems[$item->id]['quantity'] ?? 1;
            @endphp
            <div class="mb-3 p-4 border-2 rounded-lg cursor-pointer transition" :class="{
                'border-blue-500 bg-blue-50 dark:bg-blue-900/20': {{ $isSelected ? 'true' : 'false' }},
                'border-slate-300 dark:border-slate-700 hover:border-blue-400 dark:hover:border-blue-500': {{ $isSelected ? 'false' : 'true' }}
            }">
                <div class="flex items-start gap-4">
                    <label class="flex items-center gap-3 flex-1 cursor-pointer">
                        <input type="checkbox" wire:click="toggleItem({{ $item->id }})" @checked($isSelected) class="w-5 h-5 text-blue-600 rounded cursor-pointer" />
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-900 dark:text-white text-sm">{{ $item->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ $item->sku ?? 'N/A' }}</p>
                            @if ($item->category)
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $item->category }}</p>
                            @endif
                            <div class="mt-2 flex gap-4 text-xs text-slate-600 dark:text-slate-400">
                                <span>Stock: <span class="font-bold">{{ $totalStock }}</span></span>
                                @if ($item->unit_cost)
                                    <span>Cost: <span class="font-bold">${{ number_format($item->unit_cost, 2) }}</span></span>
                                @endif
                            </div>
                        </div>
                    </label>

                    @if ($isSelected)
                        <div class="flex items-center gap-1 bg-white dark:bg-slate-700 px-2 py-1 rounded border border-slate-300 dark:border-slate-600 flex-shrink-0">
                            <button wire:click="updateQuantity({{ $item->id }}, {{ max(1, $selectedQty - 1) }})" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold px-1 text-sm">−</button>
                            <span class="w-6 text-center font-bold text-slate-900 dark:text-white text-sm">{{ $selectedQty }}</span>
                            <button wire:click="updateQuantity({{ $item->id }}, {{ $selectedQty + 1 }})" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-bold px-1 text-sm">+</button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-slate-500 dark:text-slate-400">
                <p class="text-sm font-medium">No items found</p>
                @if ($search)
                    <p class="text-xs">Try a different search</p>
                @endif
            </div>
        @endforelse
    </div>

    <!-- Footer -->
    <div class="border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-6 py-4">
        <div class="flex items-center justify-between">
            <div>
                @if (!empty($selectedItems))
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        <span class="font-bold text-slate-900 dark:text-white">{{ count($selectedItems) }}</span> item{{ count($selectedItems) !== 1 ? 's' : '' }} selected
                    </p>
                    <p class="text-sm font-bold text-blue-600 dark:text-blue-400 mt-0.5">
                        Total: ${{ number_format(collect($selectedItems)->sum(fn($item) => (float)$item['unit_cost'] * (int)$item['quantity']), 2) }}
                    </p>
                @endif
            </div>
            <div class="flex gap-3">
                <button @click="$dispatch('closeModal')" class="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition font-medium text-sm">
                    Cancel
                </button>
                <button wire:click="confirmSelection" @disabled(empty($selectedItems)) @class(['px-6 py-2 rounded-lg font-medium text-white transition text-sm',
                    'bg-blue-600 hover:bg-blue-700' => !empty($selectedItems),
                    'bg-slate-400 dark:bg-slate-600 cursor-not-allowed opacity-60' => empty($selectedItems)
                ])>
                    Add Items
                </button>
            </div>
        </div>
    </div>
</div>

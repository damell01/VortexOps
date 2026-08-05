<div class="h-screen w-screen bg-white dark:bg-slate-950 flex flex-col overflow-hidden">

        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between bg-white dark:bg-slate-900">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-white">{{ $title ?? 'Add Items' }}</h1>
                <p class="text-slate-600 dark:text-slate-400 mt-1">{{ $description ?? 'Select items and quantities' }}</p>
            </div>
            <button
                @click="$dispatch('closeModal')"
                class="p-2 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition text-slate-600 dark:text-slate-400"
                aria-label="Close"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Content -->
        <div class="flex flex-1 overflow-hidden">

            <!-- Left: Inventory Picker -->
            <div class="flex-1 flex flex-col border-r border-slate-200 dark:border-slate-700 p-8">

                <!-- Search & Filters -->
                <div class="mb-6 space-y-4">
                    <div class="relative">
                        <svg class="absolute left-3 top-3 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            wire:model.live="search"
                            type="text"
                            placeholder="Search items..."
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                        />
                    </div>

                    @if (count($categories) > 0)
                        <div class="flex gap-2 flex-wrap">
                            <button
                                wire:click="$set('selectedCategory', '')"
                                @class(['px-3 py-1.5 rounded-full text-sm font-medium transition',
                                    'bg-blue-600 text-white' => $selectedCategory === '',
                                    'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600' => $selectedCategory !== ''
                                ])
                            >
                                All
                            </button>
                            @foreach ($categories as $category)
                                <button
                                    wire:click="$set('selectedCategory', '{{ $category }}')"
                                    @class(['px-3 py-1.5 rounded-full text-sm font-medium transition',
                                        'bg-blue-600 text-white' => $selectedCategory === $category,
                                        'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600' => $selectedCategory !== $category
                                    ])
                                >
                                    {{ $category }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <button
                        wire:click="$toggle('showingCreateForm')"
                        class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition font-medium flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                        </svg>
                        New Item
                    </button>
                </div>

                <!-- Create Form -->
                @if ($showingCreateForm)
                    <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg space-y-3">
                        <h3 class="font-bold text-emerald-900 dark:text-emerald-100">Create New Item</h3>
                        <input wire:model="newItemName" type="text" placeholder="Item name" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500" />
                        <input wire:model.number="newItemCost" type="number" step="0.01" placeholder="Cost (optional)" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500" />
                        <div class="flex gap-2">
                            <button wire:click="createNewItem" class="flex-1 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium">Create</button>
                            <button wire:click="$toggle('showingCreateForm')" class="flex-1 px-3 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-lg font-medium">Cancel</button>
                        </div>
                    </div>
                @endif

                <!-- Items List -->
                <div class="flex-1 overflow-y-auto space-y-2">
                    @forelse ($items as $item)
                        @php
                            $totalStock = $item->stock->sum('quantity_on_hand') ?? 0;
                            $isSelected = isset($selectedItems[$item->id]);
                        @endphp
                        <label class="flex items-start p-3 border-2 rounded-lg cursor-pointer transition"
                            :class="{ 'border-blue-500 bg-blue-50 dark:bg-blue-900/30': {{ $isSelected ? 'true' : 'false' }}, 'border-slate-200 dark:border-slate-700 hover:border-blue-400': {{ $isSelected ? 'false' : 'true' }} }"
                        >
                            <input type="checkbox" wire:click="toggleItem({{ $item->id }})" @checked($isSelected) class="w-5 h-5 text-blue-600 rounded mt-0.5" />
                            <div class="ml-3 flex-1">
                                <p class="font-bold text-slate-900 dark:text-white">{{ $item->name }}</p>
                                @if ($item->sku)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ $item->sku }}</p>
                                @endif
                                <div class="mt-2 flex gap-4 text-xs">
                                    <div>
                                        <p class="text-slate-600 dark:text-slate-400">Stock: <span class="font-bold">{{ $totalStock }}</span></p>
                                    </div>
                                    @if ($item->unit_cost)
                                        <div>
                                            <p class="text-slate-600 dark:text-slate-400">Cost: <span class="font-bold">${{ number_format($item->unit_cost, 2) }}</span></p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @empty
                        <div class="text-center py-12 text-slate-500 dark:text-slate-400">
                            <p class="font-medium">No items found</p>
                            @if ($search)
                                <p class="text-sm">Try a different search</p>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Right: Selected Items -->
            <div class="w-96 flex flex-col bg-slate-50 dark:bg-slate-900 p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">
                        Selected
                        @if (!empty($selectedItems))
                            <span class="ml-2 px-2.5 py-1 bg-blue-600 text-white rounded-full text-sm">{{ count($selectedItems) }}</span>
                        @endif
                    </h2>
                </div>

                @if (!empty($selectedItems))
                    <div class="flex-1 overflow-y-auto space-y-4 mb-6">
                        @foreach ($selectedItems as $itemId => $item)
                            <div class="bg-white dark:bg-slate-800 rounded-lg p-4 border-2 border-blue-200 dark:border-blue-800">
                                <div class="flex items-start justify-between mb-3">
                                    <p class="font-bold text-slate-900 dark:text-white text-sm">{{ $item['name'] }}</p>
                                    <button wire:click="toggleItem({{ $itemId }})" class="text-red-600 hover:text-red-700 text-xs font-bold">✕</button>
                                </div>

                                <div class="space-y-2 mb-3">
                                    <div>
                                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 block mb-1">Qty</label>
                                        <input type="number" min="1" wire:model.live="selectedItems.{{ $itemId }}.quantity" class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded dark:bg-slate-700 dark:text-white text-sm font-semibold" />
                                    </div>
                                    <div>
                                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-400 block mb-1">Cost <span class="text-xs font-normal text-slate-500">(opt)</span></label>
                                        <input type="number" step="0.01" wire:model.live="selectedItems.{{ $itemId }}.unit_cost" placeholder="Auto" class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded dark:bg-slate-700 dark:text-white text-sm font-semibold" />
                                    </div>
                                </div>

                                <div class="bg-blue-100 dark:bg-blue-900/50 rounded p-2 text-center">
                                    <p class="text-xs text-blue-700 dark:text-blue-300">Total</p>
                                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400">${{ number_format((float)$item['unit_cost'] * (int)$item['quantity'], 2) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-slate-400">
                        <svg class="w-16 h-16 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <p class="text-sm font-medium">No items yet</p>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-6 border-t border-slate-200 dark:border-slate-700">
                    <button @click="$dispatch('closeModal')" class="flex-1 px-4 py-2.5 bg-slate-300 dark:bg-slate-700 hover:bg-slate-400 dark:hover:bg-slate-600 text-slate-800 dark:text-white rounded-lg transition font-bold">
                        Cancel
                    </button>
                    <button wire:click="confirmSelection" :disabled="!Object.keys($selectedItems || {}).length" @class="flex-1 px-4 py-2.5 rounded-lg transition font-bold flex items-center justify-center gap-2"
                        :class="{ 'bg-blue-600 hover:bg-blue-700 text-white': Object.keys($selectedItems || {}).length, 'bg-slate-300 dark:bg-slate-700 text-slate-500 cursor-not-allowed': !Object.keys($selectedItems || {}).length }"
                    >
                        <svg wire:loading.remove class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span wire:loading.remove>Add</span>
                        <span wire:loading>Adding...</span>
                    </button>
                </div>
            </div>

        </div>
    </div>

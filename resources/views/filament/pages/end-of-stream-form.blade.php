<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Step 1: Show Selection --}}
        @if (!$this->show)
            <div class="rounded-xl border-2 border-dashed border-primary-300 dark:border-primary-700 bg-primary-50 dark:bg-primary-900/20 p-8">
                <div class="text-center space-y-4">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-800">
                        <x-heroicon-o-tv class="h-8 w-8 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Select a Show</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Choose the show you just streamed</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
                        @forelse ($this->shows as $s)
                            <button type="button"
                                    wire:click="selectShow('{{ $s->id }}')"
                                    class="group rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-primary-400 dark:hover:border-primary-600 hover:shadow-md transition-all p-4 text-left">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100 group-hover:text-primary-600 dark:group-hover:text-primary-400">
                                            {{ $s->title }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $s->show_date?->format('M j, Y') }}
                                        </div>
                                    </div>
                                    <x-heroicon-o-chevron-right class="h-5 w-5 text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors" />
                                </div>
                            </button>
                        @empty
                            <div class="col-span-2 text-center py-8 text-gray-500 dark:text-gray-400">
                                <p class="text-sm">No recent shows found. Your upcoming shows will appear here.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @else
            {{-- Step 2: Inventory Selection --}}
            <div class="space-y-4">
                {{-- Header --}}
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $this->show->title }}
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $this->show->show_date?->format('M j, Y') }} •
                            Select items you sold
                        </p>
                    </div>
                    <button type="button"
                            wire:click="selectShow('')"
                            class="inline-flex items-center gap-2 rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <x-heroicon-o-arrow-left class="h-4 w-4" />
                        Change Show
                    </button>
                </div>

                {{-- Search Bar --}}
                <div class="relative">
                    <input type="text"
                           wire:model.live.debounce-300ms="search"
                           placeholder="Search by name, SKU, or brand..."
                           class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 pl-10 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:focus:border-primary-400 dark:focus:ring-primary-400/20 transition-colors" />
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400 dark:text-gray-500 pointer-events-none" />
                </div>

                {{-- Inventory Cards Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @forelse ($this->inventory as $item)
                        @php
                            $isSelected = in_array($item->id, $this->selectedItems);
                            $quantity = $this->itemQuantities[$item->id] ?? 1;
                            $totalStock = $item->stock->sum('quantity');
                        @endphp
                        <div class="rounded-lg border-2 {{ $isSelected ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/30' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:border-gray-300 dark:hover:border-gray-600' }} transition-all cursor-pointer overflow-hidden"
                             wire:click="toggleItem({{ $item->id }})">
                            <div class="p-4 space-y-3">
                                {{-- Item Name & SKU --}}
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-gray-100 line-clamp-2">
                                        {{ $item->name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        SKU: {{ $item->sku }}
                                    </div>
                                </div>

                                {{-- Brand/Details --}}
                                @if ($item->brand || $item->category)
                                    <div class="text-xs text-gray-600 dark:text-gray-300 space-y-1">
                                        @if ($item->brand)
                                            <div>{{ $item->brand }}</div>
                                        @endif
                                        @if ($item->category)
                                            <div class="text-gray-500 dark:text-gray-400">{{ $item->category }}</div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Stock Indicator --}}
                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                        {{ $totalStock }} in stock
                                    </div>
                                </div>

                                {{-- Quantity Input (when selected) --}}
                                @if ($isSelected)
                                    <div class="pt-2 border-t border-primary-200 dark:border-primary-800">
                                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                            Quantity Sold
                                        </label>
                                        <input type="number"
                                               min="1"
                                               max="{{ $totalStock }}"
                                               value="{{ $quantity }}"
                                               wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                               class="w-full rounded border border-primary-300 dark:border-primary-600 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:focus:border-primary-400 dark:focus:ring-primary-400" />
                                    </div>
                                @endif

                                {{-- Selection Indicator --}}
                                <div class="flex items-center justify-center pt-2">
                                    @if ($isSelected)
                                        <div class="inline-flex items-center gap-1 rounded-full bg-primary-500 px-3 py-1">
                                            <x-heroicon-s-check-circle class="h-4 w-4 text-white" />
                                            <span class="text-xs font-semibold text-white">Selected</span>
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Click to select</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12">
                            <x-heroicon-o-inbox class="h-12 w-12 text-gray-400 dark:text-gray-600 mx-auto mb-3" />
                            <p class="text-gray-600 dark:text-gray-400">No items found</p>
                        </div>
                    @endforelse
                </div>

                {{-- Selected Items Summary & Submit --}}
                @if (!empty($this->selectedItems))
                    <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 shadow-lg">
                        <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ count($this->selectedItems) }} item{{ count($this->selectedItems) !== 1 ? 's' : '' }} selected
                                    </div>
                                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        Total: {{ array_sum($this->itemQuantities) }} unit{{ array_sum($this->itemQuantities) !== 1 ? 's' : '' }}
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button"
                                            wire:click="selectShow('')"
                                            class="rounded-lg border border-gray-300 dark:border-gray-600 px-6 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                        Cancel
                                    </button>
                                    <button type="button"
                                            wire:click="submit"
                                            class="rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-500 transition-colors flex items-center gap-2">
                                        <x-heroicon-o-check-circle class="h-5 w-5" />
                                        Save Items
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="h-28"></div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>

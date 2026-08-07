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
            {{-- Step 2: Compact Inventory Submission --}}
            <div class="space-y-4">
                {{-- Header with Show Info & Actions --}}
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $this->show->title }}
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $this->show->show_date?->format('M j, Y') }}
                        </p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <button type="button"
                                wire:click="selectShow('')"
                                class="inline-flex items-center gap-2 rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                            <x-heroicon-o-arrow-left class="h-4 w-4" />
                            Back
                        </button>
                    </div>
                </div>

                {{-- Compact Status Bar --}}
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-primary-50 dark:from-blue-900/20 dark:to-primary-900/20 p-4">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex items-center gap-3 text-sm">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-500 text-white font-semibold">1</span>
                            <span class="text-gray-700 dark:text-gray-300"><strong>{{ count($this->selectedItems) }}</strong> items selected</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-primary-500 text-white font-semibold">2</span>
                            <span class="text-gray-700 dark:text-gray-300"><strong>{{ array_sum($this->itemQuantities) }}</strong> units total</span>
                        </div>
                        <button type="button"
                                wire:click="$set('showInventoryPicker', true)"
                                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition-colors ml-auto">
                            <x-heroicon-o-plus-circle class="h-5 w-5" />
                            Add Items
                        </button>
                    </div>
                </div>

                {{-- Selected Items Table (Compact) --}}
                @if (!empty($this->selectedItems))
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-2 text-left">Item</th>
                                    <th class="px-4 py-2 text-center">Qty</th>
                                    <th class="px-4 py-2 text-right">Stock</th>
                                    <th class="px-4 py-2 text-center w-20">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($this->inventory->whereIn('id', $this->selectedItems) as $item)
                                    @php
                                        $quantity = $this->itemQuantities[$item->id] ?? 1;
                                        $totalStock = $item->stock->sum('quantity');
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-2">
                                            <div class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $item->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->sku }}</div>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <input type="number"
                                                   min="1"
                                                   max="{{ $totalStock }}"
                                                   value="{{ $quantity }}"
                                                   wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                                   class="w-12 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-1 py-1 text-center text-sm text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
                                        </td>
                                        <td class="px-4 py-2 text-right text-xs text-gray-600 dark:text-gray-400">{{ (int)$totalStock }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <button type="button"
                                                    wire:click="toggleItem({{ $item->id }})"
                                                    class="inline-flex items-center text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors">
                                                <x-heroicon-o-x-mark class="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Submit Buttons --}}
                    <div class="flex gap-2">
                        <button type="button"
                                wire:click="selectShow('')"
                                class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Cancel
                        </button>
                        <button type="button"
                                wire:click="submit"
                                class="flex-1 rounded-lg bg-primary-600 px-4 py-3 text-sm font-semibold text-white hover:bg-primary-500 transition-colors flex items-center justify-center gap-2">
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                            Submit Items
                        </button>
                    </div>
                @else
                    <div class="rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50 p-8 text-center">
                        <x-heroicon-o-inbox class="h-10 w-10 text-gray-400 dark:text-gray-600 mx-auto mb-2" />
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">No items selected yet</p>
                        <button type="button"
                                wire:click="$set('showInventoryPicker', true)"
                                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition-colors">
                            <x-heroicon-o-plus-circle class="h-5 w-5" />
                            Add Items
                        </button>
                    </div>
                @endif
            </div>

            {{-- Inventory Picker Modal --}}
            @if ($this->showInventoryPicker ?? false)
                <div class="fixed inset-0 bg-black/50 dark:bg-black/70 z-40 flex items-center justify-center p-4">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                        {{-- Modal Header --}}
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-6 py-4 flex-shrink-0">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Add Items to Show</h3>
                            <button type="button"
                                    wire:click="$set('showInventoryPicker', false)"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                <x-heroicon-o-x-mark class="h-6 w-6" />
                            </button>
                        </div>

                        {{-- Search Bar --}}
                        <div class="border-b border-gray-200 dark:border-gray-800 px-6 py-3 flex-shrink-0">
                            <div class="relative">
                                <input type="text"
                                       wire:model.live.debounce-300ms="search"
                                       placeholder="Search by name, SKU, or brand..."
                                       class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 pl-10 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20" />
                                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500 pointer-events-none" />
                            </div>
                        </div>

                        {{-- Inventory Table --}}
                        <div class="flex-grow overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2 text-left w-6">
                                            <input type="checkbox" wire:model="selectAll" class="rounded w-4 h-4" />
                                        </th>
                                        <th class="px-4 py-2 text-left">Item</th>
                                        <th class="px-4 py-2 text-right">Stock</th>
                                        <th class="px-4 py-2 text-center">Qty</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($this->inventory as $item)
                                        @php
                                            $isSelected = in_array($item->id, $this->selectedItems);
                                            $quantity = $this->itemQuantities[$item->id] ?? 1;
                                            $totalStock = $item->stock->sum('quantity');
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                            <td class="px-4 py-2">
                                                <input type="checkbox"
                                                       wire:change="toggleItem({{ $item->id }})"
                                                       {{ $isSelected ? 'checked' : '' }}
                                                       class="rounded w-4 h-4" />
                                            </td>
                                            <td class="px-4 py-2">
                                                <div class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $item->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->sku }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-600 dark:text-gray-400">{{ (int)$totalStock }}</td>
                                            <td class="px-4 py-2">
                                                @if ($isSelected)
                                                    <input type="number"
                                                           min="1"
                                                           max="{{ $totalStock }}"
                                                           value="{{ $quantity }}"
                                                           @click.stop
                                                           wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                                           class="w-12 rounded border border-primary-300 dark:border-primary-600 bg-white dark:bg-gray-800 px-1 py-1 text-center text-sm text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-1 focus:ring-primary-500" />
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                                No items found
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Modal Footer --}}
                        <div class="border-t border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center justify-between flex-shrink-0 bg-gray-50 dark:bg-gray-800">
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                {{ count($this->selectedItems) }} selected
                            </p>
                            <button type="button"
                                    wire:click="$set('showInventoryPicker', false)"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 transition-colors">
                                Done
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>

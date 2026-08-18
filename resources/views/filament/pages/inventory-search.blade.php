<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Search & Filters Panel -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Search & Filter</h2>

            <div class="space-y-4">
                <!-- Main search input -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search</label>
                    <input wire:model.live.debounce.500ms="searchQuery" type="text" placeholder="Search by name, SKU, barcode, or UPC..."
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                </div>

                <!-- Filters Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Category Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                        <select wire:model.live="categoryFilter"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">All Categories</option>
                            @foreach(\App\Models\InventoryItem::distinct()->pluck('category')->filter() as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Vendor Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vendor</label>
                        <select wire:model.live="vendorFilter"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">All Vendors</option>
                            @foreach(\App\Models\Vendor::orderBy('name')->pluck('name') as $vendor)
                            <option value="{{ $vendor }}">{{ $vendor }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Stock Status -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stock Status</label>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="lowStockOnly" class="rounded" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Low Stock</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" wire:model.live="noStockOnly" class="rounded" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Out of Stock</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Active/Inactive Toggle -->
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="activeOnly" class="rounded" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active items only</span>
                    </label>
                </div>

                <!-- Sort Options -->
                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort By</label>
                        <select wire:model.live="sortBy"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="name">Name</option>
                            <option value="sku">SKU</option>
                            <option value="category">Category</option>
                            <option value="average_cost">Cost</option>
                            <option value="created_at">Date Added</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Order</label>
                        <select wire:model.live="sortDirection"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-gray-700 flex-wrap">
                    <button wire:click="clearFilters" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <x-heroicon-o-x-mark class="h-4 w-4 inline mr-1" />
                        Clear Filters
                    </button>

                    <!-- Save Filter Modal Trigger -->
                    <div x-data="{ open: false }" class="flex-1 flex gap-2">
                        <button @click="open = true" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-500 rounded-lg transition">
                            <x-heroicon-o-bookmark class="h-4 w-4 inline mr-1" />
                            Save Filter
                        </button>

                        <!-- Modal -->
                        {{-- x-cloak: full-viewport overlay, inert until Alpine boots. --}}
                        <div x-cloak x-show="open" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 rounded-lg">
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Save Search Filter</h3>
                                <input wire:model="savedFilterName" type="text" placeholder="Filter name (e.g., 'Low Stock Cards')"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white mb-4" />
                                <div class="flex gap-2">
                                    <button @click="open = false; @this.call('saveFilter')" class="flex-1 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg font-medium transition">
                                        Save
                                    </button>
                                    <button @click="open = false" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg font-medium transition">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Saved Filters -->
        @if(count($savedFilters) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Saved Filters</h3>
            <div class="flex gap-2 flex-wrap">
                @foreach($savedFilters as $filter)
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-300 rounded-full text-sm">
                    <button wire:click="loadFilter('{{ $filter }}')" class="hover:underline font-medium">
                        {{ $filter }}
                    </button>
                    <button wire:click="deleteFilter('{{ $filter }}')" class="hover:text-primary-600 dark:hover:text-primary-200">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Results -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Results ({{ count($this->results) }})
                </h3>
            </div>

            @if(count($this->results) === 0)
            <div class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                <x-heroicon-o-magnifying-glass class="h-12 w-12 mx-auto mb-3 opacity-50" />
                <p>No inventory items found matching your search criteria.</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <tr>
                            <th class="px-6 py-3 text-left text-gray-600 dark:text-gray-300 font-medium">Item</th>
                            <th class="px-6 py-3 text-left text-gray-600 dark:text-gray-300 font-medium">SKU / Barcode</th>
                            <th class="px-6 py-3 text-center text-gray-600 dark:text-gray-300 font-medium">Stock</th>
                            <th class="px-6 py-3 text-left text-gray-600 dark:text-gray-300 font-medium">Category</th>
                            <th class="px-6 py-3 text-right text-gray-600 dark:text-gray-300 font-medium">Avg Cost</th>
                            <th class="px-6 py-3 text-right text-gray-600 dark:text-gray-300 font-medium">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->results as $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <td class="px-6 py-3">
                                <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $item]) }}" class="text-primary-600 hover:underline font-medium">
                                    {{ $item->name }}
                                </a>
                                @if(!$item->is_active)
                                <span class="ml-2 text-xs bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-xs font-mono text-gray-600 dark:text-gray-400">
                                {{ $item->sku }} {{ $item->barcode ? '/ ' . $item->barcode : '' }}
                            </td>
                            <td class="px-6 py-3 text-center font-semibold {{ $item->totalQuantity() <= ($item->reorder_level ?? 0) ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ number_format($item->totalQuantity()) }}
                            </td>
                            <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-400">{{ $item->category ?? '—' }}</td>
                            <td class="px-6 py-3 text-right text-gray-900 dark:text-white">${{ number_format($item->average_cost, 2) }}</td>
                            <td class="px-6 py-3 text-right font-semibold text-gray-900 dark:text-white">
                                ${{ number_format($item->totalQuantity() * $item->average_cost, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>

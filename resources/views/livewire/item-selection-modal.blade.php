<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 backdrop-blur-sm animate-fade-in transition-colors duration-300 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="w-full max-w-4xl max-h-[90vh] transform rounded-2xl bg-white dark:bg-gray-900 shadow-2xl transition-all animate-scale-in flex flex-col">
            <!-- Header -->
            <div id="modal-title" class="flex-shrink-0 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-500 to-cyan-500 px-6 py-6 text-white rounded-t-2xl">
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
                        wire:model.live.debounce-300ms="search"
                        placeholder="Search by item name, SKU, or barcode..."
                        class="w-full rounded-lg bg-white/90 py-3 pl-10 pr-4 text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/50"
                        autofocus
                        @onfocus="document.dispatchEvent(new CustomEvent('showRecentSearches'))"
                    >
                    <div wire:loading class="absolute inset-y-0 right-0 flex items-center pr-3">
                        <svg class="animate-spin h-5 w-5 text-white/60" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-grow overflow-y-auto p-6">
                @if(empty($search) && !$isSearching)
                    <div id="recentSearches" style="display: none;">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Recent Searches</h3>
                        <div id="recentSearchList" class="space-y-2 mb-6"></div>
                    </div>

                    <div id="favoriteItems" style="display: none;">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Favorites</h3>
                        <div id="favoritesList" class="space-y-2 mb-6"></div>
                    </div>

                    @if(true)
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <svg class="h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">Start searching</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Type at least 2 characters to search your inventory</p>
                        </div>
                    @endif
                @elseif($isSearching)
                    <div class="space-y-3">
                        @for($i = 0; $i < 3; $i++)
                            <div class="rounded-lg border-2 border-gray-200 dark:border-gray-700 p-4 animate-pulse">
                                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                                <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-3"></div>
                                <div class="flex gap-2">
                                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
                                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20"></div>
                                </div>
                            </div>
                        @endfor
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
                                    <div class="flex-shrink-0 flex items-center gap-2">
                                        <button
                                            type="button"
                                            onclick="event.stopPropagation(); window.favorites?.toggle({{ $item->id }}, '{{ addslashes($item->name) }}')"
                                            class="favorite-btn text-xl opacity-60 hover:opacity-100 transition"
                                            data-item-id="{{ $item->id }}"
                                        >
                                            <span class="favorite-icon">☆</span>
                                        </button>
                                        @if($selectedItemId === $item->id)
                                            <svg class="h-6 w-6 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                    </div>
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
                <div class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-6 py-6 rounded-b-2xl">
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('input[wire\\:model\\.live="search"]');
    const contentArea = document.querySelector('.max-h-96');

    // Update favorite star icons
    function updateFavoriteIcons() {
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            const itemId = btn.dataset.itemId;
            const isFavorite = window.favorites?.isFavorite?.(parseInt(itemId));
            const icon = btn.querySelector('.favorite-icon');
            if (icon) {
                icon.textContent = isFavorite ? '★' : '☆';
            }
        });
    }

    // Show recent searches and favorites when search is empty
    function updateEmptyState() {
        const recentSearchesDiv = document.getElementById('recentSearches');
        const favoritesDiv = document.getElementById('favoriteItems');
        const emptyState = contentArea?.querySelector('.flex.flex-col.items-center');

        if (!searchInput?.value) {
            // Get recent searches
            const searches = window.searchHistory?.getAll?.() || [];
            const recentList = document.getElementById('recentSearchList');

            if (recentList && searches.length > 0) {
                recentList.innerHTML = searches
                    .slice(0, 5)
                    .map(query => `
                        <button type="button" onclick="document.querySelector('input[wire\\\\:model\\\\.live=\\\"search\\\"]').value='${query.replace(/'/g, "\\'")}; document.querySelector('input[wire\\\\:model\\\\.live=\\\"search\\\"]').dispatchEvent(new Event('input', { bubbles: true }));"
                            class="block w-full text-left px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition text-sm"
                        >
                            🕐 ${query}
                        </button>
                    `).join('');
                recentSearchesDiv.style.display = 'block';
            }

            // Get favorites
            const favorites = window.favorites?.getAll?.() || [];
            const favoritesList = document.getElementById('favoritesList');
            if (favoritesList && favorites.length > 0) {
                favoritesList.innerHTML = favorites
                    .slice(0, 5)
                    .map(fav => `
                        <button type="button" onclick="document.querySelector('input[wire\\\\:model\\\\.live=\\\"search\\\"]').value='${fav.label.replace(/'/g, "\\'")}; document.querySelector('input[wire\\\\:model\\\\.live=\\\"search\\\"]').dispatchEvent(new Event('input', { bubbles: true }));"
                            class="block w-full text-left px-3 py-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-gray-700 dark:text-gray-300 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition text-sm border border-amber-200 dark:border-amber-800/50"
                        >
                            ★ ${fav.label}
                        </button>
                    `).join('');
                favoritesDiv.style.display = 'block';
            }
        } else {
            recentSearchesDiv.style.display = 'none';
            favoritesDiv.style.display = 'none';
        }

        updateFavoriteIcons();
    }

    // Listen for search changes
    searchInput?.addEventListener('input', updateEmptyState);

    // Initial update
    updateEmptyState();

    // Update favorite icons when favorites change
    window.addEventListener('storage', updateFavoriteIcons);

    // Handle trackSearch event from Livewire
    window.addEventListener('trackSearch', (e) => {
        if (e.detail.query) {
            window.searchHistory?.add?.(e.detail.query);
            updateEmptyState();
        }
    });

    // Listen for livewire updates
    Livewire.hook('commit', () => {
        setTimeout(updateFavoriteIcons, 100);
    });
});
</script>

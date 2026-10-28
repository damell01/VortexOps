<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Location Selection -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- From Location -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">From Location</h3>
                <select wire:model.live="fromLocationId"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent mb-4">
                    <option value="">Select source location...</option>
                    @foreach($this->locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->type }})</option>
                    @endforeach
                </select>

                @if($fromLocationId)
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <p>Type: <strong>{{ collect($this->locations)->firstWhere('id', $fromLocationId)->type }}</strong></p>
                </div>
                @endif
            </div>

            <!-- To Location -->
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">To Location</h3>
                <select wire:model.live="toLocationId"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent mb-4">
                    <option value="">Select destination location...</option>
                    @foreach($this->locations as $loc)
                    @if($fromLocationId && $loc->id != $fromLocationId)
                    <option value="{{ $loc->id }}">{{ $loc->name }} ({{ $loc->type }})</option>
                    @endif
                    @endforeach
                </select>

                @if($toLocationId)
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <p>Type: <strong>{{ collect($this->locations)->find('id', $toLocationId)->type }}</strong></p>
                </div>
                @endif
            </div>
        </div>

        <!-- Transfer Reason -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Transfer Reason (Optional)</label>
            <textarea wire:model="transferReason" placeholder="e.g., Seasonal reorganization, Floor move, Quality check..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                rows="2"></textarea>
        </div>

        <!-- Source Stock Selection -->
        @if($fromLocationId)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Select Items</h3>

            <!-- Search -->
            <input wire:model.live.debounce.300ms="searchQuery" type="text" placeholder="Search items by name, SKU, or barcode..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-transparent mb-4" />

            <!-- Items List -->
            @if(count($this->sourceStock) === 0)
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <x-heroicon-o-inbox class="h-12 w-12 mx-auto mb-2 opacity-50" />
                <p>No items available at this location.</p>
            </div>
            @else
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($this->sourceStock as $stock)
                <div class="flex items-center gap-4 p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                    <div class="flex-1">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $stock['item_name'] }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">SKU: {{ $stock['sku'] }}</p>
                    </div>

                    <div class="text-right">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Available: <strong>{{ number_format($stock['available']) }}</strong></p>
                    </div>

                    <!-- Transfer Qty Input -->
                    <div class="flex items-center gap-2">
                        <input type="number" wire:change="updateTransferQty({{ $stock['item_id'] }}, $event.target.value)"
                            value="{{ $stock['transferring'] }}"
                            min="0" max="{{ $stock['available'] }}"
                            placeholder="0"
                            class="w-20 px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-right focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        <!-- Transfer Summary -->
        @if($this->transferSummary)
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-300 mb-4">Transfer Summary</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-blue-700 dark:text-blue-400">Items to Transfer</p>
                    <p class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ $this->transferSummary['item_count'] }}</p>
                </div>
                <div>
                    <p class="text-sm text-blue-700 dark:text-blue-400">Total Quantity</p>
                    <p class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ number_format($this->transferSummary['total_quantity']) }}</p>
                </div>
                <div>
                    <p class="text-sm text-blue-700 dark:text-blue-400">Total Value</p>
                    <p class="text-2xl font-bold text-blue-900 dark:text-blue-300">${{ number_format($this->transferSummary['total_value'], 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3 justify-end">
            <button wire:click="clearSelection" class="px-6 py-2 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium">
                Clear Selection
            </button>

            <button wire:click="$toggle('showConfirm')" class="px-6 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg transition font-medium">
                Execute Transfer
            </button>
        </div>

        <!-- Confirmation Modal -->
        @if($showConfirm)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-8 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Confirm Transfer</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Transfer {{ number_format($this->transferSummary['total_quantity']) }} unit(s) across {{ $this->transferSummary['item_count'] }} item(s)?
                    <br/><strong class="text-primary-600">Total Value: ${{ number_format($this->transferSummary['total_value'], 2) }}</strong>
                </p>

                <div class="flex gap-3">
                    <button wire:click="$toggle('showConfirm')" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium">
                        Cancel
                    </button>
                    <button wire:click="executeTransfer" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg transition font-medium">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
        @endif
        @else
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 p-12 text-center">
            <x-heroicon-o-arrow-path class="h-12 w-12 text-gray-400 mx-auto mb-3" />
            <p class="text-gray-600 dark:text-gray-400">Select a source location to begin stock transfer</p>
        </div>
        @endif
    </div>
</x-filament-panels::page>

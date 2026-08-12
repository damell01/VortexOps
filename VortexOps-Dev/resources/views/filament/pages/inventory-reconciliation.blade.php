<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- LOCATION SELECTION                                                  --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <x-heroicon-o-map-pin class="h-5 w-5" />
                Select Location to Reconcile
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">Location</label>
                    <select wire:model="selectedLocationId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm">
                        <option value="">Select location…</option>
                        @foreach($this->getLocationsProperty() as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                @if($selectedLocationId)
                <div class="md:col-span-2 flex items-end gap-2">
                    <button wire:click="reconcileLocation" type="button"
                        class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
                        <x-heroicon-o-clipboard-document-check class="h-4 w-4 inline -mt-0.5 mr-2" />
                        Start Location Count
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- SINGLE ITEM RECONCILIATION                                          --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Reconcile Single Item</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">Location</label>
                    <select wire:model="selectedLocationId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm">
                        <option value="">Select location…</option>
                        @foreach($this->getLocationsProperty() as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">Item</label>
                    <select wire:model="selectedItemId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm">
                        <option value="">Select item…</option>
                        @foreach($this->getItemsProperty() as $item)
                        <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                        @endforeach
                    </select>
                </div>

                @if($selectedItemId && $selectedLocationId)
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">Physical Count</label>
                    <input type="number" wire:model="physicalCount" step="0.01"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" />
                </div>

                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block mb-2">Reason (Optional)</label>
                    <input type="text" wire:model="reconciliationReason" placeholder="e.g., Inventory audit"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" />
                </div>

                <div class="md:col-span-2 flex gap-2">
                    <button wire:click="reconcileItem" type="button"
                        class="flex-1 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700">
                        <x-heroicon-o-check-circle class="h-4 w-4 inline -mt-0.5 mr-1" />
                        Reconcile Item
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════════ --}}
        {{-- LOCATION STOCK SUMMARY                                              --}}
        {{-- ════════════════════════════════════════════════════════════════════ --}}
        @if($selectedLocationId && !$showResults)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Current Stock in Location</h3>

            @if(!empty($this->getLocationStockProperty()))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Item Name</th>
                            <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">SKU</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">System Qty</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Unit Cost</th>
                            <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getLocationStockProperty() as $stock)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-3 px-4 text-gray-900 dark:text-gray-100">{{ $stock['name'] }}</td>
                            <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $stock['sku'] }}</td>
                            <td class="py-3 px-4 text-right text-gray-900 dark:text-gray-100">{{ number_format($stock['system_qty'], 2) }}</td>
                            <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">${{ number_format($stock['avg_cost'], 2) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-gray-900 dark:text-gray-100">${{ number_format($stock['value'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-gray-500 dark:text-gray-400">No stock in this location</p>
            @endif
        </div>
        @endif

    </div>
</x-filament-panels::page>

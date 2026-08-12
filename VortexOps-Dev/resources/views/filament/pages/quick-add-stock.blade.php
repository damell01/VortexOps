<x-filament-panels::page>
    <div class="max-w-2xl mx-auto space-y-6">
        {{-- Product Scanner Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Scan Product</h2>

            {{-- Camera Component --}}
            <livewire:barcode-scanner lazy-loading />

            {{-- Manual Barcode Input --}}
            <div class="mt-4 space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Manual Entry</label>
                <div class="flex gap-2">
                    <input
                        wire:model="barcode"
                        type="text"
                        placeholder="Enter barcode or SKU"
                        class="flex-1 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                        style="min-height: 44px;"
                    />
                    <button
                        wire:click="scanBarcode"
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition active:opacity-75 touch-manipulation"
                        style="min-height: 44px;"
                    >
                        Search
                    </button>
                </div>
            </div>
        </div>

        {{-- Product Details Section --}}
        @if($product)
        <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-700 rounded-lg p-6">
            <div class="space-y-4">
                <div>
                    <p class="text-xs text-green-700 dark:text-green-400 uppercase font-semibold">Selected Product</p>
                    <h3 class="text-xl font-bold text-green-900 dark:text-green-100">{{ $product->name }}</h3>
                    <p class="text-sm text-green-600 dark:text-green-400 mt-1">
                        SKU: <span class="font-mono">{{ $product->sku }}</span>
                        @if($product->barcode)
                            | Barcode: <span class="font-mono">{{ $product->barcode }}</span>
                        @endif
                    </p>
                </div>

                {{-- Quantity Selector --}}
                <div class="border-t border-green-200 dark:border-green-700 pt-4">
                    <label class="block text-sm font-medium text-green-900 dark:text-green-100 mb-3">Quantity</label>
                    <div class="flex items-center gap-3">
                        <button
                            wire:click="decrementQuantity"
                            class="w-12 h-12 flex items-center justify-center bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-xl transition active:opacity-75 touch-manipulation"
                        >
                            −
                        </button>
                        <input
                            wire:model.number="quantity"
                            type="number"
                            min="1"
                            class="w-20 px-4 py-2 text-center text-lg font-bold rounded-lg border-2 border-green-600 dark:border-green-500 bg-white dark:bg-gray-700 text-green-900 dark:text-green-100"
                            style="min-height: 44px;"
                        />
                        <button
                            wire:click="incrementQuantity"
                            class="w-12 h-12 flex items-center justify-center bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-xl transition active:opacity-75 touch-manipulation"
                        >
                            +
                        </button>
                    </div>
                </div>

                {{-- Unit Cost --}}
                @if($unitCost !== null)
                <div class="border-t border-green-200 dark:border-green-700 pt-4">
                    <label class="block text-sm font-medium text-green-900 dark:text-green-100 mb-2">Unit Cost</label>
                    <input
                        wire:model.number="unitCost"
                        type="number"
                        step="0.01"
                        class="w-full px-4 py-2 rounded-lg border border-green-300 dark:border-green-600 bg-white dark:bg-gray-700 text-green-900 dark:text-green-100"
                        style="min-height: 44px;"
                    />
                </div>
                @endif

                {{-- Location Selector --}}
                <div class="border-t border-green-200 dark:border-green-700 pt-4">
                    <label class="block text-sm font-medium text-green-900 dark:text-green-100 mb-2">Storage Location</label>
                    <select
                        wire:model="locationId"
                        class="w-full px-4 py-2 rounded-lg border border-green-300 dark:border-green-600 bg-white dark:bg-gray-700 text-green-900 dark:text-green-100"
                        style="min-height: 44px;"
                    >
                        <option value="">Select location...</option>
                        @foreach($this->locations as $location)
                            <option value="{{ $location->id }}">
                                {{ $location->name }}
                                @if($location->type)
                                    ({{ ucfirst($location->type) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Add Button --}}
                <button
                    wire:click="addStock"
                    class="w-full mt-4 px-6 py-4 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition active:opacity-75 touch-manipulation text-lg"
                    style="min-height: 52px;"
                >
                    Add {{ (int)$quantity }} Unit{{ $quantity !== 1 ? 's' : '' }} to Stock
                </button>
            </div>
        </div>
        @else
        <div class="bg-gray-50 dark:bg-gray-800/50 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center">
            <p class="text-gray-500 dark:text-gray-400 text-lg">
                👇 Scan a barcode or enter an SKU to get started
            </p>
        </div>
        @endif

        {{-- Recent Additions --}}
        @if(!empty($recentAdds))
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Recent Additions</h3>
            <ul class="space-y-2">
                @foreach($recentAdds as $add)
                <li class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $add['product_name'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $add['quantity'] }} units → {{ $add['location'] }}
                        </p>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $add['timestamp'] }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    <script>
        document.addEventListener('scanSuccess', () => {
            if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
        });
        document.addEventListener('scanError', () => {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100, 50, 100]);
        });
    </script>
</x-filament-panels::page>

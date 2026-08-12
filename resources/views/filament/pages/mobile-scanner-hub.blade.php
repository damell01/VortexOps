<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Mode Selection -->
        @if(!$currentSession)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <button
                    wire:click="selectMode('inventory')"
                    class="p-6 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition text-left"
                >
                    <div class="text-3xl mb-2">📦</div>
                    <p class="font-bold text-gray-900 dark:text-white">Inventory</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Search, locate, manage stock</p>
                </button>

                <button
                    wire:click="selectMode('receiving')"
                    class="p-6 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-green-500 hover:bg-green-50 dark:hover:bg-green-900/20 transition text-left"
                >
                    <div class="text-3xl mb-2">📥</div>
                    <p class="font-bold text-gray-900 dark:text-white">Receiving</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Verify incoming shipments</p>
                </button>

                <button
                    wire:click="selectMode('shipping')"
                    class="p-6 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-purple-500 hover:bg-purple-50 dark:hover:bg-purple-900/20 transition text-left"
                >
                    <div class="text-3xl mb-2">📤</div>
                    <p class="font-bold text-gray-900 dark:text-white">Shipping</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Verify orders before packing</p>
                </button>

                <button
                    wire:click="selectMode('lookup')"
                    class="p-6 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-orange-500 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition text-left"
                >
                    <div class="text-3xl mb-2">🔍</div>
                    <p class="font-bold text-gray-900 dark:text-white">Lookup</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Quick item search & details</p>
                </button>
            </div>
        @else
            <!-- Active Scanning Session -->
            <div class="space-y-4">
                <!-- Session Header -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-300 dark:border-blue-700 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <p class="text-xs text-blue-600 dark:text-blue-400 font-semibold uppercase">{{ ucfirst($mode) }} Mode</p>
                            <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $totalScanned }} items scanned</p>
                        </div>
                        <button
                            wire:click="endSession"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition active:opacity-75 touch-manipulation"
                            style="min-height: 44px;"
                        >
                            End Scan
                        </button>
                    </div>
                    <p class="text-sm text-blue-700 dark:text-blue-300">{{ $sessionStatus }}</p>
                </div>

                <!-- Camera Scanner -->
                <livewire:barcode-scanner lazy-loading />

                <!-- Scanned Items List -->
                @if(!empty($scannedItems))
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                            <p class="font-semibold text-gray-900 dark:text-white">Items Scanned ({{ count($scannedItems) }})</p>
                        </div>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                            @foreach($scannedItems as $item)
                                <li class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <div class="flex justify-between items-start gap-3">
                                        <div class="flex-1">
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">SKU: {{ $item['sku'] }} | Qty: {{ $item['quantity'] }} | {{ $item['location'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">{{ $item['scanned_at'] }}</p>
                                        </div>
                                        <span class="text-2xl">✓</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Duplicates Warning -->
                @if(!empty($duplicates))
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border-2 border-yellow-300 dark:border-yellow-700 rounded-lg p-4">
                        <p class="font-semibold text-yellow-900 dark:text-yellow-100 mb-2">⚠️ Duplicate Scans</p>
                        <ul class="space-y-1">
                            @foreach($duplicates as $dup)
                                <li class="text-sm text-yellow-800 dark:text-yellow-200">
                                    {{ $dup['name'] }} ({{ $dup['scanned_at'] }})
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Session Actions -->
                <div class="flex gap-3">
                    <button
                        wire:click="clearSession"
                        class="flex-1 px-4 py-3 rounded-lg font-semibold bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-900 dark:text-white transition active:opacity-75 touch-manipulation"
                        style="min-height: 48px;"
                    >
                        Clear & Restart
                    </button>
                    <button
                        class="flex-1 px-4 py-3 rounded-lg font-semibold bg-green-600 hover:bg-green-700 text-white transition active:opacity-75 touch-manipulation"
                        style="min-height: 48px;"
                    >
                        Save & Review
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:updated', () => {
            // Vibrate on success
            window.addEventListener('scanSuccess', () => {
                if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
            });

            // Vibrate on error
            window.addEventListener('scanError', () => {
                if (navigator.vibrate) navigator.vibrate([100, 50, 100, 50, 100]);
            });

            // Vibrate on warning
            window.addEventListener('scanWarning', () => {
                if (navigator.vibrate) navigator.vibrate([100]);
            });
        });
    </script>
</x-filament-panels::page>

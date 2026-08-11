{{-- Container Scan Mode Only - Included in wrapper --}}
@if ($this->containerScanMode)
        <div class="space-y-6">
            {{-- Step 1: Container Setup --}}
            @if ($this->scanStep === 1)
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <h2 class="mb-4 text-xl font-semibold">Setup Container</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Container Name
                            </label>
                            <input
                                type="text"
                                wire:model="containerName"
                                wire:key="container-name"
                                placeholder="e.g., 20-Box Case, Booster Box Lot"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                @focus="$dispatch('focus-container-name')"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Container Barcode (Optional)
                            </label>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    wire:model="containerBarcode"
                                    placeholder="Barcode for this case/container"
                                    class="flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    id="container-barcode-setup"
                                >
                                <button
                                    type="button"
                                    onclick="window.dispatchEvent(new Event('open-camera-scanner'))"
                                    class="rounded-lg bg-blue-600 px-3 py-2 text-white hover:bg-blue-700 active:scale-95 transition-transform"
                                    title="Open camera scanner"
                                >
                                    📷
                                </button>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button
                                wire:click="startScanning"
                                class="flex-1 rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700"
                            >
                                Start Scanning Items
                            </button>
                            <button
                                wire:click="disableContainerScan"
                                class="rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Step 2: Scan Items --}}
            @if ($this->scanStep === 2)
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <h2 class="mb-2 text-xl font-semibold">{{ $this->containerName }}</h2>
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                        Scan barcodes for items inside this container
                    </p>

                    {{-- Barcode Input with Camera Button --}}
                    <div class="mb-6">
                        <div class="relative flex gap-2">
                            <input
                                type="text"
                                wire:model.debounce-500ms="barcodeInput"
                                wire:key="barcode-input"
                                placeholder="Scan barcode here..."
                                class="flex-1 rounded-lg border-2 border-gray-300 bg-white px-4 py-3 text-lg text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                @keydown.enter="$wire.dispatch('submit-barcode')"
                                @focus="$dispatch('focus-barcode-input')"
                                autofocus
                                id="barcode-input-container-scan"
                            >
                            <button
                                type="button"
                                onclick="window.dispatchEvent(new Event('open-camera-scanner'))"
                                class="rounded-lg bg-blue-600 px-4 py-3 text-white hover:bg-blue-700 active:scale-95 transition-transform"
                                title="Open camera scanner (Alt+C)"
                            >
                                📷
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Press Enter, click 📷, or scan with barcode scanner
                        </p>
                    </div>

                    {{-- Scanned Items List --}}
                    @if (!empty($this->scannedItems))
                        <div class="mb-6">
                            <h3 class="mb-3 font-semibold text-gray-900 dark:text-white">
                                {{ count($this->scannedItems) }} item(s) scanned
                            </h3>
                            <div class="space-y-2 max-h-64 overflow-y-auto">
                                @foreach ($this->scannedItems as $item)
                                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                SKU: {{ $item['sku'] }} • Barcode: {{ $item['barcode'] }}
                                            </p>
                                        </div>
                                        <button
                                            wire:click="removeScannedItem('{{ $item['id'] }}')"
                                            type="button"
                                            class="ml-2 rounded-lg bg-red-100 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="flex gap-3 pt-4">
                        <button
                            wire:click="finishScanning"
                            class="flex-1 rounded-lg bg-green-600 px-4 py-2 font-semibold text-white hover:bg-green-700"
                        >
                            Review & Create ({{ count($this->scannedItems) }} items)
                        </button>
                        <button
                            wire:click="disableContainerScan"
                            class="rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            @endif

            {{-- Step 3: Review --}}
            @if ($this->scanStep === 3)
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <h2 class="mb-4 text-xl font-semibold">Review Items</h2>

                    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/30">
                            <p class="text-xs font-semibold text-blue-600 dark:text-blue-400">CONTAINER</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $this->containerName }}</p>
                        </div>
                        <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/30">
                            <p class="text-xs font-semibold text-green-600 dark:text-green-400">ITEMS SCANNED</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ count($this->scannedItems) }}</p>
                        </div>
                        @if ($this->containerBarcode)
                            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                                <p class="text-xs font-semibold text-gray-600 dark:text-gray-400">CONTAINER BARCODE</p>
                                <p class="text-sm font-mono text-gray-900 dark:text-white">{{ $this->containerBarcode }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Items Table --}}
                    <div class="mb-6 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">#</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">Item Name</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">SKU</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900 dark:text-white">Barcode</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->scannedItems as $index => $item)
                                    <tr class="border-b border-gray-200 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                        <td class="px-4 py-3 text-gray-500">{{ $index + 1 }}</td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</td>
                                        <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-400">{{ $item['sku'] }}</td>
                                        <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-400">{{ $item['barcode'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex gap-3">
                        <button
                            wire:click="createContainerWithItems"
                            class="flex-1 rounded-lg bg-green-600 px-4 py-2 font-semibold text-white hover:bg-green-700"
                        >
                            ✓ Create Container & Items
                        </button>
                        <button
                            wire:click="goBackToScanning"
                            class="rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Back to Scanning
                        </button>
                        <button
                            wire:click="disableContainerScan"
                            class="rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            @endif
        </div>
@endif

<script>
// Handle barcode scanned from camera and submit it
document.addEventListener('barcode-scanned', function(e) {
    const input = document.getElementById('barcode-input-container-scan');
    if (input && e.detail?.value) {
        // Small delay to ensure camera scanner closes first
        setTimeout(() => {
            input.focus();
            @this.dispatch('submit-barcode');
        }, 150);
    }
});

// Keyboard shortcuts for Enter key
document.addEventListener('keydown', function(e) {
    if (e.code === 'Enter' && document.activeElement.id === 'barcode-input-container-scan') {
        e.preventDefault();
        @this.dispatch('submit-barcode');
    }
});
</script>

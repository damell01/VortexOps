@php
    use Filament\Support\Enums\MaxWidth;
@endphp

<x-filament-panels::page>
    <div class="mx-auto max-w-2xl">
        <!-- Progress Indicator -->
        <div class="mb-8 flex justify-between">
            @for ($i = 1; $i <= 3; $i++)
                <div class="flex flex-col items-center flex-1">
                    <div class="flex items-center w-full">
                        @if ($i > 1)
                            <div class="flex-1 h-0.5 {{ $i <= $this->currentStep ? 'bg-success-500' : 'bg-gray-300' }}"></div>
                        @endif
                        <div class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full {{ $i <= $this->currentStep ? 'bg-success-500 text-white' : 'bg-gray-300 text-gray-600' }}">
                            @if ($i < $this->currentStep)
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            @else
                                {{ $i }}
                            @endif
                        </div>
                        @if ($i < 3)
                            <div class="flex-1 h-0.5 {{ $i < $this->currentStep ? 'bg-success-500' : 'bg-gray-300' }}"></div>
                        @endif
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-600">
                        @if ($i === 1) Item Details
                        @elseif ($i === 2) Add Stock
                        @else Review
                        @endif
                    </span>
                </div>
            @endfor
        </div>

        <!-- Form -->
        <div class="space-y-6">
            <!-- Step 1: Item Details -->
            @if ($this->currentStep === 1)
            <div class="space-y-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-lg font-semibold">Step 1: Item Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                        <input type="text" wire:model="data.name" placeholder="e.g., 2024 Topps Chrome Box" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500" autofocus />
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Barcode/UPC (optional)</label>
                        <div class="flex gap-2 mt-1">
                            <input type="text" wire:model="data.barcode" placeholder="Scan barcode or enter UPC" class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500" />
                            <button type="button" wire:click="scanBarcode()" class="px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition font-medium text-sm">📷 Scan</button>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
                        <input type="text" wire:model="data.sku" placeholder="Auto-generated" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                        <select wire:model="data.category" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500">
                            <option value="">Select or type category...</option>
                            @php
                                $categories = \App\Models\InventoryItem::whereNotNull('category')->distinct()->pluck('category')->sort();
                            @endphp
                            @foreach($categories as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Unit Cost</label>
                        <input type="number" step="0.01" wire:model="data.unit_cost" placeholder="0.00" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div class="col-span-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Vendor (optional)</label>
                        <select wire:model="data.preferred_vendor_id" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500">
                            <option value="">Select a vendor...</option>
                            @foreach(\App\Models\Vendor::activeOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            @endif

            <!-- Step 2: Add Stock -->
            @if ($this->currentStep === 2)
            <div class="space-y-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-lg font-semibold">Step 2: Add Stock</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Stock Location</label>
                        <select wire:model="data.location_id" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500">
                            <option value="">Select location...</option>
                            @foreach(\App\Models\InventoryLocation::activeOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                        <input type="number" step="0.01" wire:model="data.quantity" placeholder="0" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Cost for Stock</label>
                        <input type="number" step="0.01" wire:model="data.cost" placeholder="Leave blank to use unit cost" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-primary-500 focus:ring-2 focus:ring-primary-500" />
                    </div>
                </div>
            </div>
            @endif

            <!-- Step 3: Review & Add -->
            @if ($this->currentStep === 3)
            <div class="space-y-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6">
                <h3 class="text-lg font-semibold">Step 3: Review & Add</h3>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $data['name'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $data['sku'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $data['category'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Unit Cost</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">${{ $data['unit_cost'] ?? '0.00' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Location</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">
                            @php
                                $location = \App\Models\InventoryLocation::find($data['location_id'] ?? null);
                            @endphp
                            {{ $location?->name ?? 'Not selected' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $data['quantity'] ?? '0' }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Navigation Buttons -->
            <div class="flex justify-between gap-4 pt-8 mt-8 border-t border-gray-200 dark:border-gray-700">
                @if ($this->currentStep > 1)
                    <button
                        type="button"
                        wire:click="dispatch('previous-step')"
                        class="px-6 py-3 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium"
                    >
                        ← Back
                    </button>
                @else
                    <div></div>
                @endif

                @if ($this->currentStep < 3)
                    <button
                        type="button"
                        wire:click="dispatch('next-step')"
                        class="px-8 py-3 bg-primary-600 dark:bg-primary-600 text-white rounded-lg hover:bg-primary-700 dark:hover:bg-primary-700 active:bg-primary-800 transition font-bold text-lg shadow-md"
                    >
                        Next →
                    </button>
                @else
                    <div class="flex gap-4 ml-auto">
                        <button
                            type="button"
                            wire:click="dispatch('previous-step')"
                            class="px-6 py-3 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium"
                        >
                            ← Back
                        </button>
                        <button
                            type="button"
                            wire:click="dispatch('submit-wizard')"
                            class="px-8 py-3 bg-success-600 dark:bg-success-600 text-white rounded-lg hover:bg-success-700 dark:hover:bg-success-700 active:bg-success-800 transition font-bold text-lg shadow-md"
                        >
                            ✓ Add to Inventory
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Hidden Camera Container for Barcode Scanning -->
    <div id="quickadd-camera-container" class="hidden fixed inset-0 bg-black z-50 flex flex-col">
        <div class="flex-1 flex items-center justify-center">
            <video id="quickadd-camera-video" class="w-full h-full object-cover"></video>
        </div>
        <div class="bg-gray-900 p-4 flex justify-between items-center">
            <div class="text-white text-sm">Point camera at barcode</div>
            <button type="button" id="quickadd-camera-stop-btn" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">Close Camera</button>
        </div>
    </div>

    <script>
        (function() {
            let scanning = false;
            let codeReader = null;
            let stream = null;

            function getInput() {
                return document.querySelector('input[wire\\:model="data.barcode"]');
            }

            async function handleCameraClick() {
                try {
                    if (scanning) {
                        console.log('Already scanning');
                        return;
                    }

                    const input = getInput();
                    if (!input) {
                        alert('Barcode input field not found');
                        return;
                    }

                    const container = document.getElementById('quickadd-camera-container');
                    const video = document.getElementById('quickadd-camera-video');

                    if (!container || !video) {
                        alert('Camera elements not found');
                        return;
                    }

                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                        stream = null;
                    }

                    if (codeReader) {
                        try {
                            codeReader.reset();
                        } catch (e) {}
                    }

                    container.classList.remove('hidden');
                    scanning = true;

                    // Initialize barcode reader
                    if (!window.barcodeScanner?.BrowserMultiFormatReader) {
                        alert('Barcode scanner library not loaded');
                        scanning = false;
                        container.classList.add('hidden');
                        return;
                    }

                    codeReader = new window.barcodeScanner.BrowserMultiFormatReader();

                    // Start scanning
                    const result = await codeReader.decodeFromVideoDevice(undefined, video, (result, err) => {
                        if (result) {
                            const barcode = result.text;
                            input.value = barcode;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));

                            // Close camera
                            scanning = false;
                            if (codeReader) {
                                try {
                                    codeReader.reset();
                                } catch (e) {}
                                codeReader = null;
                            }
                            if (stream) {
                                stream.getTracks().forEach(track => track.stop());
                                stream = null;
                            }
                            container.classList.add('hidden');
                        }
                    });

                } catch (error) {
                    console.error('Camera error:', error);
                    scanning = false;
                    const container = document.getElementById('quickadd-camera-container');
                    if (container) container.classList.add('hidden');
                    alert('Error opening camera: ' + error.message);
                }
            }

            // Bind scan button
            function bindScanButton() {
                const scanBtn = document.querySelector('button[wire\\:click="scanBarcode()"]');
                if (scanBtn) {
                    scanBtn.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        handleCameraClick();
                    };
                }
            }

            // Bind stop button
            function bindStopButton() {
                const stopBtn = document.getElementById('quickadd-camera-stop-btn');
                if (stopBtn && !stopBtn.dataset.bound) {
                    stopBtn.dataset.bound = '1';
                    stopBtn.addEventListener('click', () => {
                        scanning = false;
                        if (codeReader) {
                            try {
                                codeReader.reset();
                            } catch (e) {}
                            codeReader = null;
                        }
                        if (stream) {
                            stream.getTracks().forEach(track => track.stop());
                            stream = null;
                        }
                        const container = document.getElementById('quickadd-camera-container');
                        if (container) container.classList.add('hidden');
                    });
                }
            }

            // Initialize
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    bindScanButton();
                    bindStopButton();
                });
            } else {
                bindScanButton();
                bindStopButton();
            }

            // Rebind after Livewire updates
            document.addEventListener('livewire:updated', () => {
                setTimeout(() => {
                    bindScanButton();
                }, 100);
            });
        })();
    </script>
</x-filament-panels::page>

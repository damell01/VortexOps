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
                            <input
                                type="text"
                                wire:model="containerBarcode"
                                placeholder="Barcode for this case/container"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                            >
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
                                id="barcode-input-scan"
                            >
                            <button
                                type="button"
                                onclick="openCameraScanner()"
                                class="rounded-lg bg-blue-600 px-4 py-3 text-white hover:bg-blue-700 active:scale-95 transition-transform"
                                title="Open camera scanner (Alt+C)"
                            >
                                📷
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Press Enter, click 📷, or scan with barcode scanner • Alt+C to toggle camera
                        </p>
                    </div>

                    {{-- Camera Scanner Modal --}}
                    <div id="camera-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 dark:bg-black/70">
                        <div class="w-full max-w-md rounded-lg bg-white p-4 dark:bg-gray-800">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Camera Scanner</h3>
                                <button
                                    type="button"
                                    onclick="closeCameraScanner()"
                                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    ✕
                                </button>
                            </div>
                            <div id="scanner-container" class="aspect-video w-full rounded-lg bg-gray-100 dark:bg-gray-700"></div>
                            <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
                                Point camera at barcode • Press Esc to close
                            </p>
                        </div>
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
let quaggaStarted = false;

function playBeep() {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gain = audioContext.createGain();
    oscillator.connect(gain);
    gain.connect(audioContext.destination);
    oscillator.frequency.value = 1000;
    oscillator.type = 'sine';
    gain.gain.setValueAtTime(0.3, audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.1);
}

function vibrate() {
    if (navigator.vibrate) {
        navigator.vibrate(100);
    }
}

function openCameraScanner() {
    const modal = document.getElementById('camera-modal');
    const container = document.getElementById('scanner-container');

    modal.classList.remove('hidden');

    if (!quaggaStarted) {
        vibrate();

        Quagga.init({
            inputStream: {
                type: 'LiveStream',
                constraints: {
                    width: { min: 640 },
                    height: { min: 480 },
                    facingMode: 'environment',
                    aspectRatio: { min: 1, max: 2 }
                },
                target: container
            },
            decoder: {
                readers: ['code128_reader', 'ean_reader', 'ean_8_reader', 'upc_reader', 'code39_reader']
            },
            locator: {
                halfSample: true
            }
        }, function(err) {
            if (err) {
                console.error('Quagga init error:', err);
                alert('Camera error: ' + err.message);
                closeCameraScanner();
                return;
            }
            Quagga.start();
            quaggaStarted = true;
        });

        Quagga.onDetected(function(data) {
            const code = data.codeResult.code;
            playBeep();
            vibrate();

            document.getElementById('barcode-input-scan').value = code;
            const event = new Event('input', { bubbles: true });
            document.getElementById('barcode-input-scan').dispatchEvent(event);

            @this.dispatch('submit-barcode');

            setTimeout(() => {
                document.getElementById('barcode-input-scan').focus();
            }, 100);
        });
    }
}

function closeCameraScanner() {
    const modal = document.getElementById('camera-modal');
    modal.classList.add('hidden');

    if (quaggaStarted) {
        Quagga.stop();
        quaggaStarted = false;
    }

    document.getElementById('barcode-input-scan').focus();
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.altKey && e.code === 'KeyC') {
        e.preventDefault();
        if (document.getElementById('camera-modal').classList.contains('hidden')) {
            openCameraScanner();
        } else {
            closeCameraScanner();
        }
    }

    if (e.code === 'Escape') {
        closeCameraScanner();
    }

    if (e.code === 'Enter' && document.activeElement.id === 'barcode-input-scan') {
        e.preventDefault();
        @this.dispatch('submit-barcode');
    }
});

// Cleanup on page leave
window.addEventListener('beforeunload', function() {
    if (quaggaStarted) {
        Quagga.stop();
    }
});
</script>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.css">
<script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>

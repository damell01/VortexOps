<x-filament-panels::page>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col safe-area-inset-bottom" style="height: 100dvh;">
        <!-- Mode Selector (Top Navigation) - Mobile First -->
        <div class="sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-md safe-area-inset-top">
            <div class="grid grid-cols-3 gap-0">
                <button
                    wire:click="$set('mode', 'receive')"
                    @click="document.getElementById('scanInput').focus()"
                    class="flex-1 py-5 px-3 font-semibold text-center transition active:opacity-75 touch-manipulation {{ $mode === 'receive' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}"
                    style="min-height: 60px; display: flex; flex-direction: column; justify-content: center; align-items: center;"
                >
                    <div class="text-2xl">📦</div>
                    <div class="text-sm mt-1 font-medium">Receive</div>
                </button>
                <button
                    wire:click="$set('mode', 'quickadd')"
                    @click="document.getElementById('scanInput').focus()"
                    class="flex-1 py-5 px-3 font-semibold text-center transition active:opacity-75 touch-manipulation {{ $mode === 'quickadd' ? 'bg-green-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}"
                    style="min-height: 60px; display: flex; flex-direction: column; justify-content: center; align-items: center;"
                >
                    <div class="text-2xl">➕</div>
                    <div class="text-sm mt-1 font-medium">Add Stock</div>
                </button>
                <button
                    wire:click="$set('mode', 'lookup')"
                    @click="document.getElementById('scanInput').focus()"
                    class="flex-1 py-5 px-3 font-semibold text-center transition active:opacity-75 touch-manipulation {{ $mode === 'lookup' ? 'bg-purple-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}"
                    style="min-height: 60px; display: flex; flex-direction: column; justify-content: center; align-items: center;"
                >
                    <div class="text-2xl">🔍</div>
                    <div class="text-sm mt-1 font-medium">Lookup</div>
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-y-auto" style="padding-bottom: 120px;">
            <!-- Camera Section -->
            <div class="bg-gray-900 p-4">
                <div id="cameraContainer" class="bg-gray-800 rounded-lg border-2 border-gray-700 w-full flex flex-col items-center justify-center text-white" style="aspect-ratio: 16/9; min-height: 240px;">
                    <video id="cameraFeed" class="w-full h-full rounded-lg object-cover hidden" style="aspect-ratio: 16/9;"></video>
                    <div id="cameraPlaceholder" class="text-center w-full">
                        <div class="text-6xl mb-2">📷</div>
                        <p class="text-base mb-4">Point camera at barcode</p>
                        <button
                            id="startCameraBtn"
                            type="button"
                            class="bg-blue-500 hover:bg-blue-600 active:opacity-75 text-white font-bold px-6 rounded text-base transition touch-manipulation inline-flex items-center justify-center"
                            style="min-height: 48px; min-width: 140px;"
                        >
                            Start Camera
                        </button>
                        <p id="cameraStatus" class="text-xs text-gray-400 mt-3">Ready to scan</p>
                    </div>
                </div>
            </div>

            <!-- Mode: RECEIVE (Pallet) -->
            @if($mode === 'receive')
                <div class="p-4 space-y-4">
                    <!-- Pallet Selection -->
                    @if(!$activeSessionId)
                        <div class="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <label class="block text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
                                Select Pallet to Receive
                            </label>
                            <select
                                wire:model="activePalletId"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded px-3 py-2 text-gray-900 dark:text-white"
                            >
                                <option value="">Choose a pallet...</option>
                                @foreach($this->getPalletsProperty as $pallet)
                                    <option value="{{ $pallet->id }}">
                                        {{ $pallet->reference }} ({{ ucfirst($pallet->status) }})
                                    </option>
                                @endforeach
                            </select>
                            @if($activePalletId)
                                <button
                                    wire:click="startReceiving"
                                    class="w-full mt-3 bg-blue-500 hover:bg-blue-600 active:opacity-75 text-white font-bold px-4 rounded-lg transition text-base touch-manipulation"
                                    style="min-height: 52px; display: flex; align-items: center; justify-content: center;"
                                >
                                    ▶ Start Receiving
                                </button>
                            @endif
                        </div>
                    @endif

                    <!-- Active Session Display -->
                    @if($activeSessionId)
                        <div class="bg-green-50 dark:bg-green-950 border-2 border-green-400 dark:border-green-600 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3 gap-3">
                                <div class="flex-1">
                                    <p class="text-sm text-green-700 dark:text-green-300">Session Active</p>
                                    <p class="text-2xl font-bold text-green-900 dark:text-green-100">
                                        {{ count($sessionItems) }} items scanned
                                    </p>
                                </div>
                                <button
                                    wire:click="endSession"
                                    class="bg-red-500 hover:bg-red-600 active:opacity-75 text-white font-bold px-4 rounded text-base transition touch-manipulation whitespace-nowrap"
                                    style="min-height: 44px; display: flex; align-items: center; justify-content: center;"
                                >
                                    ⊗ End
                                </button>
                            </div>

                            @if($this->getActiveSessionProperty)
                                <div class="text-xs text-green-700 dark:text-green-300 space-y-1">
                                    <p>Started: {{ $this->getActiveSessionProperty->started_at?->format('H:i') }}</p>
                                </div>
                            @endif
                        </div>

                        <!-- Scanned Items List -->
                        @if(!empty($sessionItems))
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Items Scanned</h3>
                                <div class="space-y-2 max-h-40 overflow-y-auto">
                                    @foreach($sessionItems as $item)
                                        <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700 p-2 rounded text-sm">
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-900 dark:text-white">{{ $item['name'] }}</p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['qty'] }} units</p>
                                            </div>
                                            <p class="text-right font-semibold text-gray-900 dark:text-white text-sm">${{ $item['total_cost'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            @endif

            <!-- Mode: QUICK ADD (Location) -->
            @if($mode === 'quickadd')
                <div class="p-4 space-y-4">
                    <!-- Location Selection -->
                    <div class="bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <label class="block text-sm font-semibold text-green-900 dark:text-green-100 mb-2">
                            Stock Location
                        </label>
                        <select
                            wire:model="qaLocationId"
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded px-3 py-2 text-gray-900 dark:text-white mb-3"
                        >
                            <option value="">Choose location...</option>
                            @foreach($this->getLocationsProperty as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>

                        <label class="block text-sm font-semibold text-green-900 dark:text-green-100 mb-2">
                            Quantity to Add
                        </label>
                        <div class="flex gap-3 mb-2">
                            <button
                                wire:click="$set('qaQty', (float)$qaQty - 1)"
                                class="bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white font-bold rounded text-2xl active:opacity-75 touch-manipulation flex items-center justify-center"
                                style="min-width: 48px; min-height: 48px;"
                            >
                                −
                            </button>
                            <input
                                type="number"
                                wire:model="qaQty"
                                step="0.01"
                                class="flex-1 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 rounded px-4 py-3 text-center text-lg font-semibold text-gray-900 dark:text-white focus:border-green-500 focus:ring-2 focus:ring-green-300 touch-manipulation"
                                style="min-height: 48px;"
                            >
                            <button
                                wire:click="$set('qaQty', (float)$qaQty + 1)"
                                class="bg-gray-300 dark:bg-gray-600 text-gray-900 dark:text-white font-bold rounded text-2xl active:opacity-75 touch-manipulation flex items-center justify-center"
                                style="min-width: 48px; min-height: 48px;"
                            >
                                +
                            </button>
                        </div>
                        <p class="text-sm text-green-700 dark:text-green-300">Currently: <strong>{{ $qaQty }} units</strong></p>
                    </div>

                    <!-- Helpful Text -->
                    <div class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-lg p-4 text-sm text-amber-900 dark:text-amber-100">
                        <p class="font-semibold mb-1">How to use:</p>
                        <ol class="list-decimal list-inside space-y-1 text-xs">
                            <li>Select a stock location</li>
                            <li>Set quantity to add</li>
                            <li>Scan item barcode</li>
                        </ol>
                    </div>
                </div>
            @endif

            <!-- Mode: LOOKUP (Item Info) -->
            @if($mode === 'lookup')
                <div class="p-4 space-y-4">
                    <div class="bg-purple-50 dark:bg-purple-950 border border-purple-200 dark:border-purple-800 rounded-lg p-4 text-center text-purple-900 dark:text-purple-100">
                        <p class="text-sm font-semibold mb-1">Item Lookup Mode</p>
                        <p class="text-xs">Scan a barcode to view item details and inventory status</p>
                    </div>
                </div>
            @endif

            <!-- Item Detail Modal Overlay -->
            @if($showItemDetail && $scannedItem)
                <div
                    class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 touch-manipulation"
                    @click="$wire.closeItemDetail()"
                    style="top: 0; left: 0; right: 0; bottom: 0;"
                >
                    <div
                        class="bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-lg shadow-2xl w-full sm:max-w-sm max-h-96 overflow-y-auto"
                        @click.stop
                        style="max-height: 80vh;"
                    >
                        <!-- Close Button -->
                        <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800">
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white">Item Details</h3>
                            <button
                                wire:click="closeItemDetail"
                                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 text-3xl leading-none w-10 h-10 flex items-center justify-center active:opacity-75 touch-manipulation"
                                style="min-width: 44px; min-height: 44px;"
                            >
                                ✕
                            </button>
                        </div>

                        <div class="p-4 space-y-4">
                            <!-- Item Name & SKU -->
                            <div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 font-semibold uppercase">Item</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $scannedItem['name'] }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">SKU: {{ $scannedItem['sku'] }}</p>
                            </div>

                            <!-- Quantities & Costs -->
                            <div class="grid grid-cols-2 gap-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                                @if(isset($scannedItem['cases']))
                                    <div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-semibold">Cases</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $scannedItem['cases'] }}</p>
                                    </div>
                                @endif

                                <div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 font-semibold">Quantity</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $scannedItem['qty'] }}</p>
                                </div>

                                @if(isset($scannedItem['unit_cost']))
                                    <div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-semibold">Unit Cost</p>
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">${{ $scannedItem['unit_cost'] }}</p>
                                    </div>
                                @endif

                                @if(isset($scannedItem['total_cost']))
                                    <div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-semibold">Total</p>
                                        <p class="text-lg font-bold text-green-600 dark:text-green-400">${{ $scannedItem['total_cost'] }}</p>
                                    </div>
                                @endif

                                @if(isset($scannedItem['avg_cost']))
                                    <div class="col-span-2">
                                        <p class="text-xs text-gray-600 dark:text-gray-400 font-semibold">Average Cost</p>
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">${{ $scannedItem['avg_cost'] }}</p>
                                    </div>
                                @endif
                            </div>

                            <!-- Lookup Mode Extra Info -->
                            @if($mode === 'lookup' && isset($scannedItem['value']))
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-2">
                                    <div class="flex justify-between">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Inventory Value:</p>
                                        <p class="font-semibold text-gray-900 dark:text-white">${{ $scannedItem['value'] }}</p>
                                    </div>
                                    @if(isset($scannedItem['reorder']))
                                        <div class="flex justify-between">
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Reorder Level:</p>
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $scannedItem['reorder'] }}</p>
                                        </div>
                                    @endif
                                    @if(isset($scannedItem['is_low']))
                                        <div class="flex justify-between items-center">
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Status:</p>
                                            <span class="{{ $scannedItem['is_low'] ? 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-100' : 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100' }} px-3 py-1 rounded text-xs font-semibold">
                                                {{ $scannedItem['is_low'] ? '⚠ Low Stock' : '✓ In Stock' }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        <div class="p-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                            <button
                                wire:click="closeItemDetail"
                                class="w-full bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 active:opacity-75 text-gray-900 dark:text-white font-bold py-4 rounded-lg transition touch-manipulation"
                                style="min-height: 48px;"
                            >
                                Close & Scan Next
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Fixed Bottom Scanner Input -->
        <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t-2 border-gray-200 dark:border-gray-700 p-4 shadow-2xl safe-area-inset-bottom z-30" style="max-height: 120px;">
            <input
                type="text"
                id="scanInput"
                wire:model.live="scanInput"
                wire:keydown.enter="submitScan"
                placeholder="Scan barcode..."
                class="w-full text-center bg-gray-100 dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 rounded-lg px-4 py-3 text-base font-semibold text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-300 focus:bg-white dark:focus:bg-gray-600 touch-manipulation"
                style="min-height: 48px; font-size: 16px;"
                autofocus
                autocomplete="off"
            >
        </div>
    </div>

    <style>
        /* Mobile viewport optimization */
        @media (max-width: 640px) {
            body { font-size: 16px; } /* Prevent iOS zoom on input focus */
        }

        /* Landscape mode adjustments */
        @media (max-height: 600px) {
            .fixed.bottom-0 {
                max-height: 30vh;
                overflow-y-auto;
            }
        }

        /* High contrast mode for warehouse lighting */
        @media (prefers-contrast: more) {
            .bg-blue-50 { background-color: rgb(0, 100, 200); color: white; }
            .bg-green-50 { background-color: rgb(0, 150, 0); color: white; }
            .bg-purple-50 { background-color: rgb(100, 0, 150); color: white; }
        }
    </style>

    <script>
        let barcodeDetector = null;
        let cameraStream = null;
        let isScanning = false;
        let lastScannedCode = null;
        let scanTimeout = null;

        // Initialize BarcodeDetector
        async function initBarcodeDetector() {
            if ('BarcodeDetector' in window) {
                try {
                    barcodeDetector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'qr_code'] });
                    console.log('BarcodeDetector initialized');
                    return true;
                } catch (e) {
                    console.warn('BarcodeDetector error:', e);
                    updateCameraStatus('Camera API not available', 'warning');
                    return false;
                }
            } else {
                console.warn('BarcodeDetector not supported');
                updateCameraStatus('Barcode detection not supported', 'warning');
                return false;
            }
        }

        // Start camera feed
        async function startCamera() {
            try {
                updateCameraStatus('Starting camera...', 'info');

                if (!navigator.mediaDevices?.getUserMedia) {
                    updateCameraStatus('Camera not available', 'error');
                    return;
                }

                const constraints = {
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };

                cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
                const video = document.getElementById('cameraFeed');
                video.srcObject = cameraStream;

                // Wait for video to be ready
                await new Promise(resolve => {
                    video.onloadedmetadata = () => {
                        video.play();
                        resolve();
                    };
                });

                // Hide placeholder, show video
                document.getElementById('cameraPlaceholder').classList.add('hidden');
                video.classList.remove('hidden');
                document.getElementById('startCameraBtn').textContent = 'Stop Camera';

                isScanning = true;
                updateCameraStatus('Camera active - Scanning...', 'success');
                scanBarcodesFromCamera();

            } catch (error) {
                console.error('Camera error:', error);
                let errorMsg = 'Camera failed to start';
                if (error.name === 'NotAllowedError') {
                    errorMsg = 'Camera permission denied';
                } else if (error.name === 'NotFoundError') {
                    errorMsg = 'Camera not found';
                }
                updateCameraStatus(errorMsg, 'error');
            }
        }

        // Stop camera feed
        function stopCamera() {
            isScanning = false;
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }

            const video = document.getElementById('cameraFeed');
            video.classList.add('hidden');
            document.getElementById('cameraPlaceholder').classList.remove('hidden');
            document.getElementById('startCameraBtn').textContent = 'Start Camera';
            updateCameraStatus('Camera stopped', 'info');
        }

        // Continuous barcode scanning
        async function scanBarcodesFromCamera() {
            const video = document.getElementById('cameraFeed');

            while (isScanning && cameraStream) {
                try {
                    if (barcodeDetector && video.readyState === video.HAVE_ENOUGH_DATA) {
                        const barcodes = await barcodeDetector.detect(video);

                        if (barcodes.length > 0) {
                            const barcode = barcodes[0];
                            const code = barcode.rawValue;

                            // Avoid duplicate scans within 500ms
                            if (code !== lastScannedCode) {
                                lastScannedCode = code;

                                // Visual feedback
                                flashCamera('success');
                                playBeep('success');

                                // Submit scan
                                const input = document.getElementById('scanInput');
                                input.value = code;
                                input.dispatchEvent(new Event('input', { bubbles: true }));

                                // Trigger submit via Livewire
                                setTimeout(() => {
                                    const event = new KeyboardEvent('keydown', {
                                        key: 'Enter',
                                        code: 'Enter',
                                        keyCode: 13,
                                        bubbles: true
                                    });
                                    input.dispatchEvent(event);
                                }, 50);

                                // Reset after timeout
                                if (scanTimeout) clearTimeout(scanTimeout);
                                scanTimeout = setTimeout(() => {
                                    lastScannedCode = null;
                                }, 500);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Barcode detection error:', error);
                }

                // Small delay before next scan
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }

        // Flash effect for scan feedback
        function flashCamera(type) {
            const container = document.getElementById('cameraContainer');
            const color = type === 'success' ? 'bg-green-500' : 'bg-red-500';

            container.classList.add(color);
            setTimeout(() => container.classList.remove(color), 200);
        }

        // Audio feedback
        function playBeep(type) {
            try {
                // Try using Web Audio API if available
                if (window.AudioContext || window.webkitAudioContext) {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gain = audioContext.createGain();

                    oscillator.connect(gain);
                    gain.connect(audioContext.destination);

                    if (type === 'success') {
                        oscillator.frequency.value = 800;
                        gain.gain.setValueAtTime(0.1, audioContext.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
                        oscillator.start(audioContext.currentTime);
                        oscillator.stop(audioContext.currentTime + 0.1);
                    } else {
                        oscillator.frequency.value = 400;
                        gain.gain.setValueAtTime(0.05, audioContext.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
                        oscillator.start(audioContext.currentTime);
                        oscillator.stop(audioContext.currentTime + 0.2);
                    }
                }
            } catch (e) {
                // Fallback: try using navigator.vibrate if available
                if (navigator.vibrate) {
                    if (type === 'success') {
                        navigator.vibrate([50, 30, 50]); // Success pattern
                    } else {
                        navigator.vibrate([100]); // Error pattern
                    }
                }
            }
        }

        // Update camera status text
        function updateCameraStatus(message, type = 'info') {
            const status = document.getElementById('cameraStatus');
            if (status) {
                status.textContent = message;
                status.className = `text-xs mt-2 ${
                    type === 'error' ? 'text-red-400' :
                    type === 'success' ? 'text-green-400' :
                    type === 'warning' ? 'text-yellow-400' :
                    'text-gray-400'
                }`;
            }
        }

        // Camera button handler
        document.getElementById('startCameraBtn')?.addEventListener('click', () => {
            if (isScanning) {
                stopCamera();
            } else {
                startCamera();
            }
        });

        // Session state persistence
        const SESSION_STATE_KEY = 'mobile_scanner_state';

        function saveSessionState() {
            const state = {
                mode: @json($mode),
                activeSessionId: @json($activeSessionId),
                activePalletId: @json($activePalletId),
                qaLocationId: @json($qaLocationId),
                qaQty: @json($qaQty),
                timestamp: Date.now()
            };
            sessionStorage.setItem(SESSION_STATE_KEY, JSON.stringify(state));
        }

        function restoreSessionState() {
            const stored = sessionStorage.getItem(SESSION_STATE_KEY);
            if (stored) {
                try {
                    const state = JSON.parse(stored);
                    // Only restore if saved less than 1 hour ago
                    if (Date.now() - state.timestamp < 3600000) {
                        console.log('Restoring session state:', state);
                    }
                } catch (e) {
                    console.warn('Failed to restore session state:', e);
                }
            }
        }

        // Save state on every Livewire update
        document.addEventListener('livewire:updated', () => {
            saveSessionState();
            setTimeout(() => {
                document.getElementById('scanInput')?.focus();
            }, 100);
        });

        // Focus on page load and restore state
        window.addEventListener('load', () => {
            initBarcodeDetector();
            restoreSessionState();
            document.getElementById('scanInput')?.focus();
            // Save initial state
            saveSessionState();
        });

        // Save state before unload
        window.addEventListener('beforeunload', () => {
            saveSessionState();
        });

        // Clean up camera on page unload
        window.addEventListener('beforeunload', () => {
            stopCamera();
        });

        // Prevent accidental back navigation on phone
        window.addEventListener('popstate', (e) => {
            e.preventDefault();
            history.pushState(null, null, location.href);
        });
        history.pushState(null, null, location.href);
    </script>
</x-filament-panels::page>

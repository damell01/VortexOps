<div x-data="cameraScanner()" x-on:open-camera-scanner.window="open()">
    {{-- Overlay --}}
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
        x-on:click.self="close()"
        style="display:none"
    >
        <div class="relative w-full max-w-sm rounded-2xl bg-white dark:bg-gray-900 shadow-2xl overflow-hidden">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📱 Scan Barcode or QR Code</h3>
                <button type="button" x-on:click="close()" class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Camera view --}}
            <div class="relative bg-black aspect-video overflow-hidden">
                <div x-ref="readerDiv" id="html5-qr-reader" style="width:100%"></div>

                {{-- Status overlay --}}
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    {{-- Scan guide frame --}}
                    <div class="w-56 h-28 border-3 border-yellow-400 rounded-lg relative mb-8">
                        <span class="absolute -top-1 -left-1 w-5 h-5 border-t-4 border-l-4 border-yellow-500 rounded-tl-lg"></span>
                        <span class="absolute -top-1 -right-1 w-5 h-5 border-t-4 border-r-4 border-yellow-500 rounded-tr-lg"></span>
                        <span class="absolute -bottom-1 -left-1 w-5 h-5 border-b-4 border-l-4 border-yellow-500 rounded-bl-lg"></span>
                        <span class="absolute -bottom-1 -right-1 w-5 h-5 border-b-4 border-r-4 border-yellow-500 rounded-br-lg"></span>
                    </div>

                    {{-- Detected flash --}}
                    <div x-show="detected" x-transition class="absolute inset-0 bg-green-400/40 flex items-center justify-center" style="display:none">
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-green-500 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <p class="text-white font-bold text-lg">Detected!</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status / error / manual fallback --}}
            <div class="px-4 py-3 space-y-2">
                <p x-show="!error && statusText" class="text-xs text-center text-gray-600 dark:text-gray-400" x-text="statusText" style="display:none"></p>
                <p x-show="error" class="text-xs text-center text-red-600 dark:text-red-400 font-medium" x-text="error" style="display:none"></p>

                <div x-show="showManual" class="flex gap-2" style="display:none">
                    <input
                        type="text"
                        x-ref="manualInput"
                        x-on:keydown.enter.prevent="submitManual()"
                        placeholder="Type barcode or UPC…"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                    <button type="button" x-on:click="submitManual()" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        Use
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
@verbatim
<script>
function cameraScanner() {
    return {
        isOpen: false,
        scanner: null,
        detected: false,
        error: null,
        showManual: false,
        targetInput: null,
        statusText: 'Initializing camera…',
        lastDetectionTime: 0,
        lastDetectedBarcode: null,
        detectionConfidence: 0,
        detectedCallback: null,

        async open() {
            this.isOpen = true;
            this.error = null;
            this.detected = false;
            this.showManual = false;
            this.statusText = 'Initializing camera…';

            this.targetInput = document.activeElement?.tagName === 'INPUT' ? document.activeElement : null;

            await this.$nextTick();
            await this.startScanner();
        },

        async startScanner() {
            try {
                this.statusText = '📷 Point camera at barcode…';

                const readerDiv = this.$refs.readerDiv;
                if (!readerDiv) return;

                // Use Quagga for 1D barcode detection (optimized for speed)
                Quagga.init({
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: readerDiv,
                        constraints: {
                            facingMode: 'environment',
                            width: { min: 800, ideal: 1280 },
                            height: { min: 600, ideal: 720 }
                        }
                    },
                    decoder: {
                        readers: [
                            'code_128_reader',
                            'ean_reader',
                            'upc_reader',
                            'ean_8_reader'
                        ],
                        debug: {
                            showCanvas: false,
                            showPatternMatches: false,
                            showFrequency: false,
                            showErrors: false
                        }
                    },
                    locator: {
                        patchSize: 'medium',
                        halfSample: true
                    },
                    frequency: 20
                }, (err) => {
                    if (err) {
                        console.error('[barcode-scanner]', err);
                        if (err.name === 'NotAllowedError') {
                            this.error = '❌ Camera permission denied. Type barcode manually below.';
                        } else if (err.name === 'NotFoundError') {
                            this.error = '❌ No camera found. Type the barcode manually.';
                        } else {
                            this.error = '❌ Camera error. Type barcode manually.';
                        }
                        this.showManual = true;
                        this.$nextTick(() => {
                            if (this.$refs.manualInput) {
                                setTimeout(() => this.$refs.manualInput.focus(), 100);
                            }
                        });
                        return;
                    }

                    Quagga.start();

                    // Store callback so we can remove it later
                    this.detectedCallback = (result) => {
                        if (result && result.codeResult && result.codeResult.code) {
                            this.onBarcodeDetected(result.codeResult.code);
                        }
                    };
                    Quagga.onDetected(this.detectedCallback);

                    this.showManual = true;
                });
            } catch (err) {
                console.error('[barcode-scanner]', err);
                this.error = '❌ Scanner initialization failed. Type barcode manually.';
                this.showManual = true;
                this.$nextTick(() => {
                    if (this.$refs.manualInput) {
                        setTimeout(() => this.$refs.manualInput.focus(), 100);
                    }
                });
            }
        },

        onBarcodeDetected(value) {
            const now = Date.now();
            if (now - this.lastDetectionTime < 300) return; // Quick throttle for consecutive checks

            // If this is a different barcode, reset confidence
            if (value !== this.lastDetectedBarcode) {
                this.lastDetectedBarcode = value;
                this.detectionConfidence = 1;
                this.statusText = '📍 Detecting: ' + value + '…';
                return; // Wait for confirmation
            }

            // Same barcode detected again - increment confidence
            this.detectionConfidence++;

            // Require 2 consecutive detections of the same barcode to confirm
            if (this.detectionConfidence < 2) {
                this.statusText = '📍 Detecting: ' + value + '…';
                this.lastDetectionTime = now;
                return;
            }

            // Confirmed! Submit the barcode
            this.lastDetectionTime = now;
            this.detected = true;
            this.statusText = '✅ Detected: ' + value;

            // Haptic feedback if available
            if (navigator.vibrate) {
                navigator.vibrate([100, 50, 100]);
            }

            this.fillInput(value);
            setTimeout(() => this.close(), 600);
        },

        submitManual() {
            const val = (this.$refs.manualInput?.value || '').trim();
            if (val) {
                this.fillInput(val);
                this.close();
            }
        },

        fillInput(value) {
            window.dispatchEvent(new CustomEvent('barcode-scanned', { detail: { value } }));

            const input = this.targetInput
                || document.querySelector('input#quickadd-barcode')
                || document.querySelector('input[id*="barcode"]')
                || document.querySelector('input[name*="barcode"]')
                || document.querySelector('input[x-model*="scanInput"]')
                || document.querySelector('input[x-model="scanInput"]');

            if (input) {
                if (input.hasAttribute('x-model') || input.hasAttribute('wire:model')) {
                    const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                    if (setter) setter.call(input, value);
                    else input.value = value;

                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));

                    if (window.Alpine) {
                        input.dispatchEvent(new Event('alpine:updated', { bubbles: true }));
                    }
                } else {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }

                setTimeout(() => input.focus(), 100);
            }
        },

        async close() {
            this.isOpen = false;
            this.detected = false;
            this.lastDetectedBarcode = null;
            this.detectionConfidence = 0;

            try {
                // Properly stop Quagga
                Quagga.stop();

                // Remove the specific detection callback
                if (this.detectedCallback) {
                    Quagga.offDetected(this.detectedCallback);
                    this.detectedCallback = null;
                }

                // Clear the reader div to allow fresh initialization
                const readerDiv = this.$refs.readerDiv;
                if (readerDiv) {
                    readerDiv.innerHTML = '';
                }
            } catch (err) {
                console.error('[barcode-scanner] stop error:', err);
            }
        },
    };
}
</script>
@endverbatim

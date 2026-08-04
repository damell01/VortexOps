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
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scan Barcode</h3>
                <button type="button" x-on:click="close()" class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Camera view --}}
            <div class="relative bg-black aspect-video overflow-hidden">
                <video x-ref="video" autoplay playsinline muted class="w-full h-full object-cover"></video>

                {{-- Scan guide frame --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-56 h-28 border-2 border-primary-400 rounded-lg relative">
                        <span class="absolute -top-px -left-px w-5 h-5 border-t-2 border-l-2 border-primary-500 rounded-tl"></span>
                        <span class="absolute -top-px -right-px w-5 h-5 border-t-2 border-r-2 border-primary-500 rounded-tr"></span>
                        <span class="absolute -bottom-px -left-px w-5 h-5 border-b-2 border-l-2 border-primary-500 rounded-bl"></span>
                        <span class="absolute -bottom-px -right-px w-5 h-5 border-b-2 border-r-2 border-primary-500 rounded-br"></span>
                    </div>
                </div>

                {{-- Detected flash --}}
                <div x-show="detected" x-transition class="absolute inset-0 bg-green-400/30 flex items-center justify-center" style="display:none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>

            {{-- Status / error / manual fallback --}}
            <div class="px-4 py-3">
                <p x-show="!error" class="text-xs text-center text-gray-500 dark:text-gray-400" x-text="statusText" style="display:none"></p>
                <p x-show="error" class="text-xs text-center text-red-500" x-text="error" style="display:none"></p>

                <div x-show="showManual" class="mt-2 flex gap-2" style="display:none">
                    <input
                        type="text"
                        x-ref="manualInput"
                        x-on:keydown.enter.prevent="submitManual()"
                        placeholder="Type barcode manually…"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                    <button type="button" x-on:click="submitManual()" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        Set
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@verbatim
<script>
function cameraScanner() {
    return {
        isOpen: false,
        stream: null,
        detector: null,
        scanLoop: null,
        detected: false,
        error: null,
        showManual: false,
        targetInput: null,
        statusText: 'Starting camera…',
        lastDetectionTime: 0,

        async open() {
            this.isOpen = true;
            this.error = null;
            this.detected = false;
            this.showManual = false;
            this.statusText = 'Starting camera…';

            this.targetInput = document.activeElement?.tagName === 'INPUT' ? document.activeElement : null;

            await this.$nextTick();
            await this.startCamera();
        },

        async startCamera() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
                });

                const video = this.$refs.video;
                video.srcObject = this.stream;

                // Wait for video to be ready
                await new Promise(resolve => {
                    const checkReady = () => {
                        if (video.readyState >= 2) { // HAVE_CURRENT_DATA or better
                            resolve();
                        } else {
                            setTimeout(checkReady, 50);
                        }
                    };
                    checkReady();
                });

                if ('BarcodeDetector' in window) {
                    const formats = await BarcodeDetector.getSupportedFormats();
                    this.detector = new BarcodeDetector({ formats });
                    this.statusText = 'Point camera at a barcode…';
                    this.startScanLoop();
                } else {
                    this.showManual = true;
                    this.statusText = 'Camera active — type barcode below or use Bluetooth scanner.';
                    this.$nextTick(() => this.$refs.manualInput?.focus());
                }
            } catch (e) {
                if (e.name === 'NotAllowedError') {
                    this.error = 'Camera permission denied. Use a Bluetooth scanner or type below.';
                } else if (e.name === 'NotFoundError') {
                    this.error = 'No camera found. Type the barcode manually.';
                } else {
                    this.error = 'Camera error: ' + e.message;
                }
                this.showManual = true;
                this.$nextTick(() => this.$refs.manualInput?.focus());
            }
        },

        startScanLoop() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            const video = this.$refs.video;

            const tick = async () => {
                if (!this.isOpen || !this.detector || !video) return;

                try {
                    if (video.readyState >= video.HAVE_CURRENT_DATA && video.videoWidth > 0 && video.videoHeight > 0) {
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        ctx.drawImage(video, 0, 0);

                        const barcodes = await this.detector.detect(canvas);
                        if (barcodes && barcodes.length > 0) {
                            // Throttle to prevent rapid re-detections
                            const now = Date.now();
                            if (now - this.lastDetectionTime > 500) {
                                this.lastDetectionTime = now;
                                this.onBarcodeDetected(barcodes[0].rawValue);
                                return;
                            }
                        }
                    }
                } catch (e) {
                    console.error('[barcode-scanner] detect error:', e);
                }

                this.scanLoop = requestAnimationFrame(tick);
            };

            this.scanLoop = requestAnimationFrame(tick);
        },

        onBarcodeDetected(value) {
            this.detected = true;
            this.statusText = 'Detected: ' + value;
            this.fillInput(value);
            setTimeout(() => this.close(), 800);
        },

        submitManual() {
            const val = (this.$refs.manualInput?.value || '').trim();
            if (val) { this.fillInput(val); this.close(); }
        },

        fillInput(value) {
            window.dispatchEvent(new CustomEvent('barcode-scanned', { detail: { value } }));

            // Priority: target input (was focused when scanner opened), then barcode field, then first input
            const input = this.targetInput
                || document.querySelector('input#quickadd-barcode')
                || document.querySelector('input[id*="barcode"]')
                || document.querySelector('input[name*="barcode"]')
                || document.querySelector('input[x-model*="scanInput"]')
                || document.querySelector('input[x-model="scanInput"]');

            if (input) {
                // For Livewire inputs, update both the value and trigger the model binding
                if (input.hasAttribute('x-model') || input.hasAttribute('wire:model')) {
                    // Get the actual input element property setter
                    const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                    if (setter) setter.call(input, value);
                    else input.value = value;

                    // Trigger all necessary events for Livewire/Alpine to pick up the change
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));

                    // For Alpine.js x-model binding
                    if (window.Alpine) {
                        input.dispatchEvent(new Event('alpine:updated', { bubbles: true }));
                    }
                } else {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // Focus back to the input for convenience
                setTimeout(() => input.focus(), 100);
            }
        },

        close() {
            this.isOpen = false;
            this.detected = false;
            if (this.scanLoop) cancelAnimationFrame(this.scanLoop);
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
            if (this.$refs.video) this.$refs.video.srcObject = null;
        },
    };
}
</script>
@endverbatim

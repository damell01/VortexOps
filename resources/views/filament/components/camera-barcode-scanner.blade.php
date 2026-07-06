<div
    x-data="cameraScanner()"
    x-on:open-camera-scanner.window="open()"
    x-cloak
    style="display:none"
    class="fi-camera-scanner"
>
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
        @click.self="close()"
    >
        <div class="relative w-full max-w-sm rounded-2xl bg-white dark:bg-gray-900 shadow-2xl overflow-hidden">
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scan Barcode</h3>
                <button type="button" @click="close()" class="rounded p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            {{-- Camera view --}}
            <div class="relative bg-black aspect-video overflow-hidden">
                <video x-ref="video" autoplay playsinline muted class="w-full h-full object-cover"></video>

                {{-- Scan guide overlay --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-56 h-28 border-2 border-primary-400 rounded-lg relative">
                        <span class="absolute -top-px -left-px w-5 h-5 border-t-2 border-l-2 border-primary-500 rounded-tl"></span>
                        <span class="absolute -top-px -right-px w-5 h-5 border-t-2 border-r-2 border-primary-500 rounded-tr"></span>
                        <span class="absolute -bottom-px -left-px w-5 h-5 border-b-2 border-l-2 border-primary-500 rounded-bl"></span>
                        <span class="absolute -bottom-px -right-px w-5 h-5 border-b-2 border-r-2 border-primary-500 rounded-br"></span>
                    </div>
                </div>

                {{-- Detected flash --}}
                <div x-show="detected" x-transition class="absolute inset-0 bg-green-400/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>

            {{-- Status / error --}}
            <div class="px-4 py-3">
                <p x-show="!error" class="text-xs text-center text-gray-500 dark:text-gray-400" x-text="statusText"></p>
                <p x-show="error" class="text-xs text-center text-red-500" x-text="error"></p>

                {{-- Manual fallback input --}}
                <div x-show="showManual" class="mt-2 flex gap-2">
                    <input
                        type="text"
                        x-ref="manualInput"
                        @keydown.enter.prevent="submitManual()"
                        placeholder="Type barcode manually…"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                    <button type="button" @click="submitManual()" class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        Set
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

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
        statusText: 'Initialising camera…',

        async open() {
            this.isOpen = true;
            this.error = null;
            this.detected = false;
            this.showManual = false;
            this.statusText = 'Starting camera…';

            // Remember the last focused input with wire:model containing "barcode"
            this.targetInput = document.activeElement?.closest('[wire\\:model], [wire\\:model\\.live]')?.querySelector('input')
                || document.querySelector('input[name*="barcode"], input[id*="barcode"], input[wire\\:model*="barcode"], input[wire\\:model\\.live*="barcode"]');

            await this.$nextTick();
            await this.startCamera();
        },

        async startCamera() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
                });
                this.$refs.video.srcObject = this.stream;

                if ('BarcodeDetector' in window) {
                    const formats = await BarcodeDetector.getSupportedFormats();
                    this.detector = new BarcodeDetector({ formats });
                    this.statusText = 'Point camera at a barcode…';
                    this.startScanLoop();
                } else {
                    // BarcodeDetector not supported — show manual fallback
                    this.showManual = true;
                    this.statusText = 'Camera active. Type barcode below or use Bluetooth scanner.';
                    this.$nextTick(() => this.$refs.manualInput?.focus());
                }
            } catch (e) {
                if (e.name === 'NotAllowedError') {
                    this.error = 'Camera permission denied. Use the field directly or a Bluetooth scanner.';
                } else if (e.name === 'NotFoundError') {
                    this.error = 'No camera found. Use a Bluetooth scanner or type the barcode.';
                } else {
                    this.error = 'Camera unavailable: ' + e.message;
                }
                this.showManual = true;
                this.$nextTick(() => this.$refs.manualInput?.focus());
            }
        },

        startScanLoop() {
            const tick = async () => {
                if (!this.isOpen || !this.detector || !this.$refs.video) return;
                try {
                    const barcodes = await this.detector.detect(this.$refs.video);
                    if (barcodes.length > 0) {
                        this.onBarcodeDetected(barcodes[0].rawValue);
                        return;
                    }
                } catch {}
                this.scanLoop = requestAnimationFrame(tick);
            };
            this.scanLoop = requestAnimationFrame(tick);
        },

        onBarcodeDetected(value) {
            this.detected = true;
            this.statusText = 'Detected: ' + value;

            // Fill the target Livewire/Alpine input
            this.fillInput(value);

            setTimeout(() => this.close(), 800);
        },

        submitManual() {
            const val = this.$refs.manualInput?.value?.trim();
            if (val) {
                this.fillInput(val);
                this.close();
            }
        },

        fillInput(value) {
            // Dispatch a window event so Livewire-connected listeners can pick it up
            window.dispatchEvent(new CustomEvent('barcode-scanned', { detail: { value } }));

            // Also try to fill the focused/target input directly
            const input = this.targetInput
                || document.querySelector('input[wire\\:model*="barcode"]')
                || document.querySelector('input[wire\\:model\\.live*="barcode"]');

            if (input) {
                const nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
                if (nativeSetter) {
                    nativeSetter.call(input, value);
                } else {
                    input.value = value;
                }
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        close() {
            this.isOpen = false;
            this.detected = false;
            if (this.scanLoop) cancelAnimationFrame(this.scanLoop);
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            if (this.$refs.video) this.$refs.video.srcObject = null;
        },
    };
}
</script>

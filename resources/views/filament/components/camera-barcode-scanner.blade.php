<div x-data="cameraScanner()" x-on:open-camera-scanner.window="open($event)">
    <div
        x-cloak
        x-show="isOpen"
        class="fixed inset-0 z-[100] flex items-end justify-center bg-black/80 sm:items-center sm:p-5"
        x-on:keydown.escape.window="close()"
        style="display:none"
    >
        <section class="flex h-[100dvh] w-full flex-col overflow-hidden bg-gray-950 sm:h-auto sm:max-h-[90vh] sm:max-w-xl sm:rounded-2xl sm:bg-white sm:shadow-2xl dark:sm:bg-gray-900">
            <header class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 bg-gray-950 px-4 pb-3 pt-[max(.75rem,env(safe-area-inset-top))] text-white sm:border-gray-200 sm:bg-white sm:py-3 sm:text-gray-950 dark:sm:border-gray-700 dark:sm:bg-gray-900 dark:sm:text-white">
                <div class="min-w-0">
                    <div class="text-sm font-semibold" x-text="titleText"></div>
                    <div class="mt-0.5 truncate text-[10px] text-gray-400 sm:text-xs" x-text="helperText"></div>
                </div>
                <button type="button" x-on:click="close()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 text-white sm:bg-gray-100 sm:text-gray-600 dark:sm:bg-gray-800 dark:sm:text-gray-300" aria-label="Close scanner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                </button>
            </header>

            <div class="relative h-[46dvh] min-h-[18rem] max-h-[30rem] shrink-0 overflow-hidden bg-black sm:aspect-[4/3] sm:h-auto sm:min-h-0 sm:max-h-none vx-scanner-stage">
                <div x-ref="readerDiv" id="html5-qr-reader"></div>

                <div class="pointer-events-none absolute inset-0 flex items-center justify-center px-5">
                    <div class="vx-scan-frame relative h-28 w-full max-w-[22rem] overflow-hidden rounded-2xl border-2 border-white/90 shadow-[0_0_0_999px_rgba(0,0,0,.42)] sm:h-36">
                        <span class="absolute -left-0.5 -top-0.5 h-8 w-8 rounded-tl-2xl border-l-4 border-t-4 border-violet-400"></span>
                        <span class="absolute -right-0.5 -top-0.5 h-8 w-8 rounded-tr-2xl border-r-4 border-t-4 border-violet-400"></span>
                        <span class="absolute -bottom-0.5 -left-0.5 h-8 w-8 rounded-bl-2xl border-b-4 border-l-4 border-violet-400"></span>
                        <span class="absolute -bottom-0.5 -right-0.5 h-8 w-8 rounded-br-2xl border-b-4 border-r-4 border-violet-400"></span>
                        <div class="vx-scan-line absolute inset-x-3 h-0.5 rounded-full bg-violet-300 shadow-[0_0_10px_rgba(196,181,253,.95)]"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="rounded-full bg-black/55 px-3 py-1 text-[10px] font-semibold uppercase tracking-[.14em] text-white/90">Place barcode here</span>
                        </div>
                    </div>
                </div>

                <div class="pointer-events-none absolute inset-x-0 bottom-4 flex justify-center px-4">
                    <div class="rounded-full bg-black/65 px-3 py-1.5 text-[11px] font-medium text-white backdrop-blur" x-text="statusText || 'Point the camera at a barcode'"></div>
                </div>

                <div x-show="detected" x-transition class="pointer-events-none absolute inset-0 flex items-center justify-center bg-green-500/35" style="display:none">
                    <div class="rounded-2xl bg-green-600 px-5 py-4 text-center text-white shadow-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        <div class="mt-1 text-sm font-semibold">Barcode scanned</div>
                        <div class="mt-0.5 font-mono text-xs" x-text="lastDetectedBarcode"></div>
                    </div>
                </div>
            </div>

            <footer class="min-h-0 flex-1 shrink-0 space-y-3 overflow-y-auto bg-gray-950 px-4 pb-[max(.85rem,env(safe-area-inset-bottom))] pt-3 text-white sm:flex-none sm:bg-white sm:pb-4 sm:text-gray-950 dark:sm:bg-gray-900 dark:sm:text-white">
                <div x-show="error" x-text="error" class="rounded-lg border border-red-400/30 bg-red-500/10 px-3 py-2 text-xs font-medium text-red-300 sm:text-red-600" style="display:none"></div>

                <div>
                    <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Manual barcode entry</div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                        <input type="text" x-ref="manualInput" x-on:keydown.enter.prevent="submitManual()" inputmode="numeric" autocomplete="off" placeholder="Type or scan barcode…" class="min-h-11 min-w-0 rounded-lg border border-white/20 bg-white/10 px-3 font-mono text-base text-white placeholder:text-gray-500 sm:border-gray-300 sm:bg-white sm:text-gray-950 dark:sm:border-gray-600 dark:sm:bg-gray-800 dark:sm:text-white" />
                        <button type="button" x-on:click="submitManual()" class="min-h-11 rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white">Use Code</button>
                    </div>
                </div>

                <p class="text-center text-[10px] leading-4 text-gray-500 sm:text-xs">Camera scans submit automatically after confirmation. Manual entry and USB/Bluetooth scanners also work.</p>
            </footer>
        </section>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    .vx-scanner-stage #html5-qr-reader { position:absolute; inset:0; overflow:hidden; }
    .vx-scanner-stage #html5-qr-reader video,
    .vx-scanner-stage #html5-qr-reader canvas { position:absolute; inset:0; width:100% !important; height:100% !important; object-fit:cover; }
    .vx-scanner-stage #html5-qr-reader canvas.drawingBuffer { display:none !important; }

    @keyframes vxBarcodeScanLine {
        0% { top: 12%; opacity: .35; }
        50% { top: 82%; opacity: 1; }
        100% { top: 12%; opacity: .35; }
    }
    .vx-scan-line { animation: vxBarcodeScanLine 1.7s ease-in-out infinite; }
    body.vx-camera-scanner-open .vx-tour-launcher { display: none !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
@verbatim
<script>
function cameraScanner() {
    return {
        isOpen: false,
        detected: false,
        error: null,
        statusText: '',
        titleText: 'Scan barcode',
        helperText: 'Hold the code inside the frame. It submits automatically.',
        targetInput: null,
        detectedCallback: null,
        lastDetectionTime: 0,
        lastDetectedBarcode: null,
        detectionConfidence: 0,

        async open(event = null) {
            if (this.isOpen) return;

            const detail = event?.detail || {};
            this.titleText = detail.title || 'Scan barcode';
            this.helperText = detail.helper || detail.itemName || 'Hold the code inside the frame. It submits automatically.';
            this.targetInput = document.activeElement?.tagName === 'INPUT' ? document.activeElement : null;
            this.isOpen = true;
            document.body?.classList.add('vx-camera-scanner-open');
            this.detected = false;
            this.error = null;
            this.statusText = 'Starting rear camera…';
            this.lastDetectedBarcode = null;
            this.detectionConfidence = 0;
            if (this.$refs.manualInput) this.$refs.manualInput.value = '';
            await this.$nextTick();
            await this.startScanner();
        },

        async startScanner() {
            const readerDiv = this.$refs.readerDiv;
            if (!readerDiv) return this.fail('Scanner view could not be opened. Type the barcode below.');
            if (typeof Quagga === 'undefined') return this.fail('Scanner library did not load. Type the barcode below.');

            try { if (window.QuaggaInitialized) Quagga.stop(); } catch (e) {}
            window.QuaggaInitialized = true;
            readerDiv.innerHTML = '';

            try {
                Quagga.init({
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: readerDiv,
                        area: { top: '25%', right: '8%', left: '8%', bottom: '25%' },
                        constraints: {
                            facingMode: 'environment',
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        }
                    },
                    decoder: { readers: ['code_128_reader','ean_reader','ean_8_reader','upc_reader','upc_e_reader'] },
                    locator: { patchSize: 'medium', halfSample: true },
                    locate: true,
                    frequency: 12,
                    numOfWorkers: Math.min(navigator.hardwareConcurrency || 2, 4),
                    multiple: false
                }, err => {
                    if (err) {
                        window.QuaggaInitialized = false;
                        if (err.name === 'NotAllowedError') return this.fail('Camera permission is blocked. Allow camera access or type the barcode below.');
                        if (err.name === 'NotFoundError') return this.fail('No rear camera was found. Type the barcode below.');
                        return this.fail('Camera could not start. Type the barcode below.');
                    }

                    Quagga.start();
                    this.detectedCallback = result => {
                        const value = result?.codeResult?.code;
                        if (value) this.onBarcodeDetected(value);
                    };
                    Quagga.onDetected(this.detectedCallback);
                    this.statusText = 'Ready — center the barcode in the box';
                });
            } catch (error) {
                console.error('[barcode-scanner]', error);
                this.fail('Camera could not start. Type the barcode below.');
            }
        },

        fail(message) {
            window.QuaggaInitialized = false;
            this.error = message;
            this.statusText = '';
            this.$nextTick(() => setTimeout(() => this.$refs.manualInput?.focus(), 100));
        },

        onBarcodeDetected(value) {
            const cleaned = String(value || '').trim();
            if (!cleaned || this.detected) return;

            const now = Date.now();
            if (now - this.lastDetectionTime < 160) return;
            this.lastDetectionTime = now;

            if (cleaned !== this.lastDetectedBarcode) {
                this.lastDetectedBarcode = cleaned;
                this.detectionConfidence = 1;
                this.statusText = 'Found ' + cleaned + ' — hold steady';
                return;
            }

            this.detectionConfidence++;
            if (this.detectionConfidence < 3) {
                this.statusText = 'Confirming ' + cleaned + ' (' + this.detectionConfidence + '/3)';
                return;
            }

            this.detected = true;
            this.statusText = 'Barcode scanned';
            if (navigator.vibrate) navigator.vibrate([80, 45, 80]);
            this.fillInput(cleaned);
            setTimeout(() => this.close(), 500);
        },

        submitManual() {
            const value = (this.$refs.manualInput?.value || '').trim();
            if (!value) return;
            this.detected = true;
            this.lastDetectedBarcode = value;
            this.fillInput(value);
            this.close();
        },

        fillInput(value) {
            window.dispatchEvent(new CustomEvent('barcode-scanned', { detail: { value } }));

            const input = this.targetInput
                || document.querySelector('input#receiving-barcode-input')
                || document.querySelector('input#quickadd-barcode')
                || document.querySelector('input[id*="barcode"]')
                || document.querySelector('input[name*="barcode"]');

            if (!input) return;

            const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
            if (setter) setter.call(input, value); else input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        async close() {
            this.isOpen = false;
            document.body?.classList.remove('vx-camera-scanner-open');
            this.detected = false;
            this.error = null;
            this.statusText = '';

            try {
                if (this.detectedCallback && typeof Quagga !== 'undefined') Quagga.offDetected(this.detectedCallback);
                this.detectedCallback = null;
                if (typeof Quagga !== 'undefined') Quagga.stop();
            } catch (e) {}

            const readerDiv = this.$refs.readerDiv;
            if (readerDiv) {
                readerDiv.querySelectorAll('video').forEach(video => {
                    try { video.srcObject?.getTracks()?.forEach(track => track.stop()); } catch (e) {}
                    video.srcObject = null;
                });
                readerDiv.innerHTML = '';
            }

            window.QuaggaInitialized = false;
        }
    };
}
</script>
@endverbatim
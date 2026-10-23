<div x-data="cameraScanner()" x-on:open-camera-scanner.window="open($event)">
    <div
        x-cloak
        x-show="isOpen"
        class="fixed inset-0 z-[100] flex items-end justify-center bg-black/80 sm:items-center sm:p-5"
        x-on:keydown.escape.window="close()"
        style="display:none"
    >
        <section class="flex h-[100dvh] w-full flex-col overflow-hidden bg-[#080f1d] text-white sm:h-auto sm:max-h-[90vh] sm:max-w-xl sm:rounded-2xl sm:shadow-2xl">
            <header class="flex shrink-0 items-center gap-3 border-b border-white/10 bg-[#080f1d] px-4 pb-4 pt-[max(.75rem,env(safe-area-inset-top))] sm:py-4">
                <button type="button" x-on:click="close()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white" aria-label="Close scanner">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="text-base font-semibold" x-text="titleText"></div>
                    <div class="mt-0.5 text-xs leading-4 text-slate-400" x-text="helperText"></div>
                </div>
                <button type="button" x-cloak x-show="flashAvailable" x-on:click="toggleFlash()" x-bind:aria-label="flashOn ? 'Turn flash off' : 'Turn flash on'" x-bind:aria-pressed="flashOn" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/15 bg-white/10 text-white" title="Flash">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m13 2-8 11h6l-1 9 9-12h-6l1-8Z" /></svg>
                </button>
            </header>

            <div class="relative h-[46dvh] min-h-[18rem] max-h-[30rem] shrink-0 overflow-hidden bg-black sm:aspect-[4/3] sm:h-auto sm:min-h-0 sm:max-h-none vx-scanner-stage">
                <div x-ref="readerDiv" id="html5-qr-reader"></div>

                <div class="pointer-events-none absolute inset-x-[5%] top-[30%] h-[40%]">
                    <div class="vx-scan-frame relative h-full w-full rounded-2xl shadow-[0_0_0_999px_rgba(0,0,0,.24)]" x-show="!barcodeOutline" x-cloak>
                        <span class="vx-scan-corner absolute -left-0.5 -top-0.5 h-9 w-9 rounded-tl-2xl border-l-4 border-t-4 border-sky-400"></span>
                        <span class="vx-scan-corner absolute -right-0.5 -top-0.5 h-9 w-9 rounded-tr-2xl border-r-4 border-t-4 border-sky-400"></span>
                        <span class="vx-scan-corner absolute -bottom-0.5 -left-0.5 h-9 w-9 rounded-bl-2xl border-b-4 border-l-4 border-sky-400"></span>
                        <span class="vx-scan-corner absolute -bottom-0.5 -right-0.5 h-9 w-9 rounded-br-2xl border-b-4 border-r-4 border-sky-400"></span>
                        <div class="vx-scan-line absolute inset-x-1 h-0.5 rounded-full bg-sky-300 shadow-[0_0_12px_3px_rgba(56,189,248,.8)]"></div>
                    </div>
                </div>

                <svg x-ref="barcodeOverlay" x-show="barcodeOutline" x-cloak aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full overflow-visible" preserveAspectRatio="none">
                    <polygon class="vx-detected-outline" x-bind:points="barcodeOutline" fill="rgba(56,189,248,.12)" stroke="#38bdf8" stroke-width="3" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                </svg>

                <div class="pointer-events-none absolute inset-x-0 bottom-4 flex justify-center px-4">
                    <div class="rounded-full bg-black/70 px-3 py-1.5 text-xs font-medium text-white backdrop-blur" x-text="statusText || 'Center barcode in the frame'"></div>
                </div>

                <div x-show="detected" x-transition class="pointer-events-none absolute inset-0 flex items-center justify-center bg-green-500/35" style="display:none">
                    <div class="rounded-2xl bg-green-600 px-5 py-4 text-center text-white shadow-xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-9 w-9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        <div class="mt-1 text-sm font-semibold">Barcode scanned</div>
                        <div class="mt-0.5 font-mono text-xs" x-text="lastDetectedBarcode"></div>
                    </div>
                </div>
            </div>

            <footer class="min-h-0 flex-1 shrink-0 space-y-4 overflow-y-auto bg-[#080f1d] px-4 pb-[max(.85rem,env(safe-area-inset-bottom))] pt-4 text-white sm:flex-none sm:pb-4">
                <div x-show="error" x-text="error" class="rounded-lg border border-red-400/30 bg-red-500/10 px-3 py-2 text-xs font-medium text-red-300 sm:text-red-600" style="display:none"></div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-3 shadow-lg">
                    <div class="mb-2 text-sm font-semibold text-white">Manual entry</div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
                        <input type="text" x-ref="manualInput" x-on:keydown.enter.prevent="submitManual()" inputmode="numeric" autocomplete="off" aria-label="Manual barcode entry" placeholder="Type or scan barcode…" class="min-h-11 min-w-0 rounded-lg border border-white/20 bg-white/5 px-3 font-mono text-base text-white placeholder:text-slate-500" />
                        <button type="button" x-on:click="submitManual()" class="min-h-11 rounded-lg bg-blue-500 px-4 text-sm font-semibold text-white">Use Code</button>
                    </div>
                </div>

                <p class="text-center text-xs leading-4 text-slate-400">Camera scans automatically. Manual entry and USB/Bluetooth scanners also work.</p>
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
    .vx-scan-corner, .vx-detected-outline { filter: drop-shadow(0 0 6px rgba(56, 189, 248, .95)); }

    @keyframes vxBarcodeScanLine {
        0% { top: 12%; opacity: .35; }
        50% { top: 82%; opacity: 1; }
        100% { top: 12%; opacity: .35; }
    }
    .vx-scan-line { animation: vxBarcodeScanLine 1.4s ease-in-out infinite; }
    @media (prefers-reduced-motion: reduce) { .vx-scan-line { animation: none; top: 50%; } }
    body.vx-camera-scanner-open .vx-tour-launcher { display: none !important; }
</style>

{{--
    Served from our own origin, not a CDN.

    This was loaded from jsdelivr, which makes the camera scanner depend on
    the warehouse having working general internet at the moment somebody
    points a phone at a box. When it does not — a restricted network, a CDN
    outage, a captive portal — the script never arrives and the scanner opens
    to "Scanner library did not load", which reads as the app being broken
    rather than as one file being missing.

    Same file, same version, pinned in package.json and copied to
    public/vendor, so it is as available as the page that asks for it.
--}}
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
        // The code that was actually accepted, shown on the success overlay.
        lastDetectedBarcode: null,
        // Votes per code within a rolling window, rather than a run of
        // identical reads — see onBarcodeDetected.
        votes: {},
        lastVoteTime: 0,
        barcodeOutline: '',
        outlineTime: 0,
        processedCallback: null,
        closeTimer: null,
        scanSession: 0,
        flashAvailable: false,
        flashOn: false,
        flashTrack: null,

        // How many quality-checked reads of the same code end a scan. Two,
        // because the error gate below has already thrown out the reads that
        // used to need out-voting.
        VOTES_TO_ACCEPT: 2,
        // Quagga scores every decoded digit; the average across a barcode is
        // a reliable separator. Good reads sit well under 0.1, and the
        // plausible-but-wrong ones that used to reset the counter are mostly
        // above 0.25.
        MAX_DECODE_ERROR: 0.18,
        // Long enough to survive a hand wobble between frames, short enough
        // that pointing at a second barcode does not inherit the first's
        // votes.
        VOTE_WINDOW_MS: 1400,

        async open(event = null) {
            if (this.isOpen) return;

            const detail = event?.detail || {};
            this.titleText = detail.title || 'Scan barcode';
            this.helperText = detail.helper || detail.itemName || 'Hold the code inside the frame. It submits automatically.';
            this.targetInput = document.activeElement?.tagName === 'INPUT' ? document.activeElement : null;
            this.isOpen = true;
            const session = ++this.scanSession;
            document.body?.classList.add('vx-camera-scanner-open');
            this.detected = false;
            this.error = null;
            this.statusText = 'Starting rear camera…';
            this.lastDetectedBarcode = null;
            this.votes = {};
            this.lastVoteTime = 0;
            this.barcodeOutline = '';
            this.outlineTime = 0;
            this.flashAvailable = false;
            this.flashOn = false;
            this.flashTrack = null;
            clearTimeout(this.closeTimer);
            if (this.$refs.manualInput) this.$refs.manualInput.value = '';
            await this.$nextTick();
            if (this.isOpen && session === this.scanSession) await this.startScanner(session);
        },

        async startScanner(session) {
            const readerDiv = this.$refs.readerDiv;
            if (!readerDiv) return this.fail('Scanner view could not be opened. Type the barcode below.');

            // Quagga is large and scanning is an occasional workflow. Do not
            // make every dashboard/table navigation download and parse it.
            if (typeof window.Quagga === 'undefined') {
                try {
                    await window.vxLoadScriptOnce('/vendor/quagga.min.js', 'vx-quagga');
                } catch (error) {
                    if (this.isOpen && session === this.scanSession) this.fail('Scanner library did not load. Type the barcode below.');
                    return;
                }
            }

            // The library can finish loading after the user has closed the modal.
            if (!this.isOpen || session !== this.scanSession) return;

            try { if (window.QuaggaInitialized) Quagga.stop(); } catch (e) {}
            window.QuaggaInitialized = true;
            readerDiv.innerHTML = '';

            try {
                Quagga.init({
                    inputStream: {
                        name: 'Live',
                        type: 'LiveStream',
                        target: readerDiv,
                        area: { top: '30%', right: '5%', left: '5%', bottom: '30%' },
                        constraints: {
                            facingMode: 'environment',
                            width: { ideal: 960 },
                            height: { ideal: 540 }
                        }
                    },
                    decoder: { readers: ['code_128_reader','ean_reader','ean_8_reader','upc_reader','upc_e_reader'] },
                    locator: { patchSize: 'medium', halfSample: true },
                    locate: true,
                    frequency: 20,
                    numOfWorkers: Math.min(Math.max((navigator.hardwareConcurrency || 2) - 1, 1), 2),
                    multiple: false
                }, err => {
                    if (!this.isOpen || session !== this.scanSession) {
                        if (!this.isOpen) try { Quagga.stop(); } catch (e) {}
                        return;
                    }
                    if (err) {
                        window.QuaggaInitialized = false;
                        if (err.name === 'NotAllowedError') return this.fail('Camera permission is blocked. Allow camera access or type the barcode below.');
                        if (err.name === 'NotFoundError') return this.fail('No rear camera was found. Type the barcode below.');
                        return this.fail('Camera could not start. Type the barcode below.');
                    }

                    Quagga.start();
                    this.updateFlashAvailability();
                    this.detectedCallback = result => {
                        const value = result?.codeResult?.code;
                        if (value) this.onBarcodeDetected(value, this.decodeError(result), result.box);
                    };
                    this.processedCallback = result => this.showBarcodeOutline(result);
                    Quagga.onProcessed(this.processedCallback);
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

        updateFlashAvailability() {
            const track = this.$refs.readerDiv?.querySelector('video')?.srcObject?.getVideoTracks?.()[0];
            try {
                if (track?.getCapabilities?.().torch === true) {
                    this.flashTrack = track;
                    this.flashAvailable = true;
                }
            } catch (e) {
                this.flashAvailable = false;
            }
        },

        async toggleFlash() {
            if (!this.flashAvailable || !this.flashTrack) return;
            const next = !this.flashOn;
            try {
                await this.flashTrack.applyConstraints({ advanced: [{ torch: next }] });
                this.flashOn = next;
            } catch (e) {
                this.flashAvailable = false;
                this.flashOn = false;
            }
        },

        /**
         * How badly Quagga struggled to read this barcode.
         *
         * It scores every decoded digit; the average across the code
         * separates a clean read from a plausible-looking wrong one far more
         * reliably than seeing the same string twice does. Null when the
         * library gives us nothing to judge by, which is treated as "cannot
         * tell" rather than "bad".
         */
        decodeError(result) {
            const codes = result?.codeResult?.decodedCodes;
            if (!Array.isArray(codes)) return null;

            const errors = codes.map(c => c?.error).filter(e => typeof e === 'number');
            if (!errors.length) return null;

            return errors.reduce((a, b) => a + b, 0) / errors.length;
        },

        // Quagga's points use the camera canvas, which can be cropped by the
        // video element's object-fit: cover. Project them onto the visible
        // preview so the outline follows the actual code on a phone screen.
        showBarcodeOutline(result) {
            if (!this.isOpen || this.detected) return;
            const now = Date.now();
            // Locator candidates can include label text and edges. Only draw
            // a box after Quagga actually decoded a barcode inside it.
            const box = result?.codeResult ? result.box : null;
            const video = this.$refs.readerDiv?.querySelector('video');
            const overlay = this.$refs.barcodeOverlay;
            if (!box || !video?.videoWidth || !video?.videoHeight || !overlay) {
                if (now - this.outlineTime > 250) this.barcodeOutline = '';
                return;
            }

            // The SVG is hidden until a read exists, so measure its visible
            // parent rather than its own zero-sized hidden box.
            const stage = this.$refs.readerDiv.parentElement;
            const width = stage?.clientWidth;
            const height = stage?.clientHeight;
            const canvas = this.$refs.readerDiv.querySelector('canvas.drawingBuffer');
            const sourceWidth = canvas?.width || video.videoWidth;
            const sourceHeight = canvas?.height || video.videoHeight;
            if (!width || !height || !sourceWidth || !sourceHeight) return;

            const scale = Math.max(width / sourceWidth, height / sourceHeight);
            const left = (width - sourceWidth * scale) / 2;
            const top = (height - sourceHeight * scale) / 2;
            const points = box.map(point => {
                if (!Array.isArray(point) || !Number.isFinite(point[0]) || !Number.isFinite(point[1])) return null;
                return `${(left + point[0] * scale).toFixed(1)},${(top + point[1] * scale).toFixed(1)}`;
            });
            if (points.length !== 4 || points.includes(null)) return;

            overlay.setAttribute('viewBox', `0 0 ${width} ${height}`);
            this.barcodeOutline = points.join(' ');
            this.outlineTime = now;
        },

        /**
         * Accept a code once enough good reads agree on it.
         *
         * This used to require three *consecutive* identical reads, throttled
         * to one every 160ms, and any single misread reset the run to one. On
         * a phone camera Quagga misreads regularly, so a scan needed three
         * clean frames in a row with nothing wrong in between — which usually
         * happened in about a second and occasionally took half a minute.
         * That was the thirty-second scan.
         *
         * Bad reads are rejected on their decode error. The two surviving
         * reads need not be consecutive, so a stray frame does not restart
         * the scan.
         */
        onBarcodeDetected(value, error = null, box = null) {
            const cleaned = String(value || '').trim();
            if (!cleaned || this.detected) return;

            if (error !== null && error > this.MAX_DECODE_ERROR) {
                return;
            }

            if (box) this.showBarcodeOutline({ box, codeResult: { code: cleaned } });

            const now = Date.now();

            // A gap means the camera moved, or moved on. Old votes should not
            // carry into whatever is in front of it now.
            if (now - this.lastVoteTime > this.VOTE_WINDOW_MS) {
                this.votes = {};
            }
            this.lastVoteTime = now;

            this.votes[cleaned] = (this.votes[cleaned] || 0) + 1;

            if (this.votes[cleaned] < this.VOTES_TO_ACCEPT) {
                this.statusText = 'Found ' + cleaned + ' — hold steady';
                return;
            }

            this.detected = true;
            this.lastDetectedBarcode = cleaned;
            this.statusText = 'Barcode scanned';
            if (navigator.vibrate) navigator.vibrate([80, 45, 80]);
            this.fillInput(cleaned);
            this.closeTimer = setTimeout(() => this.close(), 120);
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
            ++this.scanSession;
            this.isOpen = false;
            document.body?.classList.remove('vx-camera-scanner-open');
            this.detected = false;
            this.error = null;
            this.statusText = '';
            this.barcodeOutline = '';
            this.flashAvailable = false;
            this.flashOn = false;
            this.flashTrack = null;
            clearTimeout(this.closeTimer);

            try {
                if (this.detectedCallback && typeof Quagga !== 'undefined') Quagga.offDetected(this.detectedCallback);
                if (this.processedCallback && typeof Quagga !== 'undefined') Quagga.offProcessed(this.processedCallback);
                this.detectedCallback = null;
                this.processedCallback = null;
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

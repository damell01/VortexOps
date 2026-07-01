<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Scanner Input ───────────────────────────────────────────────── --}}
        <div class="rounded-xl border-2 border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 px-6 py-5 space-y-3">
            <div class="flex items-center gap-3">
                <x-heroicon-o-qr-code class="h-5 w-5 text-violet-500" />
                <h2 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Scan Item</h2>
                <span class="text-xs text-violet-600 dark:text-violet-400">Scan a barcode, or type a SKU — works with any Bluetooth or USB scanner</span>
            </div>

            <div class="flex gap-3 items-center">
                <input
                    wire:model="scanInput"
                    wire:keydown.enter="submitScan"
                    id="scanner-input"
                    type="text"
                    placeholder="Scan barcode or type SKU…"
                    autofocus
                    autocomplete="off"
                    class="flex-1 rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono"
                />
                <button
                    wire:click="submitScan"
                    type="button"
                    class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500"
                >
                    Look Up
                </button>
                {{-- Camera scan button — uses BarcodeDetector Web API (Chrome/Android/Edge) --}}
                <button
                    type="button"
                    id="camera-scan-btn"
                    title="Scan with camera"
                    class="rounded-lg border border-violet-300 dark:border-violet-600 px-3 py-2.5 text-violet-600 dark:text-violet-400 hover:bg-violet-100 dark:hover:bg-violet-900 focus:outline-none focus:ring-2 focus:ring-violet-500 hidden"
                >
                    <x-heroicon-o-camera class="h-5 w-5" />
                </button>
            </div>

            {{-- Camera preview (shown only while scanning) --}}
            <div id="camera-container" class="hidden space-y-2">
                <video id="camera-video" class="w-full max-w-sm rounded-lg border border-violet-300" autoplay playsinline muted></video>
                <p class="text-xs text-violet-600 dark:text-violet-400">Point camera at barcode. Detection is automatic.</p>
                <button type="button" id="camera-stop-btn" class="text-xs text-red-500 underline">Stop camera</button>
            </div>
        </div>

        {{-- ── Error ───────────────────────────────────────────────────────── --}}
        @if ($errorMessage)
            <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-5 py-4 text-sm text-red-700 dark:text-red-300 flex items-start gap-3">
                <x-heroicon-o-exclamation-circle class="h-5 w-5 flex-shrink-0 mt-0.5 text-red-400" />
                <span>{{ $errorMessage }}</span>
            </div>
        @endif

        {{-- ── Result ──────────────────────────────────────────────────────── --}}
        @if ($result)
            <div class="space-y-4">

                {{-- Item header --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $result['name'] }}</h2>
                                @if ($result['is_low'])
                                    <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">Low Stock</span>
                                @endif
                            </div>
                            <div class="mt-1 flex items-center gap-4 text-xs text-gray-400 flex-wrap">
                                @if ($result['category'])
                                    <span>{{ $result['category'] }}</span>
                                @endif
                                @if ($result['sku'])
                                    <span class="font-mono">SKU: {{ $result['sku'] }}</span>
                                @endif
                                @if ($result['barcode'])
                                    <span class="font-mono">Barcode: {{ $result['barcode'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $result['total_qty'] }}</p>
                            <p class="text-xs text-gray-400">total units</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3 flex-wrap">
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 text-center">
                            <p class="text-xs text-gray-400">Avg Cost</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">${{ $result['avg_cost'] }}</p>
                        </div>
                        @if ($result['reorder'])
                            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-2 text-center">
                                <p class="text-xs text-gray-400">Reorder At</p>
                                <p class="text-sm font-semibold {{ $result['is_low'] ? 'text-amber-600' : 'text-gray-900 dark:text-gray-100' }}">{{ $result['reorder'] }} units</p>
                            </div>
                        @endif
                        <a
                            href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('edit', ['record' => $result['id']]) }}"
                            class="ml-auto rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            Edit Item
                        </a>
                        @if (! $adjustMode)
                            <button
                                wire:click="openAdjust"
                                type="button"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
                            >
                                Adjust Stock
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Adjust stock form --}}
                @if ($adjustMode)
                    <div class="rounded-xl border border-violet-200 dark:border-violet-800 bg-violet-50 dark:bg-violet-950 px-6 py-5 space-y-4">
                        <h3 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Adjust Stock</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Location</label>
                                <select wire:model="adjustLocationId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                                    @foreach ($result['stock'] as $s)
                                        <option value="{{ $s['location_id'] }}">{{ $s['location'] }} ({{ $s['qty'] }} units)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Quantity Change (+ add, − remove)</label>
                                <input
                                    wire:model="adjustQty"
                                    type="number"
                                    step="1"
                                    placeholder="e.g. 5 or -3"
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Reason (optional)</label>
                                <input
                                    wire:model="adjustReason"
                                    type="text"
                                    placeholder="Cycle count, damage, etc."
                                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500"
                                />
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button
                                wire:click="applyAdjust"
                                type="button"
                                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
                            >
                                Apply Adjustment
                            </button>
                            <button
                                wire:click="$set('adjustMode', false)"
                                type="button"
                                class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Stock per location --}}
                @if (count($result['stock']))
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="px-6 py-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Stock by Location</h3>
                        </div>
                        @foreach ($result['stock'] as $s)
                            <div class="px-6 py-3 flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $s['location'] }}</span>
                                <span class="text-sm font-semibold {{ $s['qty'] <= 0 ? 'text-red-500' : 'text-gray-900 dark:text-gray-100' }}">{{ number_format($s['qty'], 0) }} units</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5 text-sm text-gray-400">
                        No stock records yet.
                    </div>
                @endif

                {{-- Recent movements --}}
                @if (count($result['movements']))
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="px-6 py-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Movements</h3>
                        </div>
                        @foreach ($result['movements'] as $m)
                            <div class="px-6 py-3 flex items-center gap-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ str_contains($m['type'], 'in') || str_contains($m['type'], 'receipt') ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' }}">
                                    {{ ucwords(str_replace('_', ' ', $m['type'])) }}
                                </span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $m['qty'] > 0 ? '+' : '' }}{{ number_format($m['qty'], 0) }}</span>
                                @if ($m['location'] !== '—')
                                    <span class="text-gray-500">→ {{ $m['location'] }}</span>
                                @endif
                                <span class="ml-auto text-gray-400 text-xs">{{ $m['date'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        @endif

        {{-- ── Usage hint (empty state) ─────────────────────────────────────── --}}
        @if (! $result && ! $errorMessage)
            <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 px-8 py-12 text-center space-y-2">
                <x-heroicon-o-qr-code class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Scan or type to look up any item</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Works with Bluetooth scanners, USB scanners, or the camera button on supported browsers</p>
            </div>
        @endif

    </div>

    {{-- Camera scanning via BarcodeDetector Web API --}}
    @push('scripts')
    <script>
    (function () {
        if (!('BarcodeDetector' in window)) return;

        const btn       = document.getElementById('camera-scan-btn');
        const container = document.getElementById('camera-container');
        const video     = document.getElementById('camera-video');
        const stopBtn   = document.getElementById('camera-stop-btn');
        const input     = document.getElementById('scanner-input');

        if (!btn) return;
        btn.classList.remove('hidden');

        let stream    = null;
        let detector  = null;
        let scanning  = false;
        let rafHandle = null;

        btn.addEventListener('click', async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                video.srcObject = stream;
                await video.play();
                container.classList.remove('hidden');
                btn.classList.add('hidden');
                detector = new BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code', 'data_matrix', 'itf'] });
                scanning = true;
                detectLoop();
            } catch (e) {
                alert('Camera access denied or unavailable.');
            }
        });

        stopBtn.addEventListener('click', stopCamera);

        function stopCamera() {
            scanning = false;
            if (rafHandle) cancelAnimationFrame(rafHandle);
            if (stream) stream.getTracks().forEach(t => t.stop());
            video.srcObject = null;
            container.classList.add('hidden');
            btn.classList.remove('hidden');
        }

        async function detectLoop() {
            if (!scanning) return;
            try {
                const barcodes = await detector.detect(video);
                if (barcodes.length > 0) {
                    const code = barcodes[0].rawValue;
                    stopCamera();
                    // Inject value into Livewire and trigger scan
                    @this.set('scanInput', code).then(() => @this.call('submitScan'));
                    return;
                }
            } catch (_) {}
            rafHandle = requestAnimationFrame(detectLoop);
        }
    })();
    </script>
    @endpush
</x-filament-panels::page>

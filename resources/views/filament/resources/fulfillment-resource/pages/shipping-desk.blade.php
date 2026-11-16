<x-filament-panels::page>
    @php ['show' => $show, 'buyers' => $buyers, 'statusCounts' => $statusCounts, 'lastSynced' => $lastSynced] = $this->shippingData(); @endphp
    <div class="space-y-5" wire:poll.visible.30s>
        <header class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400">Fulfillment Center · Shipping workspace</p>
            <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $show->title }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review buyers, order lines, shipments, tracking and available Whatnot labels. Label creation is not enabled in this workspace yet.</p>
            @if($this->isManager())
                <button type="button" wire:click="refreshShipments" wire:loading.attr="disabled" wire:target="refreshShipments" class="mt-4 min-h-11 rounded-xl bg-violet-600 px-4 font-semibold text-white hover:bg-violet-700 disabled:opacity-50">Refresh shipments from Whatnot</button>
            @endif
            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_220px]">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Search buyers, order IDs or tracking
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search shipping records…" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                </label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Shipping status
                    <select wire:model.live="statusFilter" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="all">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="label_created">Label created</option>
                        <option value="label_generated">Label generated</option>
                        <option value="in_transit">In transit</option>
                        <option value="dropped_off">Dropped off</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                    </select>
                </label>
            </div>
        </header>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach(['total' => 'Packages', 'pending' => 'Pending', 'labels' => 'Labels ready', 'shipped' => 'In transit', 'delivered' => 'Delivered', 'exceptions' => 'Exceptions'] as $key => $label)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $statusCounts[$key] }}</p>
                </div>
            @endforeach
        </div>
        <section class="rounded-2xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-900 dark:bg-violet-950/30">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-violet-950 dark:text-violet-100">Bulk label preparation</h3>
                    <p class="text-sm text-violet-800 dark:text-violet-200">Select shipments below, then prepare a print batch. Only existing Whatnot label documents are used.</p>
                </div>
                <button type="button" wire:click="selectPrintableLabels" class="min-h-11 rounded-xl border border-violet-400 bg-white px-4 font-semibold text-violet-800 hover:bg-violet-100">Select printable (up to 100)</button>
                <button type="button" wire:click="$set('selectedShipmentIds', [])" class="min-h-11 rounded-xl border border-violet-300 px-3 font-semibold text-violet-800">Clear</button>
                <button type="button" wire:click="preparePrintBatch" wire:loading.attr="disabled" wire:target="preparePrintBatch" class="min-h-11 rounded-xl bg-violet-700 px-4 font-semibold text-white hover:bg-violet-800">Prepare selected labels ({{ count($selectedShipmentIds) }})</button>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="text-xs font-semibold text-violet-950 dark:text-violet-100">Paper size
                    <select wire:model="labelSize" class="mt-1 w-full rounded-lg border-violet-200 bg-white text-sm text-gray-900 dark:bg-gray-800 dark:text-white">
                        <option value="4x6">4 × 6 thermal</option><option value="letter">US Letter</option>
                    </select>
                </label>
                <label class="text-xs font-semibold text-violet-950 dark:text-violet-100">Printer name (reference)
                    <input wire:model="printerName" maxlength="120" placeholder="e.g. Station 1 Rollo" class="mt-1 w-full rounded-lg border-violet-200 bg-white text-sm text-gray-900 dark:bg-gray-800 dark:text-white">
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-violet-950 dark:text-violet-100"><input type="checkbox" wire:model="confirmReprints" class="rounded border-violet-300"> Confirm possible reprints</label>
            </div>
            <p class="mt-2 text-xs text-violet-800 dark:text-violet-200">Printer name is recorded for audit only. Select the physical printer and matching paper in your browser print dialog.</p>
            @error('selectedShipmentIds') <p class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p> @enderror
            @if(count($printDocuments))
                <div class="mt-4 rounded-xl border border-violet-200 bg-white p-3 dark:border-violet-800 dark:bg-gray-900">
                    <p class="mb-2 text-sm font-semibold">Prepared documents — open and print each label</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($printDocuments as $document)
                            <div x-data="{ open: false }" class="rounded-xl border border-violet-200 bg-white p-2 dark:bg-gray-800">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong class="text-xs text-gray-900 dark:text-white">{{ $loop->iteration }}. {{ $document['buyer'] }}</strong>
                                    <button type="button" @click="open = !open" class="rounded-lg border border-violet-300 px-3 py-2 text-xs font-bold text-violet-800 dark:text-violet-200">Preview</button>
                                    <button type="button" wire:click="recordPrintAttempt({{ $document['shipment_id'] }})" wire:loading.attr="disabled" wire:target="recordPrintAttempt" class="rounded-lg border border-violet-300 px-3 py-2 text-xs font-bold text-violet-800 dark:text-violet-200">Record print attempt</button>
                                    <a href="{{ $document['url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-violet-700 px-3 py-2 text-xs font-bold text-white">Open / print</a>
                                </div>
                                <template x-if="open"><iframe src="{{ $document['url'] }}" title="Prepared label for {{ $document['buyer'] }}" style="margin-top:8px;width:100%;height:400px;min-width:min(340px,75vw);border:1px solid #ddd6fe;border-radius:8px"></iframe></template>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 rounded-xl border border-violet-200 bg-violet-50 p-3 dark:bg-violet-950/30" x-data="vortexLabelBatch(@js($printDocuments), @js($labelSize))">
                        <h4 class="font-bold text-violet-950 dark:text-violet-100">Combined label PDF</h4>
                        <p class="mt-1 text-xs text-violet-800 dark:text-violet-200">Merge all selected Whatnot PDF or image labels into one downloadable, printable document. The original labels are preserved as individual pages.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="build()" :disabled="busy" class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" x-text="busy ? 'Combining labels…' : 'Build combined PDF'"></button>
                            <button type="button" @click="download()" :disabled="!pdf" class="rounded-lg border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-900 disabled:opacity-50 dark:text-white">Download PDF</button>
                            <button type="button" @click="browserPrint()" :disabled="!pdf" class="rounded-lg border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-900 disabled:opacity-50 dark:text-white">Print via browser</button>
                        </div>
                        <p class="mt-2 text-xs" role="status" x-text="message"></p>
                        <div class="mt-3 border-t border-violet-200 pt-3">
                            <h5 class="text-sm font-bold text-violet-950 dark:text-violet-100">Direct thermal printing (QZ Tray)</h5>
                            <p class="mt-1 text-xs text-violet-800 dark:text-violet-200">Requires QZ Tray installed on this workstation, a configured printer, and approval of the local printing connection. Untrusted/unsigned connections may show a security warning. Browser printing remains available.</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <button type="button" @click="connectPrinter()" :disabled="busy" class="rounded-lg border border-violet-300 px-3 py-2 text-sm font-semibold text-violet-900 dark:text-white">Connect QZ Tray</button>
                                <select x-model="printer" class="rounded-lg border border-violet-300 bg-white text-sm text-gray-900 dark:bg-gray-800 dark:text-white" aria-label="Choose connected printer">
                                    <option value="">Select printer</option>
                                    <template x-for="name in printers" :key="name"><option :value="name" x-text="name"></option></template>
                                </select>
                                <label class="flex items-center gap-2 text-xs text-violet-900 dark:text-violet-100"><input type="checkbox" x-model="acknowledged"> I verified label orientation and printer setup</label>
                                <button type="button" @click="directPrint()" :disabled="!pdf || !printer || busy || !acknowledged" class="rounded-lg bg-violet-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">Print to selected printer</button>
                            </div>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Browser printing is manual. Preparing a batch does not mark labels as printed.</p>
                </div>
            @endif
        </section>
        <p class="text-xs text-gray-500">Last shipment sync: {{ $lastSynced ? \Illuminate\Support\Carbon::parse($lastSynced)->diffForHumans() : 'Unknown' }} · Screen refreshes every 30 seconds; actual updates depend on Whatnot synchronization.</p>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $buyers->count() }} matching buyers · data from existing synced orders and shipments</p>
        @forelse($buyers as $buyer)
            <section wire:key="shipping-buyer-{{ $loop->index }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4 dark:border-gray-700">
                    <div><h3 class="text-lg font-bold text-gray-950 dark:text-white">{{ $buyer['username'] }}</h3><p class="text-sm text-gray-500 dark:text-gray-400">{{ $buyer['orders']->count() }} order lines · {{ $buyer['items'] }} items · {{ $buyer['shipments']->count() }} shipment records</p></div>
                    <span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200">{{ str_replace('_', ' ', ucfirst($buyer['status'])) }}</span>
                </div>
                <div class="grid gap-4 p-4 lg:grid-cols-2">
                    <div>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">Order items</h4>
                        @forelse($buyer['orders'] as $order)
                            <div class="mb-2 rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-700">
                                <p class="font-semibold text-gray-950 dark:text-white">{{ $order->item_name ?: 'Order item' }} <span class="font-normal text-gray-500">× {{ $order->quantity ?: 1 }}</span></p>
                                <p class="mt-1 break-all text-xs text-gray-500">Order {{ $order->whatnot_order_id ?: 'ID unavailable' }}</p>
                                @if($order->tracking_number)<p class="mt-1 break-all text-xs text-gray-600 dark:text-gray-300">Tracking {{ $order->tracking_number }}</p>@endif
                                @if(\App\Filament\Resources\FulfillmentResource\Pages\ShippingDesk::safeDocumentUrl($order->whatnot_shipment_url))
                                    <a class="mt-2 inline-block text-sm font-semibold text-violet-700 underline dark:text-violet-300" target="_blank" rel="noopener noreferrer" href="{{ $order->whatnot_shipment_url }}">View shipment on Whatnot</a>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">Order details have not synced for this buyer.</p>
                        @endforelse
                    </div>
                    <div>
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">Shipments & labels</h4>
                        @forelse($buyer['shipments'] as $shipment)
                            @php
                                $payload = is_array($shipment->raw_payload) ? $shipment->raw_payload : [];
                                $docs = is_array($payload['documents'] ?? null) ? $payload['documents'] : [];
                                $label = \App\Filament\Resources\FulfillmentResource\Pages\ShippingDesk::safeDocumentUrl($docs['label_url'] ?? null);
                                $bundle = \App\Filament\Resources\FulfillmentResource\Pages\ShippingDesk::safeDocumentUrl($docs['bundled_url'] ?? null);
                                $dashboard = \App\Filament\Resources\FulfillmentResource\Pages\ShippingDesk::safeDocumentUrl($payload['shipment_url'] ?? null);
                            @endphp
                            @if($label)
                                <label class="mb-2 flex cursor-pointer items-center gap-3 rounded-lg bg-violet-50 p-2 text-sm font-medium text-violet-900 dark:bg-violet-950/40 dark:text-violet-200">
                                    <input type="checkbox" wire:model.live="selectedShipmentIds" value="{{ $shipment->id }}" class="rounded border-violet-300 text-violet-700" />
                                    Include this shipment in label batch
                                </label>
                            @endif
                            <div class="mb-2 rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-700">
                                <div class="flex flex-wrap justify-between gap-2"><span class="font-semibold text-gray-950 dark:text-white">{{ $shipment->carrier ?: 'Carrier pending' }}</span><span class="text-gray-500">{{ str_replace('_', ' ', $shipment->status ?: 'Pending') }}</span></div>
                                <p class="mt-1 break-all text-xs text-gray-600 dark:text-gray-300">Tracking: {{ $shipment->tracking_number ?: 'Not available' }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $shipment->weight_oz !== null ? $shipment->weight_oz.' oz' : 'Weight pending' }} · {{ $shipment->item_count ?? 0 }} items</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if($label)
                                        <div x-data="{ open: false }" class="w-full" wire:key="shipping-label-{{ $shipment->id }}">
                                            <button type="button" @click="open = !open" :aria-expanded="open.toString()" class="inline-flex min-h-10 items-center rounded-lg bg-violet-600 px-3 font-semibold text-white hover:bg-violet-700">Preview label</button>
                                            <a href="{{ route('fulfillment.shipment-label', ['shipment' => $shipment->id]) }}" target="_blank" rel="noopener noreferrer" class="ml-2 inline-flex min-h-10 items-center rounded-lg border border-violet-300 px-3 font-semibold text-violet-700">Open / print</a>
                                            <div x-show="open" x-cloak class="mt-3 rounded-xl border border-violet-200 bg-white p-2">
                                                <div class="mb-2 flex items-center justify-between gap-2"><strong class="text-sm text-gray-900">Label for {{ $buyer['username'] }}</strong><button type="button" @click="open = false" class="rounded-lg border px-3 py-2 text-xs font-semibold">Close preview</button></div>
                                                <template x-if="open"><iframe src="{{ route('fulfillment.shipment-label', ['shipment' => $shipment->id]) }}" title="Whatnot shipping label for {{ $buyer['username'] }}" style="display:block;width:100%;height:460px;border:0;background:#f8fafc"></iframe></template>
                                            </div>
                                        </div>
                                    @endif
                                    @if($bundle)<a href="{{ $bundle }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center rounded-lg border border-violet-300 px-3 font-semibold text-violet-700 dark:text-violet-300">Open bundled document</a>@endif
                                    @if($dashboard)<a href="{{ $dashboard }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 px-3 font-medium dark:border-gray-600">Open in Whatnot</a>@endif
                                    @if(!$label && !$bundle)<span class="text-xs text-amber-700 dark:text-amber-300">No printable document currently synced</span>@endif
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700">No shipment records synced. Use Whatnot until shipment data becomes available.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-900">No matching buyers or shipments. Check the sync status and search filters.</div>
        @endforelse
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900" x-data="{ expanded: false }">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h3 class="font-bold text-gray-950 dark:text-white">Shipping &amp; packing audit</h3><p class="text-xs text-gray-500">Recent actions, buyer usernames and the team member responsible.</p></div>
                <button type="button" @click="expanded = !expanded" :aria-expanded="expanded.toString()" class="rounded-lg border border-violet-300 px-3 py-2 text-sm font-semibold text-violet-700"><span x-text="expanded ? 'Hide history' : 'Show history'"></span></button>
            </div>
            <div x-show="expanded" x-cloak class="mt-3 space-y-2">
                @forelse($this->shippingAudit() as $event)
                    <div wire:key="shipping-audit-{{ $event->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 py-2 text-sm dark:border-gray-700">
                        <div><strong class="text-gray-900 dark:text-white">{{ \App\Models\FulfillmentEvent::label($event->type) }}</strong><span class="ml-2 text-gray-600 dark:text-gray-300">{{ $event->buyer_username ?: 'Show-level event' }}</span></div>
                        <span class="text-xs text-gray-500">{{ $event->user?->name ?? 'System' }} · {{ $event->created_at?->format('M j, g:i A') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No shipping or packing audit events recorded for this show.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
@once
<script src="https://cdn.jsdelivr.net/npm/pdf-lib@1.17.1/dist/pdf-lib.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.5/qz-tray.js" defer></script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('vortexLabelBatch', (documents, paperSize) => ({
        documents, paperSize, pdf: null, busy: false, message: '', printer: '', printers: [], acknowledged: false,
        async build() {
            if (!window.PDFLib) { this.message = 'PDF library unavailable. Check your connection and reload.'; return; }
            this.busy = true; this.pdf = null; this.message = 'Fetching and combining ' + this.documents.length + ' labels…';
            try {
                const out = await PDFLib.PDFDocument.create();
                for (const doc of this.documents) {
                    const response = await fetch(doc.url, { credentials: 'same-origin', redirect: 'error' });
                    if (!response.ok) throw new Error('Unable to retrieve label for ' + doc.buyer + ' (HTTP ' + response.status + ')');
                    const bytes = await response.arrayBuffer();
                    const mime = (response.headers.get('content-type') || '').toLowerCase();
                    if (mime.includes('pdf')) {
                        const source = await PDFLib.PDFDocument.load(bytes);
                        const pages = await out.copyPages(source, source.getPageIndices());
                        pages.forEach(page => out.addPage(page));
                    } else if (mime.includes('png') || mime.includes('jpeg') || mime.includes('jpg')) {
                        const img = mime.includes('png') ? await out.embedPng(bytes) : await out.embedJpg(bytes);
                        const page = out.addPage([img.width, img.height]);
                        page.drawImage(img, { x: 0, y: 0, width: img.width, height: img.height });
                    } else throw new Error('Unsupported label type for ' + doc.buyer + '. Use individual browser printing.');
                }
                this.pdf = await out.save();
                this.message = 'Ready: ' + out.getPageCount() + ' label pages. Review the PDF and choose a printer before printing.';
            } catch (e) { this.message = e.message || 'Could not combine labels'; }
            finally { this.busy = false; }
        },
        download() {
            if (!this.pdf) return;
            const url = URL.createObjectURL(new Blob([this.pdf], {type:'application/pdf'}));
            const link = document.createElement('a'); link.href = url; link.download = 'vortexops-shipping-labels.pdf'; link.click();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
        },
        browserPrint() {
            if (!this.pdf) return;
            const url = URL.createObjectURL(new Blob([this.pdf], {type:'application/pdf'}));
            window.open(url, '_blank', 'noopener');
            this.message = 'Combined PDF opened. Use its Print button and select your printer. No physical print confirmation is recorded.';
            setTimeout(() => URL.revokeObjectURL(url), 120000);
        },
        async connectPrinter() {
            if (!window.qz) { this.message = 'QZ Tray script unavailable. Install/start QZ Tray and reload, or use browser printing.'; return; }
            try {
                if (!qz.websocket.isActive()) await qz.websocket.connect();
                this.printers = await qz.printers.find();
                this.message = this.printers.length ? 'QZ Tray connected. Select your thermal printer.' : 'Connected, but no printers were detected.';
            } catch(e) { this.message = 'QZ Tray connection failed: ' + (e.message || e); }
        },
        async directPrint() {
            if (!this.pdf || !this.printer || !window.qz || !this.acknowledged) return;
            this.busy = true;
            try {
                if (!qz.websocket.isActive()) await qz.websocket.connect();
                const config = qz.configs.create(this.printer, this.paperSize === '4x6' ? { size: { width: 4, height: 6 }, units: 'in' } : { size: { width: 8.5, height: 11 }, units: 'in' });
                let binary = ''; const bytes = this.pdf;
                for (let i = 0; i < bytes.length; i += 8192) binary += String.fromCharCode(...bytes.subarray(i, i + 8192));
                await qz.print(config, [{ type:'pixel', format:'pdf', flavor:'base64', data:btoa(binary) }]);
                this.message = 'Print job submitted to ' + this.printer + '. Verify output at the printer; physical printing is not confirmed.';
            } catch(e) { this.message = 'Printer rejected the job: ' + (e.message || e) + '. Use browser printing instead.'; }
            finally { this.busy = false; }
        }
    }));
});
</script>
@endonce


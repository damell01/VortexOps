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
                <button type="button" wire:click="preparePrintBatch" wire:loading.attr="disabled" wire:target="preparePrintBatch" class="min-h-11 rounded-xl bg-violet-700 px-4 font-semibold text-white hover:bg-violet-800">Prepare selected labels ({{ count($selectedShipmentIds) }})</button>
            </div>
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
                                    <a href="{{ $document['url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-violet-700 px-3 py-2 text-xs font-bold text-white">Open / print</a>
                                </div>
                                <template x-if="open"><iframe src="{{ $document['url'] }}" title="Prepared label for {{ $document['buyer'] }}" style="margin-top:8px;width:100%;height:400px;min-width:min(340px,75vw);border:1px solid #ddd6fe;border-radius:8px"></iframe></template>
                            </div>
                        @endforeach
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
    </div>
</x-filament-panels::page>

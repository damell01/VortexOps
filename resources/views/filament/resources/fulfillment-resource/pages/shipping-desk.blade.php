<x-filament-panels::page>
    @php ['show' => $show, 'buyers' => $buyers] = $this->shippingData(); @endphp
    <div class="space-y-5">
        <header class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400">Fulfillment Center · Shipping workspace</p>
            <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $show->title }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review buyers, order lines, shipments, tracking and available Whatnot labels. Label creation is not enabled in this workspace yet.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_220px]">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Search buyers, order IDs or tracking
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search shipping records…" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                </label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Shipping status
                    <select wire:model.live="statusFilter" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="all">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="label_created">Label created</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                    </select>
                </label>
            </div>
        </header>
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
                            <div class="mb-2 rounded-xl border border-gray-200 p-3 text-sm dark:border-gray-700">
                                <div class="flex flex-wrap justify-between gap-2"><span class="font-semibold text-gray-950 dark:text-white">{{ $shipment->carrier ?: 'Carrier pending' }}</span><span class="text-gray-500">{{ str_replace('_', ' ', $shipment->status ?: 'Pending') }}</span></div>
                                <p class="mt-1 break-all text-xs text-gray-600 dark:text-gray-300">Tracking: {{ $shipment->tracking_number ?: 'Not available' }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $shipment->weight_oz !== null ? $shipment->weight_oz.' oz' : 'Weight pending' }} · {{ $shipment->item_count ?? 0 }} items</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if($label)<a href="{{ $label }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center rounded-lg bg-violet-600 px-3 font-semibold text-white hover:bg-violet-700">Open / print label</a>@endif
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

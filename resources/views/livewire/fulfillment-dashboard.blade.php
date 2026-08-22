<div class="space-y-5">
    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-primary-600">Fulfillment Operations</div>
                <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $show->title }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $show->primaryStreamer()?->name ?? 'Unassigned streamer' }} · {{ $show->show_date?->format('M j, Y') }}
                </p>
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Assigned: <span class="font-medium text-gray-900 dark:text-white">{{ $assignedUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                <div class="text-xs text-gray-500">Shipments</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($shipmentStats['total']) }}</div>
            </div>
            <div class="rounded-xl bg-green-50 p-4 dark:bg-green-950/30">
                <div class="text-xs text-green-700 dark:text-green-300">Delivered</div>
                <div class="mt-1 text-2xl font-semibold text-green-700 dark:text-green-200">{{ number_format($shipmentStats['delivered']) }}</div>
            </div>
            <div class="rounded-xl bg-blue-50 p-4 dark:bg-blue-950/30">
                <div class="text-xs text-blue-700 dark:text-blue-300">Open</div>
                <div class="mt-1 text-2xl font-semibold text-blue-700 dark:text-blue-200">{{ number_format($shipmentStats['open']) }}</div>
            </div>
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                <div class="text-xs text-gray-500">Shipping Spend</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">${{ number_format($shipmentStats['shipping_cost'], 2) }}</div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-950 dark:text-white">Whatnot Shipments</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Shipment records imported from Whatnot for this show.</p>
            </div>
        </div>

        @if($shipments->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No Whatnot shipment rows have been imported for this show yet.</div>
        @else
            <div class="grid gap-3 lg:grid-cols-2">
                @foreach($shipments->take(20) as $shipment)
                    <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-gray-950 dark:text-white">{{ $shipment->buyer_username ?: 'Recipient' }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $shipment->created_at_whatnot?->format('M j, Y g:i A') ?? '—' }}</div>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $shipment->status ? ucwords(str_replace('_',' ', $shipment->status)) : 'Unknown' }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div><div class="text-xs text-gray-500">Items</div><div class="font-medium">{{ $shipment->item_count ?? '—' }}</div></div>
                            <div><div class="text-xs text-gray-500">Weight</div><div class="font-medium">{{ $shipment->weight_oz ? number_format((float)$shipment->weight_oz,1).' oz' : '—' }}</div></div>
                            <div><div class="text-xs text-gray-500">Carrier</div><div class="font-medium">{{ $shipment->carrier ?: '—' }}</div></div>
                            <div><div class="text-xs text-gray-500">Tracking</div><div class="truncate font-mono text-xs">{{ $shipment->tracking_number ?: '—' }}</div></div>
                        </div>
                    </article>
                @endforeach
            </div>
            @if($shipments->count() > 20)
                <div class="mt-4 text-sm text-gray-500">Showing the newest 20 of {{ $shipments->count() }} shipments here. Use the show's Shipments action for the full list.</div>
            @endif
        @endif
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-semibold text-gray-950 dark:text-white">Packing / Order Queue</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Keep the existing item-level packing controls where order data is available.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach(['all' => 'All', 'pending' => 'Pending', 'label_created' => 'Label Created', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'] as $status => $label)
                    <button type="button" wire:click="$set('filterStatus', '{{ $status }}')"
                        class="rounded-lg px-3 py-2 text-sm font-medium {{ $filterStatus === $status ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="mt-5 space-y-3">
            @forelse($orders as $order)
                <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="font-medium text-gray-950 dark:text-white">{{ $order->item_name ?? 'Unknown Item' }}</div>
                            <div class="mt-1 text-xs text-gray-500">Buyer: {{ $order->buyer_display_name ?? $order->buyer_username ?? 'Unknown' }} · Qty {{ $order->quantity ?? 1 }}</div>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $statusLabels[$order->shipping_status] ?? ucfirst(str_replace('_',' ', $order->shipping_status ?: 'pending')) }}</span>
                    </div>

                    <div class="mt-4">
                        @if(in_array($order->shipping_status, [null, '', 'pending', 'label_created'], true))
                            <button wire:click="markAsPacked({{ $order->id }})" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Mark Packed</button>
                        @elseif($order->shipping_status === 'packed')
                            <form wire:submit="markAsShipped({{ $order->id }}, $event.target.elements.tracking.value)" class="flex flex-col gap-2 sm:flex-row">
                                <input name="tracking" type="text" placeholder="Tracking number" class="min-w-0 flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white">Mark Shipped</button>
                            </form>
                        @elseif($order->shipping_status === 'shipped')
                            <div class="text-sm text-green-700 dark:text-green-300">Shipped{{ $order->tracking_number ? ' · '.$order->tracking_number : '' }}</div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No order rows match this status.</div>
            @endforelse
        </div>
    </section>
</div>

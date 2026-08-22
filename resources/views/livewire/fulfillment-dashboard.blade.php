<div class="space-y-3 pb-24 sm:space-y-5 sm:pb-0">
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Fulfillment Operations</div>
                <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $show->title }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                    {{ $show->primaryStreamer()?->name ?? 'Unassigned streamer' }} · {{ $show->show_date?->format('M j, Y') }}
                </p>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                Assigned: <span class="font-medium text-gray-900 dark:text-white">{{ $assignedUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-5 sm:gap-3 lg:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800 sm:rounded-xl sm:p-4">
                <div class="text-[10px] text-gray-500 sm:text-xs">Shipments</div>
                <div class="mt-0.5 text-xl font-semibold text-gray-950 dark:text-white sm:mt-1 sm:text-2xl">{{ number_format($shipmentStats['total']) }}</div>
            </div>
            <div class="rounded-lg bg-green-50 p-3 dark:bg-green-950/30 sm:rounded-xl sm:p-4">
                <div class="text-[10px] text-green-700 dark:text-green-300 sm:text-xs">Delivered</div>
                <div class="mt-0.5 text-xl font-semibold text-green-700 dark:text-green-200 sm:mt-1 sm:text-2xl">{{ number_format($shipmentStats['delivered']) }}</div>
            </div>
            <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-950/30 sm:rounded-xl sm:p-4">
                <div class="text-[10px] text-blue-700 dark:text-blue-300 sm:text-xs">Open</div>
                <div class="mt-0.5 text-xl font-semibold text-blue-700 dark:text-blue-200 sm:mt-1 sm:text-2xl">{{ number_format($shipmentStats['open']) }}</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800 sm:rounded-xl sm:p-4">
                <div class="text-[10px] text-gray-500 sm:text-xs">Shipping Spend</div>
                <div class="mt-0.5 text-lg font-semibold text-gray-950 dark:text-white sm:mt-1 sm:text-2xl">${{ number_format($shipmentStats['shipping_cost'], 2) }}</div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="mb-3 sm:mb-4">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Whatnot Shipments</h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">Shipment records imported from Whatnot for this show.</p>
        </div>

        @if($shipments->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-7 text-center text-xs text-gray-500 dark:border-gray-700 sm:p-8 sm:text-sm">No Whatnot shipment rows have been imported for this show yet.</div>
        @else
            <div class="grid gap-2.5 sm:gap-3 lg:grid-cols-2">
                @foreach($shipments->take(20) as $shipment)
                    <article class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-gray-950 dark:text-white sm:text-base">{{ $shipment->buyer_username ?: 'Recipient' }}</div>
                                <div class="mt-0.5 text-[10px] text-gray-500 sm:mt-1 sm:text-xs">{{ $shipment->created_at_whatnot?->format('M j, Y g:i A') ?? '—' }}</div>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:px-2.5 sm:text-xs">{{ $shipment->status ? ucwords(str_replace('_',' ', $shipment->status)) : 'Unknown' }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:mt-4 sm:grid-cols-4 sm:gap-3 sm:text-sm">
                            <div><div class="text-[10px] text-gray-500 sm:text-xs">Items</div><div class="font-medium">{{ $shipment->item_count ?? '—' }}</div></div>
                            <div><div class="text-[10px] text-gray-500 sm:text-xs">Weight</div><div class="font-medium">{{ $shipment->weight_oz ? number_format((float)$shipment->weight_oz,1).' oz' : '—' }}</div></div>
                            <div><div class="text-[10px] text-gray-500 sm:text-xs">Carrier</div><div class="truncate font-medium">{{ $shipment->carrier ?: '—' }}</div></div>
                            <div><div class="text-[10px] text-gray-500 sm:text-xs">Tracking</div><div class="truncate font-mono text-[10px] sm:text-xs">{{ $shipment->tracking_number ?: '—' }}</div></div>
                        </div>
                    </article>
                @endforeach
            </div>
            @if($shipments->count() > 20)
                <div class="mt-3 text-xs text-gray-500 sm:mt-4 sm:text-sm">Showing the newest 20 of {{ $shipments->count() }} shipments here.</div>
            @endif
        @endif
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Packing / Order Queue</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ $pendingPackingCount }} packing {{ \Illuminate\Support\Str::plural('line', $pendingPackingCount) }} still need attention.</p>
            </div>
            <div class="flex gap-1.5 overflow-x-auto pb-1 sm:flex-wrap sm:gap-2 sm:overflow-visible sm:pb-0">
                @foreach(['all' => 'All', 'pending' => 'Pending', 'label_created' => 'Label', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'] as $status => $label)
                    <button type="button" wire:click="$set('filterStatus', '{{ $status }}')"
                        class="min-h-9 shrink-0 rounded-lg px-2.5 py-1.5 text-[11px] font-medium sm:px-3 sm:py-2 sm:text-sm {{ $filterStatus === $status ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="mt-4 space-y-2.5 sm:mt-5 sm:space-y-3">
            @forelse($orders as $order)
                <article class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-950 dark:text-white sm:text-base">{{ $order->item_name ?? 'Unknown Item' }}</div>
                            <div class="mt-0.5 text-[10px] text-gray-500 sm:mt-1 sm:text-xs">Buyer: {{ $order->buyer_display_name ?? $order->buyer_username ?? 'Unknown' }} · Qty {{ $order->quantity ?? 1 }}</div>
                        </div>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:px-2.5 sm:text-xs">{{ $statusLabels[$order->shipping_status] ?? ucfirst(str_replace('_',' ', $order->shipping_status ?: 'pending')) }}</span>
                    </div>

                    <div class="mt-3 sm:mt-4">
                        @if(in_array($order->shipping_status, [null, '', 'pending', 'label_created'], true))
                            <button wire:click="markAsPacked({{ $order->id }})" class="min-h-10 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white sm:text-sm">Mark Packed</button>
                        @elseif($order->shipping_status === 'packed')
                            <form wire:submit="markAsShipped({{ $order->id }}, $event.target.elements.tracking.value)" class="flex flex-col gap-2 sm:flex-row">
                                <input name="tracking" type="text" placeholder="Tracking number" class="min-h-11 min-w-0 flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                <button type="submit" class="min-h-11 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white">Mark Shipped</button>
                            </form>
                        @elseif($order->shipping_status === 'shipped')
                            <div class="text-xs text-green-700 dark:text-green-300 sm:text-sm">Shipped{{ $order->tracking_number ? ' · '.$order->tracking_number : '' }}</div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-7 text-center text-xs text-gray-500 dark:border-gray-700 sm:p-8 sm:text-sm">No order rows match this status.</div>
            @endforelse
        </div>
    </section>

    @if($pendingPackingCount > 0)
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden">
            <button type="button" wire:click="markNextAsPacked" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white">
                Mark Next Packed · {{ $pendingPackingCount }} left
            </button>
        </div>
    @endif
</div>

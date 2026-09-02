<div class="space-y-3 pb-24 sm:space-y-5 sm:pb-0">
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Fulfillment Workstation</div>
                <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $show->title }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ $show->primaryStreamer()?->name ?? 'Unassigned streamer' }} · {{ $show->show_date?->format('M j, Y') }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                Assigned: <span class="font-semibold text-gray-950 dark:text-white">{{ $assignedUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
            @foreach([
                ['Pending Pack',$pendingPackingCount,'amber'],
                ['Packed',$packedCount,'blue'],
                ['Shipped',$shippedCount,'indigo'],
                ['Delivered',$deliveredOrderCount,'green'],
                ['Open Shipments',$shipmentStats['open'],'blue'],
                ['Shipping Spend','$'.number_format($shipmentStats['shipping_cost'],2),'gray'],
            ] as [$label,$value,$tone])
                <div class="rounded-lg bg-{{ $tone }}-50 p-3 dark:bg-{{ $tone }}-950/20">
                    <div class="text-[10px] font-medium text-{{ $tone }}-700 dark:text-{{ $tone }}-300 sm:text-xs">{{ $label }}</div>
                    <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Packing Queue</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Search a buyer, item, order or tracking number, then work one line or several at once.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search buyer, item, order…" class="min-h-11 min-w-0 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 sm:w-64" />
                <div class="flex gap-1.5 overflow-x-auto">
                    @foreach(['all' => 'All', 'pending' => 'Pending', 'label_created' => 'Label', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'] as $status => $label)
                        <button type="button" wire:click="$set('filterStatus', '{{ $status }}')" class="min-h-11 shrink-0 rounded-lg px-3 text-xs font-semibold {{ $filterStatus === $status ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        @if(count($selectedOrders) > 0)
            <div class="mt-3 flex flex-col gap-2 rounded-xl border border-primary-200 bg-primary-50 p-3 dark:border-primary-900 dark:bg-primary-950/20 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm font-semibold text-primary-800 dark:text-primary-200">{{ count($selectedOrders) }} line(s) selected</div>
                <div class="flex gap-2">
                    <button wire:click="clearSelection" class="min-h-10 rounded-lg border border-primary-200 px-3 text-xs font-semibold text-primary-700 dark:border-primary-800 dark:text-primary-200">Clear</button>
                    <button wire:click="bulkMarkPacked" class="min-h-10 rounded-lg bg-primary-600 px-4 text-xs font-semibold text-white">Mark Selected Packed</button>
                </div>
            </div>
        @endif

        <div class="mt-4 space-y-2.5">
            @forelse($orders as $order)
                @php $packable = in_array($order->shipping_status, [null, '', 'pending', 'label_created'], true); @endphp
                <article class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                    <div class="flex items-start gap-3">
                        @if($packable)
                            <input type="checkbox" wire:model.live="selectedOrders" value="{{ $order->id }}" class="mt-1 h-5 w-5 rounded border-gray-300 text-primary-600" />
                        @else
                            <div class="mt-1 h-5 w-5 shrink-0"></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $order->item_name ?? 'Unknown Item' }}</div>
                                    <div class="mt-0.5 text-[10px] text-gray-500 sm:text-xs">Buyer: {{ $order->buyer_display_name ?? $order->buyer_username ?? 'Unknown' }} · Qty {{ $order->quantity ?? 1 }} @if($order->lot_number)· Lot {{ $order->lot_number }}@endif</div>
                                </div>
                                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-[10px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $statusLabels[$order->shipping_status] ?? ucfirst(str_replace('_',' ', $order->shipping_status ?: 'pending')) }}</span>
                            </div>

                            <div class="mt-3">
                                @if($packable)
                                    <button wire:click="markAsPacked({{ $order->id }})" class="min-h-10 rounded-lg bg-primary-600 px-4 text-xs font-semibold text-white">Mark Packed</button>
                                @elseif($order->shipping_status === 'packed')
                                    <form wire:submit="markAsShipped({{ $order->id }}, $event.target.elements.tracking.value)" class="flex flex-col gap-2 sm:flex-row">
                                        <input name="tracking" type="text" placeholder="Tracking number" class="min-h-11 min-w-0 flex-1 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                                        <button type="submit" class="min-h-11 rounded-lg bg-green-600 px-4 text-sm font-semibold text-white">Mark Shipped</button>
                                    </form>
                                @elseif(in_array($order->shipping_status, ['shipped','delivered'], true))
                                    <div class="text-xs text-green-700 dark:text-green-300">{{ ucfirst($order->shipping_status) }}{{ $order->tracking_number ? ' · '.$order->tracking_number : '' }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No order rows match the current search and status.</div>
            @endforelse
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Whatnot Shipment Feed</h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Imported shipment status is shown separately from the internal packing queue.</p>
        </div>
        @if($shipments->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-7 text-center text-xs text-gray-500 dark:border-gray-700">No Whatnot shipment rows have been imported for this show yet.</div>
        @else
            <div class="grid gap-2.5 lg:grid-cols-2">
                @foreach($shipments->take(20) as $shipment)
                    <article class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $shipment->buyer_username ?: 'Recipient' }}</div><div class="mt-1 text-[10px] text-gray-500">{{ $shipment->created_at_whatnot?->format('M j, g:i A') ?? '—' }}</div></div>
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $shipment->status ? ucwords(str_replace('_',' ', $shipment->status)) : 'Unknown' }}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                            <div><div class="text-[10px] text-gray-500">Items</div><div class="font-medium">{{ $shipment->item_count ?? '—' }}</div></div>
                            <div><div class="text-[10px] text-gray-500">Weight</div><div class="font-medium">{{ $shipment->weight_oz ? number_format((float)$shipment->weight_oz,1).' oz' : '—' }}</div></div>
                            <div><div class="text-[10px] text-gray-500">Carrier</div><div class="truncate font-medium">{{ $shipment->carrier ?: '—' }}</div></div>
                            <div><div class="text-[10px] text-gray-500">Tracking</div><div class="truncate font-mono text-[10px]">{{ $shipment->tracking_number ?: '—' }}</div></div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    @if($pendingPackingCount > 0)
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden">
            @if(count($selectedOrders) > 0)
                <button type="button" wire:click="bulkMarkPacked" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white">Mark {{ count($selectedOrders) }} Selected Packed</button>
            @else
                <button type="button" wire:click="markNextAsPacked" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white">Mark Next Packed · {{ $pendingPackingCount }} left</button>
            @endif
        </div>
    @endif
</div>

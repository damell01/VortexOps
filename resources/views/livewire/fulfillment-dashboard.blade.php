<div class="space-y-3 pb-24 sm:space-y-5 sm:pb-0">
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Fulfillment Workstation</div>
                <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $show->title }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $show->primaryStreamer()?->name ?? 'Unassigned streamer' }} · {{ $show->show_date?->format('M j, Y') }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                Assigned: <span class="font-semibold text-gray-950 dark:text-white">{{ $assignedUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @foreach([
                ['Logged Items',$allLines->count(),'gray'],
                ['Pending Review',$pendingCount,'amber'],
                ['Fulfilled',$fulfilledCount,'green'],
                ['Not Fulfilled',$notFulfilledCount,'red'],
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
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Streamer-Logged Items</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Review what the streamer logged against this show. There is no buyer or Whatnot-order mapping required.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search item, SKU, barcode…" class="min-h-11 min-w-0 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 sm:w-64" />
                <div class="flex gap-1.5 overflow-x-auto">
                    @foreach(['all' => 'All', 'pending' => 'Pending', 'fulfilled' => 'Fulfilled', 'not_fulfilled' => 'Not Fulfilled'] as $status => $label)
                        <button type="button" wire:click="$set('filterStatus', '{{ $status }}')" class="min-h-11 shrink-0 rounded-lg px-3 text-xs font-semibold {{ $filterStatus === $status ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        @if($pendingCount > 0)
            <div class="mt-3 flex flex-col gap-2 rounded-xl border border-primary-200 bg-primary-50 p-3 dark:border-primary-900 dark:bg-primary-950/20 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-primary-800 dark:text-primary-200">{{ $pendingCount }} item line(s) still need review</div>
                    <div class="mt-0.5 text-xs text-primary-700/80 dark:text-primary-300/80">If everything for the show was fulfilled cleanly, you can finish the pending items at once.</div>
                </div>
                <button wire:click="markAllFulfilled" wire:confirm="Mark every pending logged item as fulfilled?" class="min-h-11 rounded-lg bg-primary-600 px-4 text-xs font-semibold text-white">Mark All Pending Fulfilled</button>
            </div>
        @endif

        <div class="mt-4 space-y-2.5">
            @forelse($lines as $line)
                @php
                    $item = $line->inventoryItem;
                    $status = $line->fulfillmentStatus();
                    $qty = $line->quantity_approved ?? $line->quantity_suggested ?? 0;
                    $badge = match($status) {
                        'fulfilled' => 'bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-300',
                        'not_fulfilled' => 'bg-red-100 text-red-700 dark:bg-red-950/30 dark:text-red-300',
                        default => 'bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300',
                    };
                @endphp
                <article class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item?->name ?? $line->raw_description ?? 'Logged Inventory Item' }}</div>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 sm:text-xs">
                                        <span>Qty {{ number_format((float) $qty, 0) }}</span>
                                        @if($item?->sku)<span>SKU {{ $item->sku }}</span>@endif
                                        @if($line->location?->name)<span>{{ $line->location->name }}</span>@endif
                                        @if($item?->barcode)<span>Barcode {{ $item->barcode }}</span>@elseif($item?->upc)<span>UPC {{ $item->upc }}</span>@endif
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $badge }}">{{ \App\Models\DeductionRequestLine::fulfillmentStatusLabels()[$status] ?? ucfirst(str_replace('_',' ', $status)) }}</span>
                            </div>

                            <div class="mt-3">
                                <label class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Fulfillment note</label>
                                <input wire:model.defer="notes.{{ $line->id }}" type="text" placeholder="Optional note; add a reason for issues" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
                            </div>

                            @if($line->fulfilled_at)
                                <div class="mt-2 text-[10px] text-gray-500">Reviewed {{ $line->fulfilled_at->format('M j, g:i A') }}{{ $line->fulfilledBy?->name ? ' by '.$line->fulfilledBy->name : '' }}</div>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2 lg:w-48 lg:flex-col">
                            <button wire:click="markFulfilled({{ $line->id }})" class="min-h-11 flex-1 rounded-lg bg-green-600 px-4 text-xs font-semibold text-white lg:flex-none">Fulfilled</button>
                            <button wire:click="markNotFulfilled({{ $line->id }})" class="min-h-11 flex-1 rounded-lg bg-red-600 px-4 text-xs font-semibold text-white lg:flex-none">Not Fulfilled</button>
                            @if($status !== 'pending')
                                <button wire:click="resetFulfillment({{ $line->id }})" class="min-h-11 flex-1 rounded-lg border border-gray-300 px-4 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200 lg:flex-none">Reset</button>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">
                    @if(!$request)
                        No streamer-logged inventory request exists for this show yet.
                    @else
                        No logged items match the current search and status.
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Whatnot Shipment Feed</h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Shipment data stays visible for show context only. It is not mapped to the logged inventory items above.</p>
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

    @if($pendingCount > 0)
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden">
            <button type="button" wire:click="markAllFulfilled" wire:confirm="Mark every pending logged item as fulfilled?" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white">Mark All Pending Fulfilled · {{ $pendingCount }} left</button>
        </div>
    @endif
</div>

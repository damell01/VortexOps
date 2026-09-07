<div class="space-y-3 pb-24 sm:space-y-4 sm:pb-0">
    <style>
        .vx-ful-card{border:1px solid rgb(229 231 235);border-radius:1rem;background:#fff}.dark .vx-ful-card{border-color:rgb(55 65 81);background:rgb(17 24 39)}.vx-ful-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.5rem}.vx-ful-kpi{border-radius:.75rem;padding:.7rem;background:rgb(249 250 251)}.dark .vx-ful-kpi{background:rgb(31 41 55)}.vx-ful-item{border:1px solid rgb(229 231 235);border-radius:.9rem;padding:.8rem}.dark .vx-ful-item{border-color:rgb(55 65 81)}.vx-ful-actions{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.vx-ful-actions .full{grid-column:1/-1}.vx-ful-filterbar{display:flex;gap:.4rem;overflow-x:auto;padding-bottom:.15rem}.vx-ful-filterbar button{white-space:nowrap}.vx-ful-ship{border:1px solid rgb(229 231 235);border-radius:.85rem;padding:.75rem}.dark .vx-ful-ship{border-color:rgb(55 65 81)}
        @media(max-width:760px){.vx-ful-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-ful-item{padding:.72rem}.vx-ful-actions{grid-template-columns:1fr 1fr}.vx-ful-card{border-radius:.85rem}.vx-ful-hide-mobile{display:none!important}}
    </style>

    <section class="vx-ful-card p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Fulfillment Workstation</div>
                <h2 class="mt-1 truncate text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $show->title }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $show->primaryStreamer()?->name ?? 'Unassigned streamer' }} · {{ $show->show_date?->format('M j, Y') }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">Assigned: <span class="font-semibold text-gray-950 dark:text-white">{{ $assignedUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</span></div>
        </div>

        <div class="vx-ful-kpis mt-4">
            @foreach([
                ['To Review',$pendingCount],
                ['Fulfilled',$fulfilledCount],
                ['Issues',$notFulfilledCount],
                ['Open Shipments',$shipmentStats['open']],
            ] as [$label,$value])
                <div class="vx-ful-kpi"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</div><div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div></div>
            @endforeach
        </div>
    </section>

    <section class="vx-ful-card p-4 sm:p-5">
        <div class="flex flex-col gap-3">
            <div><h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Items to Fulfill</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tap an item, add a note only when needed, then mark it fulfilled or flag an issue.</p></div>
            <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search item, SKU, barcode…" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
            <div class="vx-ful-filterbar">
                @foreach(['all' => 'All', 'pending' => 'Pending', 'not_fulfilled' => 'Issues', 'fulfilled' => 'Done'] as $status => $label)
                    <button type="button" wire:click="$set('filterStatus', '{{ $status }}')" class="min-h-10 shrink-0 rounded-lg px-3 text-xs font-semibold {{ $filterStatus === $status ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if($pendingCount > 0)
            <div class="mt-3 flex flex-col gap-2 rounded-xl border border-primary-200 bg-primary-50 p-3 dark:border-primary-900 dark:bg-primary-950/20 sm:flex-row sm:items-center sm:justify-between">
                <div><div class="text-sm font-semibold text-primary-800 dark:text-primary-200">{{ $pendingCount }} left to review</div><div class="mt-0.5 text-xs text-primary-700/80 dark:text-primary-300/80">If the whole show is clean, finish all remaining items at once.</div></div>
                <button wire:click="markAllFulfilled" wire:confirm="Mark every pending logged item as fulfilled?" class="min-h-11 rounded-lg bg-primary-600 px-4 text-xs font-semibold text-white">Mark All Fulfilled</button>
            </div>
        @endif

        <div class="mt-4 space-y-2.5">
            @forelse($lines as $line)
                @php
                    $item = $line->inventoryItem;
                    $status = $line->fulfillmentStatus();
                    $badge = match($status) {
                        'fulfilled' => 'bg-green-100 text-green-700 dark:bg-green-950/30 dark:text-green-300',
                        'not_fulfilled' => 'bg-red-100 text-red-700 dark:bg-red-950/30 dark:text-red-300',
                        default => 'bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300',
                    };
                @endphp
                <article class="vx-ful-item">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1"><div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $line->item_name ?: $item?->name ?: 'Logged Inventory Item' }}</div><div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 sm:text-xs"><span class="font-semibold">Qty {{ number_format((float) $line->quantity, 0) }}</span>@if($item?->sku)<span>SKU {{ $item->sku }}</span>@endif @if($line->location?->name)<span>{{ $line->location->name }}</span>@endif @if($item?->barcode)<span class="vx-ful-hide-mobile">Barcode {{ $item->barcode }}</span>@elseif($item?->upc)<span class="vx-ful-hide-mobile">UPC {{ $item->upc }}</span>@endif</div></div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $badge }}">{{ \App\Models\StreamerLogItem::fulfillmentStatusLabels()[$status] ?? ucfirst(str_replace('_',' ', $status)) }}</span>
                    </div>

                    <div class="mt-3"><label class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Note</label><input wire:model.defer="notes.{{ $line->id }}" type="text" placeholder="Optional; add reason for an issue" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" /></div>

                    @if($line->fulfilled_at)<div class="mt-2 text-[10px] text-gray-500">Reviewed {{ $line->fulfilled_at->format('M j, g:i A') }}{{ $line->fulfilledBy?->name ? ' by '.$line->fulfilledBy->name : '' }}</div>@endif

                    <div class="vx-ful-actions mt-3">
                        <button wire:click="markFulfilled({{ $line->id }})" class="min-h-11 rounded-lg bg-green-600 px-3 text-xs font-semibold text-white">✓ Fulfilled</button>
                        <button wire:click="markNotFulfilled({{ $line->id }})" class="min-h-11 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white">Flag Issue</button>
                        @if($status !== 'pending')<button wire:click="resetFulfillment({{ $line->id }})" class="full min-h-10 rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Reset Status</button>@endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">@if(!$report) No streamer log exists for this show yet. @else No items match this filter. @endif</div>
            @endforelse
        </div>
    </section>

    <section class="vx-ful-card p-4 sm:p-5">
        <details>
            <summary class="cursor-pointer list-none"><div class="flex items-center justify-between gap-3"><div><h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Whatnot Shipment Reference</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $shipmentStats['open'] }} open · {{ $shipmentStats['delivered'] }} delivered · ${{ number_format($shipmentStats['shipping_cost'],2) }} shipping</p></div><span class="text-xs font-semibold text-primary-600">View</span></div></summary>
            <div class="mt-3">
                @if($shipments->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-xs text-gray-500 dark:border-gray-700">No shipment rows imported for this show yet.</div>
                @else
                    <div class="grid gap-2.5 md:grid-cols-2">
                        @foreach($shipments->take(20) as $shipment)
                            <article class="vx-ful-ship"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $shipment->buyer_username ?: 'Recipient' }}</div><div class="mt-1 text-[10px] text-gray-500">{{ $shipment->created_at_whatnot?->format('M j, g:i A') ?? '—' }}</div></div><span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $shipment->status ? ucwords(str_replace('_',' ', $shipment->status)) : 'Unknown' }}</span></div><div class="mt-3 grid grid-cols-2 gap-2 text-xs"><div><div class="text-[10px] text-gray-500">Items</div><div class="font-medium">{{ $shipment->item_count ?? '—' }}</div></div><div><div class="text-[10px] text-gray-500">Carrier</div><div class="truncate font-medium">{{ $shipment->carrier ?: '—' }}</div></div></div></article>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>
    </section>

    @if($pendingCount > 0)
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden"><button type="button" wire:click="markAllFulfilled" wire:confirm="Mark every pending logged item as fulfilled?" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white">Mark All Fulfilled · {{ $pendingCount }} left</button></div>
    @endif
</div>
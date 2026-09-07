<div class="space-y-3 pb-24 sm:space-y-4 sm:pb-0">
    <style>
        .vx-ful-card{border:1px solid rgb(229 231 235);border-radius:1rem;background:#fff}.dark .vx-ful-card{border-color:rgb(55 65 81);background:rgb(17 24 39)}.vx-ful-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.5rem}.vx-ful-kpi{border-radius:.75rem;padding:.7rem;background:rgb(249 250 251)}.dark .vx-ful-kpi{background:rgb(31 41 55)}.vx-ful-item,.vx-box{border:1px solid rgb(229 231 235);border-radius:.9rem;padding:.8rem}.dark .vx-ful-item,.dark .vx-box{border-color:rgb(55 65 81)}.vx-ful-actions{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.vx-ful-actions .full{grid-column:1/-1}.vx-ful-filterbar{display:flex;gap:.4rem;overflow-x:auto;padding-bottom:.15rem}.vx-ful-filterbar button{white-space:nowrap}.vx-progress{height:.45rem;border-radius:999px;background:rgb(229 231 235);overflow:hidden}.dark .vx-progress{background:rgb(55 65 81)}.vx-progress>span{display:block;height:100%;background:rgb(37 99 235)}.vx-box-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem}.vx-label{border:2px solid #111827;border-radius:.5rem;padding:.75rem;background:white;color:#111827}.vx-label h4{font-size:.85rem;font-weight:900}.vx-label small{font-size:.62rem}.vx-label ul{margin:.5rem 0 0;padding-left:1rem;font-size:.72rem}.vx-label-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-weight:900;letter-spacing:.08em}
        @media(max-width:760px){.vx-ful-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-ful-item,.vx-box{padding:.72rem}.vx-box-grid{grid-template-columns:1fr}.vx-ful-hide-mobile{display:none!important}}
        @media print{body *{visibility:hidden!important}.vx-label-print,.vx-label-print *{visibility:visible!important}.vx-label-print{position:absolute!important;left:0!important;top:0!important;width:100%!important}.vx-no-print{display:none!important}}
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
                ['Units Left',$pendingCount],
                ['Lines Done',$fulfilledCount],
                ['Issues',$notFulfilledCount],
                ['Boxes',$packages->count()],
            ] as [$label,$value])
                <div class="vx-ful-kpi"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</div><div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div></div>
            @endforeach
        </div>
    </section>

    <section class="vx-ful-card p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Current Box</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Create a box first, then scan or tap items into that box.</p></div>
            <div class="flex gap-2"><input wire:model.defer="newPackageBuyer" type="text" placeholder="Buyer (optional)" class="min-h-11 min-w-0 flex-1 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" /><button wire:click="createPackage" class="min-h-11 rounded-lg bg-primary-600 px-4 text-xs font-semibold text-white">+ New Box</button></div>
        </div>

        @if($packages->isNotEmpty())
            <div class="vx-box-grid mt-3">
                @foreach($packages as $package)
                    <div class="vx-box {{ $activePackageId === $package->id ? 'ring-2 ring-primary-500' : '' }}">
                        <div class="flex items-start justify-between gap-2"><div><div class="text-sm font-semibold text-gray-950 dark:text-white">Box {{ $package->box_number }} of {{ max($package->box_total, $packages->count()) }}</div><div class="mt-0.5 text-[10px] text-gray-500">{{ $package->package_code }}{{ $package->buyer_username ? ' · @'.$package->buyer_username : '' }}</div></div><span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $package->isSealed() ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $package->isSealed() ? 'Sealed' : 'Building' }}</span></div>
                        <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $package->totalItems() }} item(s)</div>
                        <div class="mt-3 flex flex-wrap gap-2 vx-no-print">
                            @if(!$package->isSealed())<button wire:click="selectPackage({{ $package->id }})" class="min-h-10 rounded-lg border border-gray-300 px-3 text-xs font-semibold dark:border-gray-600">Use This Box</button><button wire:click="sealPackage({{ $package->id }})" wire:confirm="Seal this box?" class="min-h-10 rounded-lg bg-green-600 px-3 text-xs font-semibold text-white">Seal Box</button>@endif
                            <button type="button" wire:click="markLabelPrinted({{ $package->id }})" onclick="setTimeout(() => window.print(), 150)" class="min-h-10 rounded-lg bg-gray-900 px-3 text-xs font-semibold text-white dark:bg-white dark:text-gray-900">Print Label</button>
                        </div>

                        <div class="vx-label vx-label-print mt-3">
                            <div class="flex items-start justify-between gap-3"><div><h4>VORTEXOPS · BOX {{ $package->box_number }} OF {{ max($package->box_total, $packages->count()) }}</h4><small>{{ $show->title }} · {{ $show->show_date?->format('M j, Y') }}</small></div><div class="text-right"><div class="vx-label-code">{{ $package->package_code }}</div><small>{{ $package->buyer_username ? '@'.$package->buyer_username : 'Buyer not set' }}</small></div></div>
                            <div class="mt-2 border-t border-gray-300 pt-2 text-[10px] font-bold uppercase">Contents</div>
                            <ul>
                                @forelse($package->items as $packageItem)
                                    <li>{{ $packageItem->streamerLogItem?->item_name ?: $packageItem->streamerLogItem?->inventoryItem?->name ?: 'Item' }} ×{{ (int)$packageItem->quantity }}</li>
                                @empty
                                    <li>No items packed yet.</li>
                                @endforelse
                            </ul>
                            <div class="mt-2 border-t border-gray-300 pt-2 text-[10px]">{{ $package->totalItems() }} total item(s) · Packed by {{ $package->packedBy?->name ?? auth()->user()?->name ?? 'Fulfillment' }}{{ $package->sealed_at ? ' · '.$package->sealed_at->format('M j, g:i A') : '' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-3 rounded-xl border border-dashed border-gray-300 p-5 text-center text-xs text-gray-500 dark:border-gray-700">No box created yet. Create Box 1 to start packing.</div>
        @endif
    </section>

    <section class="vx-ful-card p-4 sm:p-5">
        <div><h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Scan Item</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Scan barcode, UPC or SKU. Each scan packs one unit into the selected box.</p></div>
        <div class="mt-3 flex gap-2"><input wire:model.defer="scanCode" wire:keydown.enter="scanItem" type="text" inputmode="text" autocomplete="off" placeholder="Scan barcode / UPC / SKU" class="min-h-12 min-w-0 flex-1 rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800" /><button wire:click="scanItem" class="min-h-12 rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white">Scan</button></div>
        @if($activePackage)<div class="mt-2 text-xs font-medium text-primary-700 dark:text-primary-300">Packing into {{ $activePackage->package_code }} · Box {{ $activePackage->box_number }}</div>@else<div class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">Select or create a box before packing.</div>@endif
    </section>

    <section class="vx-ful-card p-4 sm:p-5">
        <div class="flex flex-col gap-3">
            <div><h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Streamer-Logged Items</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">These are the items the streamer says were used in the show. Pack them into the selected box.</p></div>
            <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search item, SKU, barcode…" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
            <div class="vx-ful-filterbar">@foreach(['all'=>'All','pending'=>'To Pack','not_fulfilled'=>'Issues','fulfilled'=>'Done'] as $status=>$label)<button type="button" wire:click="$set('filterStatus','{{ $status }}')" class="min-h-10 rounded-lg px-3 text-xs font-semibold {{ $filterStatus === $status ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $label }}</button>@endforeach</div>
        </div>

        <div class="mt-4 space-y-2.5">
            @forelse($lines as $line)
                @php $item=$line->inventoryItem; $status=$line->fulfillmentStatus(); $total=max(1,(int)$line->quantity); $packed=min((int)$line->packed_quantity,$total); $pct=min(100,round(($packed/$total)*100)); @endphp
                <article class="vx-ful-item">
                    <div class="flex items-start justify-between gap-2"><div class="min-w-0 flex-1"><div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $line->item_name ?: $item?->name ?: 'Logged Inventory Item' }}</div><div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 sm:text-xs"><span class="font-semibold">{{ $line->packingProgressLabel() }}</span>@if($item?->sku)<span>SKU {{ $item->sku }}</span>@endif @if($line->location?->name)<span>{{ $line->location->name }}</span>@endif</div></div><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $status === 'fulfilled' ? 'bg-green-100 text-green-700' : ($status === 'not_fulfilled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ \App\Models\StreamerLogItem::fulfillmentStatusLabels()[$status] ?? 'Packing' }}</span></div>
                    <div class="vx-progress mt-3"><span style="width:{{ $pct }}%"></span></div>
                    <div class="mt-3"><input wire:model.defer="notes.{{ $line->id }}" type="text" placeholder="Optional note / issue reason" class="min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" /></div>
                    <div class="vx-ful-actions mt-3">
                        @if($line->remainingToPack() > 0)<button wire:click="packOne({{ $line->id }})" class="min-h-11 rounded-lg bg-primary-600 px-3 text-xs font-semibold text-white">+ Pack 1</button><button wire:click="packRemaining({{ $line->id }})" class="min-h-11 rounded-lg bg-green-600 px-3 text-xs font-semibold text-white">Pack Remaining {{ $line->remainingToPack() }}</button>@endif
                        <button wire:click="markNotFulfilled({{ $line->id }})" class="min-h-11 rounded-lg bg-red-600 px-3 text-xs font-semibold text-white {{ $line->remainingToPack() <= 0 ? 'full' : '' }}">Flag Issue</button>
                        @if($packed > 0 || $status !== 'pending')<button wire:click="resetFulfillment({{ $line->id }})" wire:confirm="Reset this line and remove it from any VortexOps boxes?" class="min-h-10 rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Reset</button>@endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No items match this filter.</div>
            @endforelse
        </div>
    </section>

    <section class="vx-ful-card p-4 sm:p-5">
        <details><summary class="cursor-pointer list-none"><div class="flex items-center justify-between gap-3"><div><h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Whatnot Shipment Reference</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $shipmentStats['open'] }} open · {{ $shipmentStats['delivered'] }} delivered</p></div><span class="text-xs font-semibold text-primary-600">View</span></div></summary><div class="mt-3 grid gap-2 md:grid-cols-2">@forelse($shipments->take(20) as $shipment)<div class="vx-box"><div class="text-sm font-semibold">{{ $shipment->buyer_username ?: 'Recipient' }}</div><div class="mt-1 text-[10px] text-gray-500">{{ $shipment->status ? ucwords(str_replace('_',' ',$shipment->status)) : 'Unknown' }}{{ $shipment->tracking_number ? ' · '.$shipment->tracking_number : '' }}</div></div>@empty<div class="text-xs text-gray-500">No shipment rows imported yet.</div>@endforelse</div></details>
    </section>
</div>
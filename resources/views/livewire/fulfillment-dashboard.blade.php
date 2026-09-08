<div class="space-y-3 pb-24 sm:space-y-4 sm:pb-0" x-data x-on:print-fulfillment-label.window="$nextTick(() => setTimeout(() => window.print(), 100))">
    <style>
        .vx-ful-card{border:1px solid rgb(229 231 235);border-radius:1rem;background:#fff}.dark .vx-ful-card{border-color:rgb(55 65 81);background:rgb(17 24 39)}
        .vx-pack-dock{position:sticky;top:4.25rem;z-index:20;border:1px solid rgb(191 219 254);border-radius:1rem;background:rgba(239,246,255,.96);box-shadow:0 10px 24px rgba(15,23,42,.08);backdrop-filter:blur(10px)}.dark .vx-pack-dock{border-color:rgb(30 64 175);background:rgba(23,37,84,.94)}
        .vx-ful-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.5rem}.vx-ful-kpi{border-radius:.75rem;padding:.7rem;background:rgb(249 250 251)}.dark .vx-ful-kpi{background:rgb(31 41 55)}
        .vx-ful-item,.vx-box{border:1px solid rgb(229 231 235);border-radius:.9rem;padding:.8rem}.dark .vx-ful-item,.dark .vx-box{border-color:rgb(55 65 81)}
        .vx-ful-item{transition:border-color .15s ease,box-shadow .15s ease}.vx-ful-item:hover{border-color:rgb(147 197 253);box-shadow:0 6px 16px rgba(15,23,42,.05)}
        .vx-ful-actions{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.vx-ful-actions .full{grid-column:1/-1}.vx-ful-filterbar{display:flex;gap:.4rem;overflow-x:auto;padding-bottom:.15rem}.vx-ful-filterbar button{white-space:nowrap}
        .vx-progress{height:.5rem;border-radius:999px;background:rgb(229 231 235);overflow:hidden}.dark .vx-progress{background:rgb(55 65 81)}.vx-progress>span{display:block;height:100%;background:rgb(37 99 235)}
        .vx-box-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem}.vx-label{border:2px solid #111827;border-radius:.5rem;padding:.75rem;background:white;color:#111827}.vx-label h4{font-size:.85rem;font-weight:900}.vx-label small{font-size:.62rem}.vx-label ul{margin:.5rem 0 0;padding-left:1rem;font-size:.72rem}.vx-label-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-weight:900;letter-spacing:.08em}.vx-package-mark{border:1px dashed #111827;padding:.35rem .5rem;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.68rem;font-weight:800;letter-spacing:.12em;text-align:center}.vx-label-qr{width:76px;height:76px;object-fit:contain;background:#fff}.vx-label-body{display:grid;grid-template-columns:minmax(0,1fr) 82px;gap:.6rem;align-items:start}
        @media(max-width:760px){.vx-pack-dock{top:3.75rem}.vx-ful-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-ful-item,.vx-box{padding:.72rem}.vx-box-grid{grid-template-columns:1fr}}
        @media print{body *{visibility:hidden!important}.vx-label-print-target,.vx-label-print-target *{visibility:visible!important}.vx-label-print-target{display:block!important;position:absolute!important;left:0!important;top:0!important;width:4in!important;min-height:2.5in!important;margin:0!important;border:2px solid #000!important;border-radius:0!important;padding:.18in!important;box-sizing:border-box!important}.vx-no-print{display:none!important}@page{size:4in 6in;margin:.15in}}
    </style>

    @php
        $totalUnits = max(0, (int)$allLines->sum('quantity'));
        $doneUnits = max(0, $totalUnits - (int)$pendingCount);
        $showProgress = $totalUnits > 0 ? min(100, (int)round(($doneUnits / $totalUnits) * 100)) : 0;
    @endphp

    <section class="vx-ful-card p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600 sm:text-xs">Packing Workstation</div>
                <h2 class="mt-1 truncate text-xl font-bold text-gray-950 dark:text-white sm:text-2xl">{{ $show->title }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $show->primaryStreamer()?->name ?? 'Unassigned streamer' }} · {{ $show->show_date?->format('M j, Y') }} · Assigned to {{ $assignedUsers->pluck('name')->join(', ') ?: 'nobody yet' }}</p>
            </div>
            <div class="rounded-xl px-3 py-2 text-xs font-semibold {{ $report?->fulfillment_reviewed_at ? 'bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-300' : ($canCompleteFulfillment ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300') }}">{{ $report?->fulfillment_reviewed_at ? 'Complete' : ($canCompleteFulfillment ? 'Ready to Complete' : 'In Progress') }}</div>
        </div>

        <div class="mt-4 flex items-center justify-between text-[10px] font-semibold text-gray-500 sm:text-xs"><span>Streamer-log packing progress</span><span>{{ $doneUnits }}/{{ $totalUnits }} units · {{ $showProgress }}%</span></div>
        <div class="vx-progress mt-1.5"><span style="width:{{ $showProgress }}%"></span></div>

        <div class="vx-ful-kpis mt-4">
            @foreach([['Units Left',$pendingCount],['Lines Done',$fulfilledCount],['Issues',$notFulfilledCount],['Boxes Tracked',$packages->count()]] as [$label,$value])
                <div class="vx-ful-kpi"><div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</div><div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $value }}</div></div>
            @endforeach
        </div>

        <div class="mt-4 flex flex-col gap-3 rounded-xl border p-3 sm:flex-row sm:items-center sm:justify-between {{ $report?->fulfillment_reviewed_at ? 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30' : ($canCompleteFulfillment ? 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800') }}">
            <div>
                <div class="text-xs font-bold text-gray-900 dark:text-white">{{ $report?->fulfillment_reviewed_at ? 'Fulfillment Complete' : ($canCompleteFulfillment ? 'Everything the streamer logged is accounted for' : 'Finish the remaining packing work') }}</div>
                <div class="mt-1 text-[11px] text-gray-600 dark:text-gray-300">@if($report?->fulfillment_reviewed_at)Completed {{ $report->fulfillment_reviewed_at->format('M j, g:i A') }}. Ready for payroll review.@elseif($canCompleteFulfillment)All logged units are packed, issues are resolved, and there are no open tracked boxes.@else{{ $pendingCount }} unit(s) left · {{ $notFulfilledCount }} issue(s){{ $openPackageCount ? ' · '.$openPackageCount.' open tracked box(es)' : '' }}.@endif</div>
            </div>
            @if(!$report?->fulfillment_reviewed_at)<button wire:click="completeFulfillment" wire:confirm="Mark fulfillment complete for this show?" @disabled(!$canCompleteFulfillment) class="min-h-11 shrink-0 rounded-xl px-4 text-xs font-bold {{ $canCompleteFulfillment ? 'bg-green-600 text-white hover:bg-green-500' : 'cursor-not-allowed bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">Complete Fulfillment</button>@endif
        </div>
    </section>

    <section class="vx-pack-dock p-3 sm:p-4">
        <div class="grid gap-3 lg:grid-cols-[250px_1fr] lg:items-end">
            <div>
                <div class="text-[9px] font-bold uppercase tracking-[.12em] text-blue-600 dark:text-blue-300">Optional Box Tracking</div>
                @if($activePackage)
                    <div class="mt-1 text-sm font-bold text-gray-950 dark:text-white">Box {{ $activePackage->box_number }} · {{ $activePackage->package_code }}</div>
                    <div class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-300">{{ $activePackage->totalItems() }} item(s) linked to this box</div>
                    <button type="button" wire:click="clearActivePackage" class="mt-1 text-[10px] font-semibold text-primary-600">Stop linking scans to this box</button>
                @else
                    <div class="mt-1 text-sm font-bold text-gray-950 dark:text-white">Packing from streamer log</div>
                    <div class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-300">No box is required. Scan or tap items now; use box tools only when useful.</div>
                @endif
            </div>
            <div>
                <div class="mb-1 text-[9px] font-bold uppercase tracking-[.12em] text-blue-600 dark:text-blue-300">Scan & Pack</div>
                <div class="flex gap-2"><input wire:model.defer="scanCode" wire:keydown.enter="scanItem" type="text" autocomplete="off" placeholder="Scan barcode / UPC / SKU" class="min-h-12 min-w-0 flex-1 rounded-xl border-blue-200 bg-white text-base dark:border-blue-800 dark:bg-gray-900"/><button wire:click="scanItem" class="min-h-12 rounded-xl bg-primary-600 px-5 text-sm font-bold text-white hover:bg-primary-500">Pack</button></div>
            </div>
        </div>
    </section>

    <section class="vx-ful-card p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h3 class="text-base font-bold text-gray-950 dark:text-white">Streamer Packing List</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">This is the job: pack the items and quantities the streamer logged. Whatnot buyer grouping does not change this list.</p></div>
            <input wire:model.live.debounce.250ms="search" type="search" placeholder="Search item, SKU, barcode…" class="min-h-11 w-full rounded-xl border-gray-300 text-sm sm:max-w-sm dark:border-gray-600 dark:bg-gray-800"/>
        </div>
        <div class="vx-ful-filterbar mt-3">@foreach(['all'=>'All','pending'=>'To Pack','not_fulfilled'=>'Issues','fulfilled'=>'Done'] as $status=>$label)<button type="button" wire:click="$set('filterStatus','{{ $status }}')" class="min-h-10 rounded-xl px-3 text-xs font-semibold {{ $filterStatus === $status ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">{{ $label }}</button>@endforeach</div>

        <div class="mt-4 space-y-2.5">
            @forelse($lines as $line)
                @php $item=$line->inventoryItem;$status=$line->fulfillmentStatus();$total=max(1,(int)$line->quantity);$packed=min((int)$line->packed_quantity,$total);$pct=min(100,round(($packed/$total)*100)); @endphp
                <article class="vx-ful-item">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1"><div class="text-sm font-bold text-gray-950 dark:text-white">{{ $line->item_name ?: $item?->name ?: 'Logged Item' }}</div><div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 sm:text-xs"><span class="font-bold">{{ $line->packingProgressLabel() }}</span>@if($item?->sku)<span>SKU {{ $item->sku }}</span>@endif @if(!$item)<span class="font-semibold text-amber-700">Unmapped — manual pack allowed</span>@endif @if($line->location?->name)<span>{{ $line->location->name }}</span>@endif</div></div>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $status === 'fulfilled' ? 'bg-green-100 text-green-700' : ($status === 'not_fulfilled' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ $status === 'fulfilled' ? 'Packed' : ($status === 'not_fulfilled' ? 'Issue' : 'To Pack') }}</span>
                    </div>
                    <div class="vx-progress mt-3"><span style="width:{{ $pct }}%"></span></div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto]">
                        <input wire:model.defer="notes.{{ $line->id }}" type="text" placeholder="Optional note / issue reason" class="min-h-11 w-full rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"/>
                        <div class="flex gap-2">@if($line->remainingToPack()>0)<button wire:click="packOne({{ $line->id }})" class="min-h-11 rounded-xl bg-primary-600 px-3 text-xs font-bold text-white">+1</button><button wire:click="packRemaining({{ $line->id }})" class="min-h-11 rounded-xl bg-green-600 px-3 text-xs font-bold text-white">Pack {{ $line->remainingToPack() }}</button>@endif</div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">@if($line->remainingToPack()>0)<button wire:click="markNotFulfilled({{ $line->id }})" class="min-h-9 rounded-lg border border-red-200 px-3 text-[11px] font-semibold text-red-700 dark:border-red-900 dark:text-red-300">Flag Issue</button>@endif @if($packed>0 || $status!=='pending')<button wire:click="resetFulfillment({{ $line->id }})" wire:confirm="Reset this packing line? Any optional VortexOps box links for this line will also be removed." class="min-h-9 rounded-lg border border-gray-300 px-3 text-[11px] font-semibold text-gray-600 dark:border-gray-600 dark:text-gray-300">Reset</button>@endif</div>
                </article>
            @empty<div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No items match this filter.</div>@endforelse
        </div>
    </section>

    <section class="vx-ful-card overflow-hidden">
        <details>
            <summary class="cursor-pointer list-none p-4 sm:p-5"><div class="flex items-center justify-between gap-3"><div><h3 class="text-sm font-bold text-gray-950 dark:text-white sm:text-base">Optional Box & Shipment Tools</h3><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Use these only if VortexOps needs to track physical boxes, labels, or an imported Whatnot shipment reference. They do not determine what needs to be packed.</p></div><span class="text-xs font-bold text-primary-600">{{ $packages->count() }} box{{ $packages->count()===1 ? '' : 'es' }}</span></div></summary>
            <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h4 class="text-sm font-bold text-gray-950 dark:text-white">Tracked Boxes</h4><p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">A box is optional. Selecting one only links future scans/taps to that box.</p></div><div class="flex gap-2"><input wire:model.defer="newPackageBuyer" type="text" placeholder="Reference (optional)" class="min-h-11 min-w-0 flex-1 rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800"/><button wire:click="createPackage" class="min-h-11 rounded-xl bg-primary-600 px-4 text-xs font-bold text-white">+ Track Box</button></div></div>

                @if($packages->isNotEmpty())
                    <div class="vx-box-grid mt-3">
                        @foreach($packages as $package)
                            @php $verifyUrl = route('admin.fulfillment-box.verify', ['packageCode' => $package->package_code]); $qrUrl = 'https://quickchart.io/qr?size=180&margin=1&text='.rawurlencode($verifyUrl); @endphp
                            <div class="vx-box {{ $activePackageId === $package->id ? 'ring-2 ring-primary-500' : '' }}">
                                <div class="flex items-start justify-between gap-2"><div><div class="text-sm font-bold text-gray-950 dark:text-white">Box {{ $package->box_number }} of {{ $package->box_total }}</div><div class="mt-0.5 text-[10px] text-gray-500">{{ $package->package_code }}{{ $package->shipment_id ? ' · Whatnot linked' : '' }}</div></div><span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $package->isSealed() ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $package->isSealed() ? 'Sealed' : ($activePackageId === $package->id ? 'Current' : 'Building') }}</span></div>
                                <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">{{ $package->totalItems() }} linked item(s)</div>
                                <div class="mt-3 grid grid-cols-2 gap-2 vx-no-print">@if(!$package->isSealed())<button wire:click="selectPackage({{ $package->id }})" class="min-h-10 rounded-xl border border-gray-300 px-3 text-xs font-bold dark:border-gray-600">{{ $activePackageId === $package->id ? 'Selected' : 'Use Box' }}</button><button wire:click="sealPackage({{ $package->id }})" wire:confirm="Seal this tracked box?" class="min-h-10 rounded-xl bg-green-600 px-3 text-xs font-bold text-white">Verify + Seal</button>@endif @if($package->shipment_id)<button wire:click="addPackageFromShipment({{ $package->shipment_id }})" class="min-h-10 rounded-xl border border-primary-300 px-3 text-xs font-bold text-primary-700 dark:border-primary-700 dark:text-primary-300">+ Another</button>@endif<button type="button" wire:click="printLabel({{ $package->id }})" class="min-h-10 rounded-xl bg-gray-900 px-3 text-xs font-bold text-white dark:bg-white dark:text-gray-900">Print Label</button></div>
                                <details class="mt-3"><summary class="cursor-pointer text-xs font-semibold text-primary-600">Label & verification</summary><div class="vx-label mt-2 {{ $printPackageId === $package->id ? 'vx-label-print-target' : '' }}"><div class="flex items-start justify-between gap-3"><div><h4>VORTEXOPS · BOX {{ $package->box_number }} OF {{ $package->box_total }}</h4><small>{{ $show->title }} · {{ $show->show_date?->format('M j, Y') }}</small></div><div class="text-right"><div class="vx-label-code">{{ $package->package_code }}</div><small>Internal box tracking</small></div></div><div class="vx-label-body mt-2"><div><div class="vx-package-mark">SCAN QR TO VERIFY BOX</div><div class="mt-2 border-t border-gray-300 pt-2 text-[10px] font-bold uppercase">Verified Contents</div><ul>@forelse($package->items as $packageItem)<li>{{ $packageItem->streamerLogItem?->item_name ?: $packageItem->streamerLogItem?->inventoryItem?->name ?: 'Unmapped logged item' }} ×{{ (int)$packageItem->quantity }}</li>@empty<li>No items linked yet.</li>@endforelse</ul></div><div class="text-center"><img class="vx-label-qr" src="{{ $qrUrl }}" alt="QR code for {{ $package->package_code }} verification"><small class="mt-1 block">{{ $package->package_code }}</small></div></div><div class="mt-2 border-t border-gray-300 pt-2 text-[10px]">{{ $package->totalItems() }} total item(s) · Packed by {{ $package->packedBy?->name ?? auth()->user()?->name ?? 'Fulfillment' }}{{ $package->sealed_at ? ' · '.$package->sealed_at->format('M j, g:i A') : '' }}</div>@if($package->tracking_number && !str_starts_with($package->tracking_number,'INTERNAL-'))<div class="mt-1 truncate text-[9px]">Whatnot tracking: {{ $package->tracking_number }}</div>@endif</div><a href="{{ $verifyUrl }}" target="_blank" class="mt-2 inline-flex min-h-9 items-center rounded-lg border border-gray-300 px-3 text-[11px] font-semibold dark:border-gray-600">Open Verification</a></details>
                            </div>
                        @endforeach
                    </div>
                @else<div class="mt-3 rounded-xl border border-dashed border-gray-300 p-4 text-center text-xs text-gray-500 dark:border-gray-700">No VortexOps boxes are being tracked. That is okay — you can complete fulfillment directly from the streamer packing list.</div>@endif

                @if($shipments->isNotEmpty())
                    <details class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700"><summary class="cursor-pointer p-3 text-xs font-semibold text-gray-700 dark:text-gray-200">Imported Whatnot shipment references ({{ $shipments->count() }})</summary><div class="border-t border-gray-100 p-3 dark:border-gray-800"><p class="mb-3 text-[11px] text-gray-500">Reference only. Buyer grouping and Whatnot item counts do not change the streamer packing list.</p><div class="grid gap-2 md:grid-cols-2">@foreach($shipments->take(12) as $shipment)@php $alreadyBuilt=in_array($shipment->id,$packagedShipmentIds,true); $shipmentBoxCount=$packages->where('shipment_id',$shipment->id)->count(); @endphp<div class="vx-box"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="truncate text-xs font-semibold text-gray-700 dark:text-gray-200">Shipment reference {{ $shipment->id }}</div><div class="mt-1 truncate text-[10px] text-gray-500">{{ $shipment->tracking_number ?: 'No tracking yet' }}</div></div><span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $shipmentBoxCount }} box{{ $shipmentBoxCount===1 ? '' : 'es' }}</span></div><div class="mt-3 grid grid-cols-2 gap-2"><button wire:click="createPackageFromShipment({{ $shipment->id }})" class="min-h-10 rounded-xl px-3 text-xs font-bold {{ $alreadyBuilt ? 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-200' : 'bg-primary-600 text-white' }}">{{ $alreadyBuilt ? 'Open Box' : 'Track Box' }}</button><button wire:click="addPackageFromShipment({{ $shipment->id }})" class="min-h-10 rounded-xl border border-primary-300 px-3 text-xs font-bold text-primary-700 dark:border-primary-700 dark:text-primary-300">+ Box</button></div></div>@endforeach</div></div></details>
                @endif
            </div>
        </details>
    </section>
</div>
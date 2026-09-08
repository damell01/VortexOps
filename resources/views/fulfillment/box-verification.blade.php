<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $package->package_code }} · VortexOps Box Verification</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-950 dark:bg-gray-950 dark:text-white">
    @php
        $show = $package->show;
        $items = $package->items;
        $total = (int) $items->sum('quantity');
    @endphp

    <main class="mx-auto max-w-3xl p-4 sm:p-6 lg:p-8">
        <div class="mb-4 flex items-center justify-between gap-3">
            <a href="{{ url('/admin/fulfillment-center/'.$show->id) }}" class="text-sm font-semibold text-primary-600">← Back to show</a>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $package->isSealed() ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $package->isSealed() ? 'Sealed' : 'Building' }}</span>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-[.18em] text-primary-600">VortexOps Box Verification</div>
                    <h1 class="mt-2 text-2xl font-black">{{ $package->package_code }}</h1>
                    <p class="mt-1 text-sm text-gray-500">Box {{ $package->box_number }} of {{ max(1, $package->box_total) }} · {{ $show?->title }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm dark:bg-gray-800">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Buyer</div>
                    <div class="mt-1 font-bold">{{ $package->buyer_username ? '@'.$package->buyer_username : 'Not assigned' }}</div>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[10px] font-semibold uppercase text-gray-500">Items</div><div class="mt-1 text-xl font-black">{{ $total }}</div></div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[10px] font-semibold uppercase text-gray-500">Packed By</div><div class="mt-1 truncate text-sm font-bold">{{ $package->packedBy?->name ?: '—' }}</div></div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[10px] font-semibold uppercase text-gray-500">Sealed</div><div class="mt-1 text-sm font-bold">{{ $package->sealed_at?->format('M j, g:i A') ?: 'Not yet' }}</div></div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800"><div class="text-[10px] font-semibold uppercase text-gray-500">Label</div><div class="mt-1 text-sm font-bold">{{ $package->label_printed_at ? 'Printed' : 'Not printed' }}</div></div>
            </div>
        </section>

        <section class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <h2 class="text-lg font-black">Verified Contents</h2>
            <p class="mt-1 text-sm text-gray-500">Use this list to confirm what should physically be inside this box.</p>

            <div class="mt-4 space-y-2">
                @forelse($items as $item)
                    @php $line = $item->streamerLogItem; @endphp
                    <div class="flex items-start justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="min-w-0">
                            <div class="font-bold">{{ $line?->item_name ?: $line?->inventoryItem?->name ?: 'Unmapped logged item' }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $line?->inventoryItem?->sku ? 'SKU '.$line->inventoryItem->sku : 'Streamer logged line #'.$item->streamer_log_item_id }}</div>
                        </div>
                        <div class="shrink-0 rounded-lg bg-gray-100 px-3 py-2 text-lg font-black dark:bg-gray-800">×{{ (int) $item->quantity }}</div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">No packed items are recorded for this box.</div>
                @endforelse
            </div>
        </section>

        @if($package->shipment)
            <section class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                <h2 class="text-lg font-black">Whatnot Shipment</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div><div class="text-xs font-semibold uppercase text-gray-500">Tracking</div><div class="mt-1 break-all font-mono text-sm">{{ $package->tracking_number ?: '—' }}</div></div>
                    <div><div class="text-xs font-semibold uppercase text-gray-500">Carrier</div><div class="mt-1 text-sm font-bold">{{ $package->carrier ?: '—' }}</div></div>
                </div>
            </section>
        @endif
    </main>
</body>
</html>
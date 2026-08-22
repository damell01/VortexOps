@php
    $show = $this->record;
    $log = $show->streamerLogEntry()->with('items.inventoryItem')->first();
    $streamers = $show->streamers()->get();
    $fulfillmentUsers = $show->fulfillmentUsers()->get();
    $shipmentCount = $show->shipments()->count();
    $deliveredCount = $show->shipments()->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'")->count();
    $openShipmentCount = max(0, $shipmentCount - $deliveredCount);
    $reportUnits = $log ? (int) $log->items->sum('quantity') : 0;
    $reportedSold = $log ? (int) $log->items->where('disposition','sold')->sum('quantity') : 0;
    $reportedGiveaways = $log ? (int) $log->items->where('disposition','giveaway')->sum('quantity') : 0;
    $unmatched = $log ? $log->items->whereNull('inventory_item_id')->count() : 0;
    $postingProblems = $log ? $log->inventoryPostingProblems() : [];
    $inventoryIssues = max($unmatched, count($postingProblems));
    $isUpcoming = $show->show_date?->isFuture() ?? false;
    $hasWhatnotData = $show->gross_revenue !== null || $show->units_sold !== null || $show->buyers_count !== null;
    $reportSubmitted = $log?->isSubmitted() ?? false;

    // Reference differences are intentionally informational. A Whatnot order or
    // giveaway transaction is not guaranteed to equal one inventory unit.
    $reconciliationWarnings = [];
    if ($log && $show->giveaways_count !== null && (int)$show->giveaways_count !== $reportedGiveaways) {
        $reconciliationWarnings[] = 'Whatnot shows ' . number_format((int)$show->giveaways_count) . ' giveaways while the streamer report contains ' . number_format($reportedGiveaways) . ' giveaway inventory units.';
    }
    if ($log && $show->units_sold !== null && (int)$show->units_sold !== $reportedSold) {
        $reconciliationWarnings[] = 'Whatnot shows ' . number_format((int)$show->units_sold) . ' orders/items sold while the streamer report contains ' . number_format($reportedSold) . ' sold inventory units.';
    }

    if ($show->status === 'closed') {
        $nextTone = 'green';
        $nextTitle = 'Show closed';
        $nextBody = 'This show is fully closed. Whatnot sync can still record later analytics or shipment changes in the activity history.';
        $nextUrl = null;
        $nextLabel = null;
    } elseif ($isUpcoming) {
        $missing = [];
        if ($streamers->isEmpty()) $missing[] = 'streamer';
        if ($fulfillmentUsers->isEmpty()) $missing[] = 'fulfillment';
        $nextTone = $missing ? 'amber' : 'blue';
        $nextTitle = $missing ? 'Upcoming show needs assignment' : 'Upcoming show is ready';
        $nextBody = $missing
            ? 'Missing: ' . implode(' and ', $missing) . '. Inventory is not assigned to shows; streamers manage their own inventory separately.'
            : 'Streamer and fulfillment ownership are assigned. Streamers can move inventory into their own inventory whenever they take possession of it.';
        $nextUrl = \App\Filament\Resources\ShowResource::getUrl('edit', ['record' => $show]);
        $nextLabel = 'Edit Assignments';
    } elseif (! $hasWhatnotData) {
        $nextTone = 'blue';
        $nextTitle = 'Waiting for Whatnot data';
        $nextBody = 'The show has ended. VortexOps is waiting for the scraper to populate show metrics and shipment information.';
        $nextUrl = null;
        $nextLabel = null;
    } elseif (! $reportSubmitted) {
        $nextTone = 'amber';
        $nextTitle = 'Waiting for streamer report';
        $nextBody = 'Whatnot data is available. The streamer still needs to report the inventory actually sold, given away, used as promo, or otherwise consumed during this show.';
        $nextUrl = \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]);
        $nextLabel = 'Open Show Report';
    } elseif ($inventoryIssues > 0) {
        $nextTone = 'red';
        $nextTitle = 'Inventory reconciliation needs attention';
        $nextBody = $inventoryIssues . ' inventory ' . \Illuminate\Support\Str::plural('issue', $inventoryIssues) . ' need review before the report is clean.';
        $nextUrl = \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]);
        $nextLabel = 'Review Report';
    } elseif ($log && in_array($log->status, ['streamer_reviewed', 'changes_requested'], true)) {
        if ($log->status === 'changes_requested') {
            $nextTone = 'amber';
            $nextTitle = 'Waiting for streamer corrections';
            $nextBody = 'Admin requested changes. The streamer can reopen the report, make the requested corrections, and submit it again.';
            $nextUrl = \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]);
            $nextLabel = 'Open Report';
        } else {
            $nextTone = 'blue';
            $nextTitle = 'Ready for admin approval';
            $nextBody = 'The streamer report is submitted and inventory has no blocking reconciliation issues. Admin can approve it or request changes.';
            $nextUrl = \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]);
            $nextLabel = 'Review Report';
        }
    } elseif ($fulfillmentUsers->isEmpty() && $shipmentCount > 0) {
        $nextTone = 'amber';
        $nextTitle = 'Assign fulfillment ownership';
        $nextBody = $shipmentCount . ' shipments are tied to this show, but no fulfillment user is assigned.';
        $nextUrl = \App\Filament\Resources\ShowResource::getUrl('edit', ['record' => $show]);
        $nextLabel = 'Assign Fulfillment';
    } elseif ($openShipmentCount > 0) {
        $nextTone = 'blue';
        $nextTitle = $openShipmentCount . ' shipment' . ($openShipmentCount === 1 ? '' : 's') . ' still open';
        $nextBody = $deliveredCount . ' of ' . $shipmentCount . ' shipments are delivered. Fulfillment should continue working the remaining open shipments.';
        $nextUrl = \App\Filament\Resources\ShipmentResource::getUrl('index', ['tableFilters[show_id][value]' => $show->id]);
        $nextLabel = 'View Shipments';
    } else {
        $nextTone = 'green';
        $nextTitle = 'Ready to close';
        $nextBody = 'The streamer report is settled, inventory is reconciled, and the current shipment data has no open deliveries.';
        $nextUrl = null;
        $nextLabel = null;
    }

    $toneClasses = match ($nextTone) {
        'red' => 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30',
        'amber' => 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30',
        'green' => 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30',
        default => 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30',
    };
@endphp

<x-filament-widgets::widget>
    <div class="space-y-3 sm:space-y-4">
        <section class="rounded-xl border p-4 sm:rounded-2xl sm:p-5 {{ $toneClasses }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Next Action</div>
                    <h3 class="mt-1 text-base font-semibold text-gray-950 dark:text-white sm:text-lg">{{ $nextTitle }}</h3>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-600 dark:text-gray-300 sm:text-sm">{{ $nextBody }}</p>
                </div>
                @if($nextUrl && $nextLabel)
                    <a href="{{ $nextUrl }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-primary-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-primary-500">{{ $nextLabel }}</a>
                @endif
            </div>
        </section>

        @if($reconciliationWarnings)
            <section class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-900 dark:bg-amber-950/20 sm:p-4">
                <div class="flex items-start gap-2.5">
                    <x-heroicon-m-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                    <div>
                        <div class="text-xs font-semibold text-amber-900 dark:text-amber-100">Reference differences</div>
                        <div class="mt-1 space-y-1 text-[11px] leading-5 text-amber-700 dark:text-amber-300 sm:text-xs">
                            @foreach($reconciliationWarnings as $warning)<p>{{ $warning }}</p>@endforeach
                        </div>
                        <p class="mt-1.5 text-[10px] leading-4 text-amber-600 dark:text-amber-400">These are warnings only. Whatnot transactions and physical inventory units are not required to match one-for-one.</p>
                    </div>
                </div>
            </section>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
            <div class="mb-3 sm:mb-4">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Show Operations</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">Streamer report, inventory, fulfillment, and Whatnot status in one view.</p>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-2 sm:gap-3 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Report</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium sm:text-xs {{ $reportSubmitted ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200' }}">{{ $reportSubmitted ? 'Submitted' : 'Pending' }}</span>
                    </div>
                    <div class="mt-2 text-xl font-semibold text-gray-950 dark:text-white sm:mt-3 sm:text-2xl">{{ number_format($reportUnits) }}</div>
                    <div class="text-[10px] text-gray-500 sm:text-xs">inventory units</div>
                </div>

                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Inventory</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium sm:text-xs {{ $inventoryIssues === 0 ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200' }}">{{ $inventoryIssues === 0 ? 'Clean' : $inventoryIssues.' issues' }}</span>
                    </div>
                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-300 sm:mt-3 sm:text-sm"><strong>{{ $reportedSold }}</strong> sold · <strong>{{ $reportedGiveaways }}</strong> giveaway</div>
                    <div class="mt-1 text-[10px] text-gray-500 sm:text-xs">{{ $unmatched }} unmatched</div>
                </div>

                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Fulfillment</span>
                    <div class="mt-2 truncate text-xs font-medium text-gray-950 dark:text-white sm:mt-3 sm:text-sm">{{ $fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</div>
                    <div class="mt-1 text-[10px] text-gray-500 sm:text-xs">{{ $fulfillmentUsers->count() }} assigned</div>
                </div>

                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs">Shipments</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium sm:text-xs {{ $openShipmentCount === 0 && $shipmentCount > 0 ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200' }}">{{ $openShipmentCount }} open</span>
                    </div>
                    <div class="mt-2 text-xl font-semibold text-gray-950 dark:text-white sm:mt-3 sm:text-2xl">{{ number_format($shipmentCount) }}</div>
                    <div class="text-[10px] text-gray-500 sm:text-xs">{{ number_format($deliveredCount) }} delivered</div>
                </div>
            </div>
        </section>
    </div>
</x-filament-widgets::widget>

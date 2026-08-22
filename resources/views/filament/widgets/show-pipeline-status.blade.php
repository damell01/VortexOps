@php
    $show = $this->record;
    $log = $show->streamerLogEntry()->with('items.inventoryItem')->first();
    $streamers = $show->streamers()->get();
    $fulfillmentUsers = $show->fulfillmentUsers()->get();
    $shipmentCount = $show->shipments()->count();
    $deliveredCount = $show->shipments()->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'")->count();
    $openShipmentCount = max(0, $shipmentCount - $deliveredCount);
    $reportUnits = $log ? (int) $log->items->sum('quantity') : 0;
    $unmatched = $log ? $log->items->whereNull('inventory_item_id')->count() : 0;
    $postingProblems = $log ? $log->inventoryPostingProblems() : [];
    $inventoryIssues = max($unmatched, count($postingProblems));
    $isUpcoming = $show->show_date?->isFuture() ?? false;
    $hasWhatnotData = $show->gross_revenue !== null || $show->units_sold !== null || $show->buyers_count !== null;
    $reportSubmitted = $log?->isSubmitted() ?? false;

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
            ? 'Missing: ' . implode(' and ', $missing) . '. No pre-show inventory allocation is required.'
            : 'Streamer and fulfillment ownership are assigned. Streamers can continue transferring inventory normally before the show.';
        $nextUrl = \App\Filament\Resources\ShowResource::getUrl('edit', ['record' => $show]);
        $nextLabel = 'Edit Assignments';
    } elseif (! $hasWhatnotData) {
        $nextTone = 'blue';
        $nextTitle = 'Waiting for Whatnot data';
        $nextBody = 'The show has ended. VortexOps is waiting for the scraper to populate the show metrics and shipment information.';
        $nextUrl = null;
        $nextLabel = null;
    } elseif (! $reportSubmitted) {
        $nextTone = 'amber';
        $nextTitle = 'Waiting for streamer show report';
        $nextBody = 'Whatnot data is available. The streamer still needs to report sold inventory, giveaways, promos, and any unlisted items.';
        $nextUrl = \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]);
        $nextLabel = 'Open Show Report';
    } elseif ($inventoryIssues > 0) {
        $nextTone = 'red';
        $nextTitle = 'Inventory reconciliation needs attention';
        $nextBody = $inventoryIssues . ' inventory ' . \Illuminate\Support\Str::plural('issue', $inventoryIssues) . ' need review before the show is considered clean.';
        $nextUrl = \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]);
        $nextLabel = 'Review Report';
    } elseif ($fulfillmentUsers->isEmpty() && $shipmentCount > 0) {
        $nextTone = 'amber';
        $nextTitle = 'Assign fulfillment ownership';
        $nextBody = $shipmentCount . ' shipments are tied to this show, but no fulfillment user is assigned.';
        $nextUrl = \App\Filament\Resources\ShowResource::getUrl('edit', ['record' => $show]);
        $nextLabel = 'Assign Fulfillment';
    } elseif ($openShipmentCount > 0) {
        $nextTone = 'blue';
        $nextTitle = 'Fulfillment in progress';
        $nextBody = $openShipmentCount . ' of ' . $shipmentCount . ' shipments are not yet marked delivered.';
        $nextUrl = \App\Filament\Resources\ShipmentResource::getUrl('index', ['tableFilters[show_id][value]' => $show->id]);
        $nextLabel = 'View Shipments';
    } else {
        $nextTone = 'green';
        $nextTitle = 'Operationally clean';
        $nextBody = 'Streamer report is submitted, inventory is reconciled, and the current shipment data has no open deliveries.';
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
    <div class="space-y-4">
        <section class="rounded-2xl border p-5 {{ $toneClasses }}">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Next Action</div>
                    <h3 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $nextTitle }}</h3>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600 dark:text-gray-300">{{ $nextBody }}</p>
                </div>
                @if($nextUrl && $nextLabel)
                    <a href="{{ $nextUrl }}" class="shrink-0 rounded-lg bg-primary-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-primary-500">{{ $nextLabel }}</a>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-950 dark:text-white">Show Operations</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">One view of the streamer, inventory, fulfillment, and Whatnot workflow.</p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Streamer Report</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $reportSubmitted ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200' }}">{{ $reportSubmitted ? 'Submitted' : 'Pending' }}</span>
                    </div>
                    <div class="mt-3 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($reportUnits) }}</div>
                    <div class="text-xs text-gray-500">reported inventory units</div>
                    @if($log)
                        <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]) }}" class="mt-3 inline-block text-sm font-medium text-primary-600">Open report →</a>
                    @endif
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Inventory</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $inventoryIssues === 0 ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200' }}">{{ $inventoryIssues === 0 ? 'Clean' : $inventoryIssues.' issues' }}</span>
                    </div>
                    @php
                        $sold = $log ? (int)$log->items->where('disposition','sold')->sum('quantity') : 0;
                        $giveaways = $log ? (int)$log->items->where('disposition','giveaway')->sum('quantity') : 0;
                    @endphp
                    <div class="mt-3 text-sm text-gray-600 dark:text-gray-300"><strong>{{ $sold }}</strong> sold · <strong>{{ $giveaways }}</strong> giveaways</div>
                    <div class="mt-1 text-xs text-gray-500">{{ $unmatched }} unmatched catalog {{ \Illuminate\Support\Str::plural('line', $unmatched) }}</div>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fulfillment</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $fulfillmentUsers->count() }} assigned</span>
                    </div>
                    <div class="mt-3 text-sm font-medium text-gray-950 dark:text-white">{{ $fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</div>
                    <div class="mt-1 text-xs text-gray-500">Operational ownership only; time tracking remains separate.</div>
                </div>

                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Shipments</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $openShipmentCount === 0 && $shipmentCount > 0 ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200' }}">{{ $openShipmentCount }} open</span>
                    </div>
                    <div class="mt-3 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($shipmentCount) }}</div>
                    <div class="text-xs text-gray-500">{{ number_format($deliveredCount) }} delivered</div>
                    @if($shipmentCount > 0)
                        <a href="{{ \App\Filament\Resources\ShipmentResource::getUrl('index', ['tableFilters[show_id][value]' => $show->id]) }}" class="mt-3 inline-block text-sm font-medium text-primary-600">View shipments →</a>
                    @endif
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800/60">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Streamer</div>
                    <div class="mt-2 font-medium text-gray-950 dark:text-white">{{ $streamers->pluck('name')->join(', ') ?: 'Not assigned' }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800/60">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Whatnot Sync</div>
                    <div class="mt-2 font-medium text-gray-950 dark:text-white">{{ $hasWhatnotData ? 'Data available' : 'Waiting for data' }}</div>
                    <div class="mt-1 text-xs text-gray-500">Last show sync: {{ $show->last_synced_at?->diffForHumans() ?? 'Never' }}</div>
                </div>
            </div>
        </section>
    </div>
</x-filament-widgets::widget>

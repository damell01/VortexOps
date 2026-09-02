@php
    use App\Models\Show;
    use App\Models\StreamerLogItem;
    /** @var Show $record */
    $record = $this->record;
    $shipmentTotal = $record->shipments()->count();
    $delivered = $record->shipments()->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'")->count();
    $open = max(0, $shipmentTotal - $delivered);
    $loggedItems = $record->streamerLogEntry?->items ?? collect();
    $pendingReview = $loggedItems->filter(fn (StreamerLogItem $item) => ! $item->isFulfillmentReviewed())->count();
    $issues = $loggedItems->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-600">Fulfillment Show</div>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $record->title ?? 'Show' }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $record->primaryStreamer()?->name ?? 'Unknown Streamer' }} · {{ $record->show_date?->format('M j, Y') }}
                    </p>
                </div>
                <span class="self-start rounded-full bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    {{ Show::statusLabels()[$record->status] ?? ucfirst(str_replace('_', ' ', $record->status)) }}
                </span>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="text-xs text-gray-500">Logged Items</div>
                    <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($loggedItems->count()) }}</div>
                </div>
                <div class="rounded-xl bg-amber-50 p-4 dark:bg-amber-950/30">
                    <div class="text-xs text-amber-700 dark:text-amber-300">To Review</div>
                    <div class="mt-1 text-xl font-semibold text-amber-700 dark:text-amber-200">{{ number_format($pendingReview) }}</div>
                </div>
                <div class="rounded-xl bg-red-50 p-4 dark:bg-red-950/30">
                    <div class="text-xs text-red-700 dark:text-red-300">Not Fulfilled</div>
                    <div class="mt-1 text-xl font-semibold text-red-700 dark:text-red-200">{{ number_format($issues) }}</div>
                </div>
                <div class="rounded-xl bg-blue-50 p-4 dark:bg-blue-950/30">
                    <div class="text-xs text-blue-700 dark:text-blue-300">Whatnot Shipments</div>
                    <div class="mt-1 text-xl font-semibold text-blue-700 dark:text-blue-200">{{ number_format($shipmentTotal) }}</div>
                    <div class="mt-0.5 text-[10px] text-blue-600/80 dark:text-blue-300/80">{{ $open }} open · reference only</div>
                </div>
            </div>

            <div class="mt-4 rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assigned Fulfillment</div>
                <div class="mt-2 text-sm font-medium text-gray-950 dark:text-white">{{ $record->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</div>
            </div>
        </section>

        @livewire('fulfillment-dashboard', ['show' => $record], key('fulfillment-' . $record->id))

        <div class="flex flex-wrap gap-2">
            @foreach($this->getHeaderActions() as $action)
                {{ $action }}
            @endforeach
            <a href="{{ route('filament.admin.resources.fulfillment-center.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">← Back to Fulfillment Center</a>
        </div>
    </div>
</x-filament-panels::page>

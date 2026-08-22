@php
    use App\Models\Show;
    /** @var Show $record */
    $record = $this->record;
    $shipmentTotal = $record->shipments()->count();
    $delivered = $record->shipments()->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'")->count();
    $open = max(0, $shipmentTotal - $delivered);
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
                    <div class="text-xs text-gray-500">Shipments</div>
                    <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($shipmentTotal) }}</div>
                </div>
                <div class="rounded-xl bg-blue-50 p-4 dark:bg-blue-950/30">
                    <div class="text-xs text-blue-700 dark:text-blue-300">Open</div>
                    <div class="mt-1 text-xl font-semibold text-blue-700 dark:text-blue-200">{{ number_format($open) }}</div>
                </div>
                <div class="rounded-xl bg-green-50 p-4 dark:bg-green-950/30">
                    <div class="text-xs text-green-700 dark:text-green-300">Delivered</div>
                    <div class="mt-1 text-xl font-semibold text-green-700 dark:text-green-200">{{ number_format($delivered) }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                    <div class="text-xs text-gray-500">Packing Lines</div>
                    <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($record->orders()->count()) }}</div>
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

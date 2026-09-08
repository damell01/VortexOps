@php
    use App\Models\Show;
    use App\Models\StreamerLogItem;
    /** @var Show $record */
    $record = $this->record;
    $loggedItems = $record->streamerLogEntry?->items ?? collect();
    $totalUnits = (int) $loggedItems->sum('quantity');
    $packedUnits = min($totalUnits, (int) $loggedItems->sum('packed_quantity'));
    $unitsLeft = max(0, $totalUnits - $packedUnits);
    $issues = $loggedItems->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();
    $progress = $totalUnits > 0 ? min(100, (int) round(($packedUnits / $totalUnits) * 100)) : 0;
@endphp

<x-filament-panels::page>
    <div class="mx-auto max-w-[1500px] space-y-4">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="p-4 sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600">Fulfillment Workspace</div>
                        <h1 class="mt-1 truncate text-xl font-bold text-gray-950 dark:text-white sm:text-2xl">{{ $record->title ?? 'Show' }}</h1>
                        <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $record->primaryStreamer()?->name ?? 'Unknown Streamer' }}</span>
                            <span>· {{ $record->show_date?->format('M j, Y') }}</span>
                            @if($record->channel)<span>· {{ $record->channel->name }}</span>@endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-gray-100 px-3 py-1.5 text-[10px] font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            {{ Show::statusLabels()[$record->status] ?? ucfirst(str_replace('_', ' ', $record->status)) }}
                        </span>
                        <span class="rounded-full {{ $unitsLeft > 0 ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300' : 'bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-300' }} px-3 py-1.5 text-[10px] font-bold">
                            {{ $unitsLeft > 0 ? $unitsLeft.' units left' : 'Items accounted for' }}
                        </span>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="text-[9px] font-bold uppercase tracking-wide text-gray-500">Item Lines</div>
                        <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ number_format($loggedItems->count()) }}</div>
                    </div>
                    <div class="rounded-xl bg-blue-50 p-3 dark:bg-blue-950/30">
                        <div class="text-[9px] font-bold uppercase tracking-wide text-blue-600 dark:text-blue-300">Total Units</div>
                        <div class="mt-1 text-xl font-bold text-blue-700 dark:text-blue-200">{{ number_format($totalUnits) }}</div>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30">
                        <div class="text-[9px] font-bold uppercase tracking-wide text-amber-600 dark:text-amber-300">Units Left</div>
                        <div class="mt-1 text-xl font-bold text-amber-700 dark:text-amber-200">{{ number_format($unitsLeft) }}</div>
                    </div>
                    <div class="rounded-xl {{ $issues ? 'bg-red-50 dark:bg-red-950/30' : 'bg-green-50 dark:bg-green-950/30' }} p-3">
                        <div class="text-[9px] font-bold uppercase tracking-wide {{ $issues ? 'text-red-600 dark:text-red-300' : 'text-green-600 dark:text-green-300' }}">Issues</div>
                        <div class="mt-1 text-xl font-bold {{ $issues ? 'text-red-700 dark:text-red-200' : 'text-green-700 dark:text-green-200' }}">{{ number_format($issues) }}</div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="mb-1.5 flex items-center justify-between text-[10px] font-semibold text-gray-500"><span>Streamer-log packing progress</span><span>{{ $packedUnits }}/{{ $totalUnits }} · {{ $progress }}%</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-primary-600" style="width:{{ $progress }}%"></div></div>
                </div>

                <div class="mt-4 flex flex-col gap-2 rounded-xl border border-gray-100 bg-gray-50 px-3 py-3 dark:border-gray-800 dark:bg-gray-800/60 sm:flex-row sm:items-center sm:justify-between">
                    <div><div class="text-[9px] font-bold uppercase tracking-wide text-gray-500">Assigned Fulfillment</div><div class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">{{ $record->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</div></div>
                    <div class="text-[10px] text-gray-500">Fulfillment works from the streamer item list below. Buyer and Whatnot shipment grouping are intentionally not part of this workspace.</div>
                </div>
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

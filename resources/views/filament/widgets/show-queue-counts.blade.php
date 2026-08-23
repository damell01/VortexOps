@php($counts = $this->counts)

<x-filament-widgets::widget>
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Waiting on someone</h2>
            <a href="{{ \App\Filament\Pages\AppSettings::getUrl() }}"
               class="shrink-0 text-[11px] font-medium text-gray-400 hover:text-primary-600 dark:hover:text-primary-400">
                Workflow settings
            </a>
        </div>

        {{-- Four numbers, each a link to the thing it counts. A dashboard
             number nobody can act on from where they are reading it is a
             number they have to go and look for. --}}
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
            @foreach ([
                ['Reports to review',  $counts['pending_reports'],        \App\Filament\Resources\StreamerLogResource::getUrl(), 'text-amber-600 dark:text-amber-400'],
                ['Unmatched lines',    $counts['unmatched_lines'],        \App\Filament\Resources\StreamerLogResource::getUrl(), 'text-rose-600 dark:text-rose-400'],
                ['Open shipments',     $counts['open_shipments'],         \App\Filament\Resources\ShipmentResource::getUrl(),    'text-sky-600 dark:text-sky-400'],
                ['Unassigned packing', $counts['unassigned_fulfillment'], \App\Filament\Pages\ShowShipments::getUrl(),           'text-violet-600 dark:text-violet-400'],
            ] as [$label, $value, $href, $tone])
                <a href="{{ $href }}" wire:navigate
                   class="group min-w-0 rounded-lg border border-gray-200 px-3 py-3 transition-colors hover:border-primary-300 dark:border-gray-700 dark:hover:border-primary-700">
                    <div class="text-xl font-semibold leading-none {{ $value > 0 ? $tone : 'text-gray-300 dark:text-gray-600' }} sm:text-2xl">
                        {{ number_format($value) }}
                    </div>
                    <div class="mt-1.5 truncate text-[11px] font-medium text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200">
                        {{ $label }}
                    </div>
                </a>
            @endforeach
        </div>

        @if (collect($counts)->every(fn ($n) => $n === 0))
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                Nothing queued — every report is reviewed and every shipment is assigned.
            </p>
        @endif
    </section>
</x-filament-widgets::widget>

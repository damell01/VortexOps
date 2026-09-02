<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Show flow</div>
                <h2 class="mt-1 text-base font-semibold text-gray-950 dark:text-white sm:text-lg">Where every show is right now</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Streamer report → admin review → inventory mapping → fulfillment → payroll.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ \App\Filament\Pages\Shows::getUrl() }}" class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Open Shows</a>
                <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="inline-flex min-h-10 items-center rounded-lg bg-primary-600 px-3 text-xs font-semibold text-white hover:bg-primary-500">Payroll Dashboard</a>
            </div>
        </div>

        <div class="grid gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-2 xl:grid-cols-6">
            @foreach($this->flow as $index => $step)
                <a href="{{ $step['url'] }}" class="group relative bg-white p-4 transition hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800/70">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-500 group-hover:bg-primary-50 group-hover:text-primary-600 dark:bg-gray-800 dark:text-gray-300 dark:group-hover:bg-primary-950/40">
                            <x-dynamic-component :component="$step['icon']" class="h-5 w-5" />
                        </div>
                        @if($index < count($this->flow) - 1)
                            <x-heroicon-m-chevron-right class="hidden h-4 w-4 text-gray-300 xl:block" />
                        @endif
                    </div>
                    <div class="mt-3 text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $step['label'] }}</div>
                    <div class="mt-0.5 text-2xl font-semibold leading-none text-gray-950 dark:text-white">{{ number_format($step['count']) }}</div>
                    <div class="mt-1 min-h-8 text-[11px] leading-4 text-gray-500 dark:text-gray-400">{{ $step['detail'] }}</div>
                </a>
            @endforeach
        </div>

        <div class="grid gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">
            <a href="{{ \App\Filament\Pages\InventoryCatalog::getUrl() }}" class="bg-white px-4 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Browse Inventory Catalog →</a>
            <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('index') }}" class="bg-white px-4 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Fulfillment Center · {{ number_format($this->openShipments) }} open →</a>
            <a href="{{ \App\Filament\Pages\PayrollOverview::getUrl() }}" class="bg-white px-4 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Review Weekly Payroll →</a>
            <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}" class="bg-white px-4 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Advanced Inventory Table →</a>
        </div>
    </section>
</x-filament-widgets::widget>

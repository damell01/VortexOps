<x-filament-panels::page>
    <div class="space-y-5">
        @if(($roleMode ?? 'user') === 'streamer')
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="p-5 sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-600">Streamer Center</div>
                    <div class="mt-1 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-950 dark:text-white">Your shows and inventory</h1>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Transfer inventory normally before a show. After it ends, report what was sold, given away, or used as promo inventory.</p>
                        </div>
                        @if($nextShow)
                            <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $nextShow]) }}" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm dark:border-primary-900 dark:bg-primary-950/30">
                                <div class="text-xs font-medium text-primary-600">Next Show</div>
                                <div class="mt-1 max-w-64 font-semibold text-gray-950 dark:text-white">{{ $nextShow->title }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $nextShow->show_date?->format('M j, Y') }} @if($nextShow->start_time) · {{ $nextShow->start_time->format('g:i A') }} @endif</div>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-4 dark:bg-gray-800">
                    @foreach ([
                        ['Reports Due', $reportsDue ?? 0, 'Open ended shows and finish your report'],
                        ['Inventory Products', $inventoryCount ?? 0, 'Products currently in your inventory'],
                        ['Inventory Units', $inventoryUnits ?? 0, 'Units available across your locations'],
                        ['Giveaways · 30d', $giveawayUnits30 ?? 0, 'Units recorded as giveaways'],
                    ] as [$label, $value, $caption])
                        <div class="bg-white p-4 dark:bg-gray-900">
                            <div class="text-xs text-gray-500">{{ $label }}</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float)$value) }}</div>
                            <div class="mt-1 text-[11px] leading-4 text-gray-400">{{ $caption }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            @if(($reportsDue ?? 0) > 0)
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-semibold text-amber-900 dark:text-amber-100">You have {{ $reportsDue }} show {{ \Illuminate\Support\Str::plural('report', $reportsDue) }} to finish</div>
                            <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Use End of Stream to add sold inventory, giveaways, promo items, and anything not in the catalog.</p>
                        </div>
                        <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl() }}" class="rounded-lg bg-amber-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-amber-500">Open End of Stream</a>
                    </div>
                </section>
            @endif
        @elseif(($roleMode ?? 'user') === 'fulfillment')
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="p-5 sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-600">Fulfillment Operations</div>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">Shows that need shipping attention</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Work by show first, then drill into Whatnot shipments and packing lines. Your time tracking remains separate.</p>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-4 dark:bg-gray-800">
                    @foreach ([
                        ['Shows to Work', $showsToFulfill ?? 0],
                        ['Open Shipments', $openShipments ?? 0],
                        ['Delivered Today', $deliveredToday ?? 0],
                        ['Unassigned Shows', $unassignedShows ?? 0],
                    ] as [$label, $value])
                        <div class="bg-white p-4 dark:bg-gray-900">
                            <div class="text-xs text-gray-500">{{ $label }}</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float)$value) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-semibold text-blue-900 dark:text-blue-100">Start from the Fulfillment Center</div>
                        <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">Shows are sorted by date and expose shipment progress, assigned fulfillment users, and next action.</p>
                    </div>
                    <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('index') }}" class="rounded-lg bg-blue-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-blue-500">Open Fulfillment Center</a>
                </div>
            </section>
        @elseif(($roleMode ?? 'user') === 'admin')
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="p-5 sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-600">Admin Operations Center</div>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">What needs attention right now</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Post-show reporting, inventory reconciliation, fulfillment ownership, and Whatnot shipment state in one place.</p>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-5 dark:bg-gray-800">
                    @foreach ([
                        ['Reports to Review', $reportsToReview ?? 0],
                        ['Unmatched Items', $unmatchedItems ?? 0],
                        ['Open Shipments', $openShipments ?? 0],
                        ['Unassigned Fulfillment', $unassignedFulfillment ?? 0],
                        ['Draft Payouts', $draftPayouts ?? 0],
                    ] as [$label, $value])
                        <div class="bg-white p-4 dark:bg-gray-900">
                            <div class="text-xs text-gray-500">{{ $label }}</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float)$value) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <x-filament-widgets::widgets :widgets="$this->getWidgets()" :columns="$this->getColumns()" />
    </div>
</x-filament-panels::page>

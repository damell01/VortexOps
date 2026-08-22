<x-filament-panels::page>
    @php($pageMode = $roleMode ?? 'user')
    <div
        class="space-y-3 sm:space-y-5"
        @if($pageMode === 'streamer') data-vx-page="streamer-dashboard"
        @elseif($pageMode === 'fulfillment') data-vx-page="fulfillment-dashboard"
        @elseif($pageMode === 'admin') data-vx-page="admin-dashboard"
        @endif
    >
        @if($pageMode === 'streamer')
            <section data-vx-tour="role-overview" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Streamer Center</div>
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">Your workspace</span>
                    </div>

                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-5">
                        <div class="min-w-0">
                            <h1 class="text-xl font-semibold leading-tight text-gray-950 dark:text-white sm:text-2xl">Your shows and inventory</h1>
                            <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Move inventory into your inventory whenever you take possession of it. It is not tied to a show ahead of time. After a show ends, report which items were sold, given away, or used as promo inventory.</p>
                        </div>

                        @if($nextShow)
                            <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $nextShow]) }}" class="group flex items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-3 py-2.5 text-sm dark:border-primary-900 dark:bg-primary-950/30 sm:min-w-64 sm:max-w-72 sm:block sm:px-4 sm:py-3">
                                <div class="min-w-0">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-primary-600">Next Show</div>
                                    <div class="mt-0.5 truncate font-semibold text-gray-950 dark:text-white sm:mt-1">{{ $nextShow->title }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-500">{{ $nextShow->show_date?->format('M j') }} @if($nextShow->start_time) · {{ $nextShow->start_time->format('g:i A') }} @endif</div>
                                </div>
                                <x-heroicon-m-chevron-right class="h-5 w-5 shrink-0 text-primary-500 sm:hidden" />
                            </a>
                        @endif
                    </div>
                </div>

                <div data-vx-tour="role-metrics" class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">
                    @foreach ([
                        ['Reports Due', $reportsDue ?? 0, 'Need action'],
                        ['Products', $inventoryCount ?? 0, 'In your inventory'],
                        ['Units', $inventoryUnits ?? 0, 'Available now'],
                        ['Giveaways · 30d', $giveawayUnits30 ?? 0, 'Reported units'],
                    ] as [$label, $value, $caption])
                        <div class="min-w-0 bg-white px-3 py-3 dark:bg-gray-900 sm:p-4">
                            <div class="truncate text-[10px] font-medium uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">{{ $label }}</div>
                            <div class="mt-0.5 text-xl font-semibold leading-none text-gray-950 dark:text-white sm:mt-1 sm:text-2xl">{{ number_format((float)$value) }}</div>
                            <div class="mt-1 truncate text-[10px] text-gray-400 sm:text-[11px]">{{ $caption }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900 sm:p-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/40">
                        <x-heroicon-m-arrow-path-rounded-square class="h-4 w-4" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-semibold text-gray-900 dark:text-white">How your show flow works</div>
                        <div class="mt-1 grid gap-1 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:grid-cols-3 sm:gap-3">
                            <span><strong class="text-gray-700 dark:text-gray-200">1.</strong> Move inventory into your inventory whenever you take it.</span>
                            <span><strong class="text-gray-700 dark:text-gray-200">2.</strong> Run shows normally while Whatnot data syncs automatically.</span>
                            <span><strong class="text-gray-700 dark:text-gray-200">3.</strong> After each show, report the inventory actually used.</span>
                        </div>
                    </div>
                </div>
            </section>

            @if(($reportsDue ?? 0) > 0)
                <section data-vx-tour="primary-action" class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30 sm:p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-amber-900 dark:text-amber-100">{{ $reportsDue }} show {{ \Illuminate\Support\Str::plural('report', $reportsDue) }} to finish</div>
                            <p class="mt-0.5 text-xs leading-5 text-amber-700 dark:text-amber-300">Record sold items, giveaways, promo items, and unlisted products. Unmatched items can be fixed by admin later.</p>
                        </div>
                        <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl() }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-500">Open End of Stream</a>
                    </div>
                </section>
            @endif
        @elseif($pageMode === 'fulfillment')
            <section data-vx-tour="role-overview" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Fulfillment Operations</div>
                    <h1 class="mt-1 text-xl font-semibold leading-tight text-gray-950 dark:text-white sm:text-2xl">Shipping work that needs attention</h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Work by show first. Open the show, then handle its Whatnot shipments and packing lines.</p>
                </div>

                <div data-vx-tour="role-metrics" class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">
                    @foreach ([
                        ['Shows to Work', $showsToFulfill ?? 0],
                        ['Open Shipments', $openShipments ?? 0],
                        ['Delivered Today', $deliveredToday ?? 0],
                        ['Unassigned', $unassignedShows ?? 0],
                    ] as [$label, $value])
                        <div class="bg-white px-3 py-3 dark:bg-gray-900 sm:p-4">
                            <div class="truncate text-[10px] font-medium uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">{{ $label }}</div>
                            <div class="mt-0.5 text-xl font-semibold leading-none text-gray-950 dark:text-white sm:mt-1 sm:text-2xl">{{ number_format((float)$value) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900 sm:p-4">
                <div class="grid gap-1 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:grid-cols-3 sm:gap-3">
                    <span><strong class="text-gray-700 dark:text-gray-200">1.</strong> Open a show with work.</span>
                    <span><strong class="text-gray-700 dark:text-gray-200">2.</strong> Pack and verify its shipment lines.</span>
                    <span><strong class="text-gray-700 dark:text-gray-200">3.</strong> Update shipment status/tracking as work moves.</span>
                </div>
            </section>

            <section data-vx-tour="primary-action" class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/30 sm:p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-blue-900 dark:text-blue-100">Ready to work the queue?</div>
                        <p class="mt-0.5 text-xs leading-5 text-blue-700 dark:text-blue-300">The Fulfillment Center keeps the list show-first so mobile users do not have to scan a giant shipment table.</p>
                    </div>
                    <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">Open Fulfillment Center</a>
                </div>
            </section>
        @elseif($pageMode === 'admin')
            <section data-vx-tour="role-overview" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Admin Operations Center</div>
                    <h1 class="mt-1 text-xl font-semibold leading-tight text-gray-950 dark:text-white sm:text-2xl">What needs attention now</h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Exceptions first: show reports, inventory matching, fulfillment ownership, shipments, and payouts.</p>
                </div>

                <div data-vx-tour="role-metrics" class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-5">
                    @foreach ([
                        ['Reports', $reportsToReview ?? 0],
                        ['Unmatched', $unmatchedItems ?? 0],
                        ['Open Shipments', $openShipments ?? 0],
                        ['Unassigned', $unassignedFulfillment ?? 0],
                        ['Draft Payouts', $draftPayouts ?? 0],
                    ] as [$label, $value])
                        <div class="bg-white px-3 py-3 dark:bg-gray-900 sm:p-4">
                            <div class="truncate text-[10px] font-medium uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">{{ $label }}</div>
                            <div class="mt-0.5 text-xl font-semibold leading-none text-gray-950 dark:text-white sm:mt-1 sm:text-2xl">{{ number_format((float)$value) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900 sm:p-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-m-light-bulb class="mt-0.5 h-4 w-4 shrink-0 text-primary-500" />
                    <p class="text-[11px] leading-5 text-gray-500 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-200">Recommended flow:</strong> clear unmatched/report exceptions first, make sure fulfillment is assigned, then process payouts after the show report is settled.</p>
                </div>
            </section>
        @endif

        <div data-vx-tour="dashboard-widgets" class="min-w-0">
            <x-filament-widgets::widgets :widgets="$this->getWidgets()" :columns="$this->getColumns()" />
        </div>
    </div>
</x-filament-panels::page>

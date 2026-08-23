<x-filament-panels::page>
    @php
        $pageMode = $roleMode ?? 'user';
        $myUpcomingShows = collect();
        $myRecentShows = collect();

        if ($pageMode === 'streamer') {
            $streamerId = auth()->user()?->streamer?->id;

            if ($streamerId) {
                $baseShows = \App\Models\Show::query()
                    ->inChannelContext()
                    ->with(['channel', 'streamerLogEntry'])
                    ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamerId))
                    ->whereNotIn('status', ['cancelled']);

                $nowTime = now()->format('H:i:s');

                $myUpcomingShows = (clone $baseShows)
                    ->where(function ($q) use ($nowTime) {
                        $q->whereDate('show_date', '>', today())
                            ->orWhere(function ($today) use ($nowTime) {
                                $today->whereDate('show_date', today())
                                    ->where(function ($time) use ($nowTime) {
                                        $time->whereNull('start_time')
                                            ->orWhereTime('start_time', '>', $nowTime);
                                    });
                            });
                    })
                    ->orderBy('show_date')
                    ->orderBy('start_time')
                    ->limit(8)
                    ->get();

                $myRecentShows = (clone $baseShows)
                    ->where(function ($q) use ($nowTime) {
                        $q->whereDate('show_date', '<', today())
                            ->orWhere(function ($today) use ($nowTime) {
                                $today->whereDate('show_date', today())
                                    ->where(function ($time) use ($nowTime) {
                                        $time->whereNull('start_time')
                                            ->orWhereTime('start_time', '<=', $nowTime);
                                    });
                            });
                    })
                    ->orderByDesc('show_date')
                    ->orderByDesc('start_time')
                    ->limit(10)
                    ->get();
            }
        }
    @endphp

    <div
        class="space-y-3 sm:space-y-5"
        @if($pageMode === 'streamer') data-vx-page="streamer-dashboard"
        @elseif($pageMode === 'fulfillment') data-vx-page="fulfillment-dashboard"
        @elseif($pageMode === 'admin') data-vx-page="admin-dashboard"
        @endif
    >
        @if($pageMode === 'streamer')
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Streamer Center</div>
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">My Shows</span>
                    </div>

                    <div class="mt-2">
                        <h1 class="text-xl font-semibold leading-tight text-gray-950 dark:text-white sm:text-2xl">Your show schedule</h1>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Upcoming shows come in automatically from Whatnot. After a show, open it here and record the inventory you actually sold, gave away, used as promo, or otherwise used during the stream.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">
                    @foreach ([
                        ['Upcoming', $myUpcomingShows->count(), 'On your schedule'],
                        ['Recent Shows', $myRecentShows->count(), 'Quick access'],
                        ['Products', $inventoryCount ?? 0, 'In your inventory'],
                        ['Units', $inventoryUnits ?? 0, 'Available now'],
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
                        <div class="text-xs font-semibold text-gray-900 dark:text-white">How this works</div>
                        <div class="mt-1 grid gap-1 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:grid-cols-3 sm:gap-3">
                            <span><strong class="text-gray-700 dark:text-gray-200">1.</strong> Your Whatnot shows appear here automatically.</span>
                            <span><strong class="text-gray-700 dark:text-gray-200">2.</strong> Manage and move inventory normally whenever you need it.</span>
                            <span><strong class="text-gray-700 dark:text-gray-200">3.</strong> Open a recent show to start or resume its report.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5 sm:py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Upcoming Shows</h2>
                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">Your imported Whatnot schedule.</p>
                    </div>
                    <span class="rounded-full bg-primary-50 px-2 py-1 text-[10px] font-semibold text-primary-700 dark:bg-primary-950/30 dark:text-primary-300 sm:text-xs">{{ $myUpcomingShows->count() }}</span>
                </div>

                @forelse($myUpcomingShows as $show)
                    <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}"
                        class="group flex items-center gap-3 border-b border-gray-100 px-3.5 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 sm:px-5 sm:py-4">
                        <div class="flex w-11 shrink-0 flex-col items-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 text-center dark:border-gray-700 dark:bg-gray-800 sm:w-12">
                            <div class="w-full bg-primary-600 py-0.5 text-[8px] font-bold uppercase tracking-wide text-white sm:text-[9px]">{{ $show->show_date?->format('M') }}</div>
                            <div class="py-1 text-base font-semibold leading-none text-gray-950 dark:text-white sm:text-lg">{{ $show->show_date?->format('j') }}</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $show->title }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                                @if($show->start_time)<span>{{ $show->start_time->format('g:i A') }}</span>@endif
                                @if($show->channel)<span>· {{ $show->channel->name }}</span>@endif
                                <span class="rounded-full bg-blue-50 px-1.5 py-0.5 font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">Upcoming</span>
                            </div>
                        </div>
                        <x-heroicon-m-chevron-right class="h-5 w-5 shrink-0 text-gray-300 transition group-hover:text-primary-500" />
                    </a>
                @empty
                    <div class="px-4 py-8 text-center sm:px-5 sm:py-10">
                        <x-heroicon-o-calendar-days class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                        <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">No upcoming shows</div>
                        <p class="mt-1 text-xs text-gray-500">New Whatnot shows assigned to you will appear here automatically.</p>
                    </div>
                @endforelse
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5 sm:py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Recent Shows</h2>
                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">Tap a show to start, resume, or review its End of Stream report.</p>
                    </div>
                </div>

                @forelse($myRecentShows as $show)
                    @php
                        $report = $show->streamerLogEntry;

                        if ($report?->status === 'changes_requested') {
                            $reportLabel = 'Changes requested';
                            $reportTone = 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-200';
                            $actionLabel = 'Fix report';
                        } elseif ($report?->status === 'admin_approved') {
                            $reportLabel = 'Approved';
                            $reportTone = 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-200';
                            $actionLabel = 'View report';
                        } elseif ($report?->submitted_at) {
                            $reportLabel = 'Submitted';
                            $reportTone = 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200';
                            $actionLabel = 'View report';
                        } elseif ($report && $report->items()->exists()) {
                            $reportLabel = 'Draft';
                            $reportTone = 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
                            $actionLabel = 'Resume';
                        } else {
                            $reportLabel = 'Not started';
                            $reportTone = 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
                            $actionLabel = 'Start report';
                        }
                    @endphp

                    <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id]) }}"
                        class="group block border-b border-gray-100 px-3.5 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 sm:px-5 sm:py-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300 sm:h-11 sm:w-11">
                                <x-heroicon-m-video-camera class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $show->title }}</div>
                                        <div class="mt-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                                            {{ $show->show_date?->format('M j, Y') }}
                                            @if($show->start_time) · {{ $show->start_time->format('g:i A') }} @endif
                                        </div>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold sm:text-xs {{ $reportTone }}">{{ $reportLabel }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                                        @if($show->units_sold !== null)<span>{{ number_format($show->units_sold) }} Whatnot orders</span>@endif
                                        @if($show->gross_revenue !== null)<span>${{ number_format((float)$show->gross_revenue, 2) }} sales</span>@endif
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1 text-[11px] font-semibold text-primary-600 sm:text-xs">
                                        {{ $actionLabel }}
                                        <x-heroicon-m-chevron-right class="h-4 w-4" />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-8 text-center sm:px-5 sm:py-10">
                        <x-heroicon-o-clock class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                        <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">No recent shows yet</div>
                        <p class="mt-1 text-xs text-gray-500">Your latest shows will appear here for quick reporting.</p>
                    </div>
                @endforelse
            </section>
        @elseif($pageMode === 'fulfillment')
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Fulfillment Operations</div>
                    <h1 class="mt-1 text-xl font-semibold leading-tight text-gray-950 dark:text-white sm:text-2xl">Shipping work that needs attention</h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Work by show first. Open the show, then handle its Whatnot shipments and packing lines.</p>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">
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

            <section class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/30 sm:p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-blue-900 dark:text-blue-100">Ready to work the queue?</div>
                        <p class="mt-0.5 text-xs leading-5 text-blue-700 dark:text-blue-300">The Fulfillment Center keeps the list show-first so mobile users do not have to scan a giant shipment table.</p>
                    </div>
                    <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">Open Fulfillment Center</a>
                </div>
            </section>
        @elseif($pageMode === 'admin')
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Admin Operations Center</div>
                    <h1 class="mt-1 text-xl font-semibold leading-tight text-gray-950 dark:text-white sm:text-2xl">What needs attention now</h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Exceptions first: show reports, inventory matching, fulfillment ownership, shipments, and payouts.</p>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-5">
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

        <div class="min-w-0">
            <x-filament-widgets::widgets :widgets="$this->getWidgets()" :columns="$this->getColumns()" />
        </div>
    </div>
</x-filament-panels::page>

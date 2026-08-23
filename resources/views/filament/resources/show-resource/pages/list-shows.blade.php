<x-filament-panels::page>
    @php
        $ops = $this->getOperations();
        $isStreamer = $ops['isStreamer'];

        $reportState = function ($show) {
            $log = $show->streamerLogEntry;
            if ($log?->status === 'changes_requested') return ['Changes requested', 'red'];
            if ($log?->status === 'admin_approved') return ['Approved', 'green'];
            if ($log?->submitted_at) return ['Submitted', 'blue'];
            if ($log && $log->items->isNotEmpty()) return ['Draft', 'amber'];
            return ['Not started', 'gray'];
        };

        $toneClass = fn ($tone) => match($tone) {
            'red' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-200',
            'green' => 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-200',
            'blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200',
            'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
            default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        };
    @endphp

    <div class="space-y-3 sm:space-y-5" data-vx-page="shows-operations">
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">{{ $isStreamer ? 'My Shows' : 'Shows Operations Center' }}</div>
                        <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $isStreamer ? 'Schedule and show reports' : 'Run shows from one operational view' }}</h2>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">
                            @if($isStreamer)
                                Upcoming Whatnot shows, recent streams, and the reports you can start or resume.
                            @else
                                See the schedule, report status, streamer ownership, fulfillment, shipments, and shows that need attention without digging through a table first.
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 gap-2 text-[10px] sm:text-xs">
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">{{ $ops['upcoming']->count() }} upcoming</span>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $ops['recent']->count() }} recent</span>
                        @unless($isStreamer)
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-200">{{ $ops['needsAttention']->count() }} attention</span>
                        @endunless
                    </div>
                </div>
            </div>
        </section>

        @unless($isStreamer)
            <section class="overflow-hidden rounded-xl border border-amber-200 bg-white dark:border-amber-900/70 dark:bg-gray-900 sm:rounded-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-amber-100 bg-amber-50/60 px-4 py-3 dark:border-amber-900/40 dark:bg-amber-950/20 sm:px-5 sm:py-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Needs Attention</h3>
                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">Exceptions, reviews, fulfillment ownership, or open shipments.</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-200 sm:text-xs">{{ $ops['needsAttention']->count() }}</span>
                </div>

                @forelse($ops['needsAttention'] as $show)
                    @php
                        [$reportLabel, $reportTone] = $reportState($show);
                        $unmatched = $show->streamerLogEntry?->items->whereNull('inventory_item_id')->count() ?? 0;
                        $issues = [];
                        if ($show->channel_attribution_suspect) $issues[] = 'channel';
                        if ($show->financials_revised_after_lock) $issues[] = 'financials changed';
                        if ($unmatched > 0) $issues[] = $unmatched.' unmatched';
                        if ($show->streamerLogEntry?->status === 'streamer_reviewed') $issues[] = 'report review';
                        if ($show->streamerLogEntry?->status === 'changes_requested') $issues[] = 'changes requested';
                        if ((int)$show->shipments_count > 0 && $show->fulfillmentUsers->isEmpty()) $issues[] = 'fulfillment unassigned';
                        if ((int)$show->open_shipments_count > 0) $issues[] = $show->open_shipments_count.' shipments open';
                    @endphp
                    <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="group block border-b border-gray-100 px-3.5 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 sm:px-5 sm:py-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300">
                                <x-heroicon-m-exclamation-triangle class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $show->title }}</div>
                                        <div class="mt-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">{{ $show->show_date?->format('M j, Y') }} @if($show->start_time) · {{ $show->start_time->format('g:i A') }} @endif</div>
                                    </div>
                                    <x-heroicon-m-chevron-right class="mt-1 h-5 w-5 shrink-0 text-gray-300 group-hover:text-primary-500" />
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold sm:text-xs {{ $toneClass($reportTone) }}">{{ $reportLabel }}</span>
                                    @foreach($issues as $issue)
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-200 sm:text-xs">{{ $issue }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-4 py-7 text-center sm:px-5 sm:py-8">
                        <x-heroicon-o-check-circle class="mx-auto h-8 w-8 text-green-400" />
                        <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">Nothing urgent right now</div>
                        <p class="mt-1 text-xs text-gray-500">Shows needing review or operational attention will surface here.</p>
                    </div>
                @endforelse
            </section>
        @endunless

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5 sm:py-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $isStreamer ? 'My Upcoming Shows' : 'Upcoming Schedule' }}</h3>
                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">Imported from Whatnot and ordered by show time.</p>
                </div>
                <span class="rounded-full bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-200 sm:text-xs">{{ $ops['upcoming']->count() }}</span>
            </div>

            @forelse($ops['upcoming'] as $show)
                <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="group flex items-center gap-3 border-b border-gray-100 px-3.5 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 sm:px-5 sm:py-4">
                    <div class="flex w-11 shrink-0 flex-col items-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 text-center dark:border-gray-700 dark:bg-gray-800 sm:w-12">
                        <div class="w-full bg-primary-600 py-0.5 text-[8px] font-bold uppercase tracking-wide text-white sm:text-[9px]">{{ $show->show_date?->format('M') }}</div>
                        <div class="py-1 text-base font-semibold leading-none text-gray-950 dark:text-white sm:text-lg">{{ $show->show_date?->format('j') }}</div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $show->title }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                            @if($show->start_time)<span>{{ $show->start_time->format('g:i A') }}</span>@endif
                            @if($show->channel)<span>· {{ $show->channel->name }}</span>@endif
                            @unless($isStreamer)
                                <span>· {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</span>
                                <span>· Fulfillment: {{ $show->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
                            @endunless
                        </div>
                    </div>
                    <span class="hidden rounded-full bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-200 sm:inline-flex sm:text-xs">Upcoming</span>
                    <x-heroicon-m-chevron-right class="h-5 w-5 shrink-0 text-gray-300 group-hover:text-primary-500" />
                </a>
            @empty
                <div class="px-4 py-8 text-center sm:px-5 sm:py-10">
                    <x-heroicon-o-calendar-days class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                    <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">No upcoming shows</div>
                    <p class="mt-1 text-xs text-gray-500">Imported future shows will appear here.</p>
                </div>
            @endforelse
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5 sm:py-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $isStreamer ? 'Recent Shows & Reports' : 'Recent Shows' }}</h3>
                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">{{ $isStreamer ? 'Tap any show to start or resume its report.' : 'Latest shows with report, fulfillment, shipment, and sales context.' }}</p>
                </div>
            </div>

            @forelse($ops['recent'] as $show)
                @php
                    [$reportLabel, $reportTone] = $reportState($show);
                    $targetUrl = $isStreamer
                        ? \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $show->id])
                        : \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]);
                    $actionLabel = $isStreamer
                        ? match($reportLabel) {
                            'Draft' => 'Resume',
                            'Changes requested' => 'Fix report',
                            'Submitted', 'Approved' => 'View report',
                            default => 'Start report',
                        }
                        : 'Open show';
                @endphp
                <a href="{{ $targetUrl }}" class="group block border-b border-gray-100 px-3.5 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 sm:px-5 sm:py-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300 sm:h-11 sm:w-11">
                            <x-heroicon-m-video-camera class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $show->title }}</div>
                                    <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                                        <span>{{ $show->show_date?->format('M j, Y') }}</span>
                                        @if($show->start_time)<span>· {{ $show->start_time->format('g:i A') }}</span>@endif
                                        @unless($isStreamer)<span>· {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</span>@endunless
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold sm:text-xs {{ $toneClass($reportTone) }}">{{ $reportLabel }}</span>
                            </div>

                            <div class="mt-2 flex items-end justify-between gap-2">
                                <div class="flex min-w-0 flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                                    @if($show->units_sold !== null)<span>{{ number_format($show->units_sold) }} orders</span>@endif
                                    @if($show->gross_revenue !== null)<span>${{ number_format((float)$show->gross_revenue, 2) }} sales</span>@endif
                                    @unless($isStreamer)
                                        <span>{{ number_format((int)$show->shipments_count) }} shipments</span>
                                        @if((int)$show->open_shipments_count > 0)<span class="font-medium text-amber-600">{{ $show->open_shipments_count }} open</span>@endif
                                        <span>Fulfillment: {{ $show->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
                                    @endunless
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1 text-[11px] font-semibold text-primary-600 sm:text-xs">{{ $actionLabel }} <x-heroicon-m-chevron-right class="h-4 w-4" /></span>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-4 py-8 text-center sm:px-5 sm:py-10">
                    <x-heroicon-o-clock class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                    <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">No recent shows</div>
                </div>
            @endforelse
        </section>

        @unless($isStreamer)
            <details class="group overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 sm:px-5 sm:py-4">
                    <div>
                        <div class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">All Shows / Advanced View</div>
                        <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">Filters, bulk actions, export, historical data, and detailed table columns.</div>
                    </div>
                    <x-heroicon-m-chevron-down class="h-5 w-5 shrink-0 text-gray-400 transition group-open:rotate-180" />
                </summary>
                <div class="border-t border-gray-100 p-2 dark:border-gray-800 sm:p-3">
                    <x-kpi-row :stats="$this->getStats()" />
                    <div class="mt-3">{{ $this->table }}</div>
                </div>
            </details>
        @endunless

        <div id="show-log-panel-container">
            <livewire:show-streamer-log-panel />
        </div>
    </div>

    <script>
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-table-action="open_log"]');
            if (!btn) return;
            const row = btn.closest('tr');
            if (!row) return;
            const recordKey = row.getAttribute('data-record-key');
            if (recordKey) Livewire.dispatch('open-show', { show: recordKey });
        });
    </script>
</x-filament-panels::page>

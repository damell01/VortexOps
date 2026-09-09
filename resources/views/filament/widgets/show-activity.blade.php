@php
    $events = $this->events;
    $changes = $events->where('type', 'whatnot_change');
    $activity = $events->where('type', '!=', 'whatnot_change');
@endphp

<x-filament-widgets::widget>
    <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600">Audit trail</div>
                <h2 class="mt-1 text-base font-bold text-gray-950 dark:text-white">Show History & Changes</h2>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400">See the useful changes first. Import runs and workflow activity stay available below without taking over the page.</p>
            </div>
            <span class="w-fit shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ $changes->count() }} changes · {{ $activity->count() }} other events</span>
        </div>

        @if($changes->isNotEmpty())
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @foreach($changes->take(12) as $event)
                    <article class="min-w-0 rounded-xl border border-blue-100 bg-blue-50/50 p-3.5 dark:border-blue-900/60 dark:bg-blue-950/20">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-blue-600 dark:text-blue-300">{{ $event['field'] ?? $event['title'] }}</div>
                                <div class="mt-1 text-xs font-semibold text-gray-950 dark:text-white">Changed {{ $event['at']?->diffForHumans() }}</div>
                            </div>
                            <div class="shrink-0 text-[10px] text-gray-400">{{ $event['at']?->format('M j, g:i A') }}</div>
                        </div>

                        <div class="mt-3 grid grid-cols-[minmax(0,1fr)_20px_minmax(0,1fr)] items-stretch gap-2">
                            <div class="min-w-0 rounded-lg border border-gray-200 bg-white p-2.5 dark:border-gray-700 dark:bg-gray-900">
                                <div class="text-[9px] font-bold uppercase tracking-wide text-gray-400">Previous</div>
                                <div class="mt-1 break-words text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $event['old'] ?? '—' }}</div>
                            </div>
                            <div class="flex items-center justify-center text-gray-400">→</div>
                            <div class="min-w-0 rounded-lg border border-blue-200 bg-white p-2.5 dark:border-blue-800 dark:bg-gray-900">
                                <div class="text-[9px] font-bold uppercase tracking-wide text-blue-500">Current</div>
                                <div class="mt-1 break-words text-xs font-bold text-gray-950 dark:text-white">{{ $event['new'] ?? '—' }}</div>
                            </div>
                        </div>

                        @if($event['meta'])
                            <div class="mt-2 break-words text-[10px] text-gray-400">{{ $event['meta'] }}</div>
                        @endif
                    </article>
                @endforeach
            </div>

            @if($changes->count() > 12)
                <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-gray-800/70 dark:text-gray-400">Showing the 12 most recent meaningful field changes. Older changes remain in the ingestion audit history.</div>
            @endif
        @else
            <div class="mt-4 rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center dark:border-gray-700">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-200">No meaningful field changes recorded yet</div>
                <p class="mt-1 text-xs text-gray-500">Future Whatnot changes will show previous and current values here.</p>
            </div>
        @endif

        @if($activity->isNotEmpty())
            <details class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-gray-50 px-3.5 py-3 text-xs font-semibold text-gray-700 dark:bg-gray-800/70 dark:text-gray-200">
                    <span>Imports & workflow activity</span>
                    <span class="rounded-full bg-white px-2 py-0.5 text-[10px] text-gray-500 shadow-sm dark:bg-gray-900 dark:text-gray-400">{{ $activity->count() }} events</span>
                </summary>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($activity->take(20) as $event)
                        <div class="grid min-w-0 grid-cols-[28px_minmax(0,1fr)] gap-3 px-3.5 py-3">
                            <div class="flex h-7 w-7 items-center justify-center rounded-full {{ $event['type'] === 'ingestion' ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-200' : ($event['type'] === 'inventory' ? 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200') }}">
                                @if($event['type'] === 'ingestion')
                                    <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                                @elseif($event['type'] === 'inventory')
                                    <x-heroicon-m-archive-box class="h-4 w-4" />
                                @else
                                    <x-heroicon-m-clipboard-document-list class="h-4 w-4" />
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="flex min-w-0 flex-col gap-0.5 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
                                    <div class="min-w-0 break-words text-xs font-semibold text-gray-950 dark:text-white">{{ $event['title'] }}</div>
                                    <div class="shrink-0 text-[10px] text-gray-400">{{ $event['at']?->format('M j, g:i A') }}</div>
                                </div>
                                @if($event['detail'])
                                    <div class="mt-1 break-words text-xs leading-5 text-gray-600 dark:text-gray-300">{{ $event['detail'] }}</div>
                                @endif
                                @if($event['meta'])
                                    <div class="mt-1 break-words text-[10px] text-gray-400">{{ $event['meta'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </section>
</x-filament-widgets::widget>

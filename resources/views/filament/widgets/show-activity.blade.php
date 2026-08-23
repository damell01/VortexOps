@php
    $events = $this->events;
@endphp

<x-filament-widgets::widget>
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Show Activity & Changes</h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Whatnot changes, report activity, approvals, and inventory movements in one history.</p>
            </div>
            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">{{ $events->count() }} events</span>
        </div>

        <div class="mt-3 sm:mt-5">
            @forelse($events as $event)
                @php
                    $tone = match($event['type']) {
                        'inventory' => 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-200',
                        'whatnot_change' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
                        'review' => 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
                    };
                @endphp
                <div class="grid grid-cols-[24px_minmax(0,1fr)] gap-2.5 py-2.5 sm:grid-cols-[28px_minmax(0,1fr)] sm:gap-3 sm:py-3">
                    <div class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full {{ $tone }} sm:h-7 sm:w-7">
                        @if($event['type'] === 'inventory')
                            <x-heroicon-m-archive-box class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        @elseif($event['type'] === 'whatnot_change')
                            <x-heroicon-m-arrow-path class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        @elseif($event['type'] === 'review')
                            <x-heroicon-m-check class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        @else
                            <x-heroicon-m-clipboard-document-list class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        @endif
                    </div>
                    <div class="min-w-0 border-b border-gray-100 pb-2.5 last:border-0 dark:border-gray-800 sm:pb-3">
                        <div class="flex flex-col gap-0.5 sm:flex-row sm:items-start sm:justify-between sm:gap-2">
                            <div class="text-xs font-medium leading-5 text-gray-950 dark:text-white sm:text-sm">{{ $event['title'] }}</div>
                            <div class="shrink-0 text-[10px] text-gray-400 sm:text-xs">{{ $event['at']?->format('M j, g:i A') }}</div>
                        </div>
                        <div class="mt-0.5 text-xs leading-5 text-gray-600 dark:text-gray-300 sm:mt-1 sm:text-sm">{{ $event['detail'] }}</div>
                        @if($event['meta'])
                            <div class="mt-0.5 truncate text-[10px] text-gray-400 sm:mt-1 sm:text-xs">{{ $event['meta'] }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-gray-700">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">No tracked activity yet</div>
                    <p class="mt-1 text-xs text-gray-500">Whatnot sync changes and show-linked inventory events will appear here.</p>
                </div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>

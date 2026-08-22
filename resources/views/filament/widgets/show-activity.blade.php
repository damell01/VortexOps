@php($events = $this->events)

<x-filament-widgets::widget>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div>
            <h2 class="font-semibold text-gray-950 dark:text-white">Show Activity & Changes</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Whatnot metric changes, streamer report activity, and show-linked inventory movements in one timeline.</p>
        </div>

        <div class="mt-5 space-y-1">
            @forelse($events as $event)
                @php
                    $tone = match($event['type']) {
                        'inventory' => 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-200',
                        'whatnot_change' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200',
                        'review' => 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
                    };
                @endphp
                <div class="grid grid-cols-[28px_minmax(0,1fr)] gap-3 py-3">
                    <div class="mt-0.5 flex h-7 w-7 items-center justify-center rounded-full {{ $tone }}">
                        @if($event['type'] === 'inventory')
                            <x-heroicon-m-archive-box class="h-4 w-4" />
                        @elseif($event['type'] === 'whatnot_change')
                            <x-heroicon-m-arrow-path class="h-4 w-4" />
                        @elseif($event['type'] === 'review')
                            <x-heroicon-m-check class="h-4 w-4" />
                        @else
                            <x-heroicon-m-clipboard-document-list class="h-4 w-4" />
                        @endif
                    </div>
                    <div class="min-w-0 border-b border-gray-100 pb-3 last:border-0 dark:border-gray-800">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                            <div class="font-medium text-gray-950 dark:text-white">{{ $event['title'] }}</div>
                            <div class="shrink-0 text-xs text-gray-400">{{ $event['at']?->format('M j, Y g:i A') }}</div>
                        </div>
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $event['detail'] }}</div>
                        @if($event['meta'])
                            <div class="mt-1 text-xs text-gray-400">{{ $event['meta'] }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No tracked activity has been recorded for this show yet.</div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>

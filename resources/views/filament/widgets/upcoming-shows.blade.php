<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-5">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-300">
                    <x-heroicon-o-calendar-days class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Upcoming Shows</h2>
                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">Your next scheduled Whatnot shows</p>
                </div>
            </div>
            <a href="{{ $allShowsUrl }}" wire:navigate class="shrink-0 text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-300">View all →</a>
        </div>

        @forelse($shows as $show)
            <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" wire:navigate
               class="grid grid-cols-[96px_minmax(0,1fr)_auto] items-center gap-3 border-b border-gray-100 px-4 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5 sm:px-5">
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $show->show_date?->format('D, M j') }}</div>
                    <div class="mt-0.5">{{ $show->start_time?->format('g:i A') ?? 'Time TBD' }}</div>
                </div>

                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $show->title }}</div>
                    <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                        @if($show->channel)
                            <span class="truncate">{{ $show->channel->display_title ?: $show->channel->name }}</span>
                        @endif
                        <span class="rounded-full bg-blue-50 px-2 py-0.5 font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">Upcoming</span>
                    </div>
                </div>

                <x-heroicon-m-chevron-right class="h-4 w-4 text-gray-300 dark:text-gray-600" />
            </a>
        @empty
            <div class="px-5 py-10 text-center">
                <x-heroicon-o-calendar-days class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">No upcoming shows</div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">New Whatnot shows will appear here automatically.</p>
            </div>
        @endforelse
    </section>
</x-filament-widgets::widget>

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-5">
            <div>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Recent Shows</h2>
                <p class="mt-0.5 text-[11px] text-gray-500 sm:text-xs">Completed streams through yesterday.</p>
            </div>
            <a href="{{ $allShowsUrl }}" class="shrink-0 text-xs font-semibold text-primary-600">View all →</a>
        </div>

        <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5 sm:p-5">
            @forelse($shows as $show)
                @php
                    $missingGross = $show->gross_revenue === null;
                    $missingNet = $show->completed_earnings === null;
                    $missingDuration = $show->show_duration === null;
                    $hasMissing = $missingGross || $missingNet || $missingDuration;
                @endphp
                <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}"
                   class="group min-w-0 rounded-xl border border-gray-200 bg-gray-50/70 p-3 transition hover:border-primary-300 hover:bg-primary-50/40 dark:border-gray-700 dark:bg-gray-800/40 dark:hover:border-primary-700">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $show->show_date?->format('M j') }}</div>
                            <div class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $show->title }}</div>
                        </div>
                        @if($hasMissing)
                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-1 text-[9px] font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Missing data</span>
                        @else
                            <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-1 text-[9px] font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Complete</span>
                        @endif
                    </div>
                    <div class="mt-1 truncate text-[11px] text-gray-500">{{ $show->channel?->name ?: 'Whatnot' }}</div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <div><div class="text-[9px] uppercase tracking-wide text-gray-400">Gross</div><div class="mt-0.5 text-xs font-bold text-gray-900 dark:text-white">{{ $missingGross ? '—' : '$'.number_format((float)$show->gross_revenue, 0) }}</div></div>
                        <div><div class="text-[9px] uppercase tracking-wide text-gray-400">Net</div><div class="mt-0.5 text-xs font-bold text-gray-900 dark:text-white">{{ $missingNet ? '—' : '$'.number_format((float)$show->completed_earnings, 0) }}</div></div>
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-2 text-[10px] dark:border-gray-700">
                        <span class="{{ $missingDuration ? 'text-amber-600' : 'text-gray-500' }}">{{ $missingDuration ? 'Duration missing' : number_format((float)$show->show_duration / 60, 1).' hrs' }}</span>
                        <span class="font-semibold text-primary-600">Details →</span>
                    </div>
                </a>
            @empty
                <div class="col-span-full py-8 text-center text-sm text-gray-500">No completed shows from the previous 7 days.</div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>

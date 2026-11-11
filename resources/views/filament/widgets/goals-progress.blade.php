<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-5">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-300">
                    <x-heroicon-o-flag class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $monthLabel }} goals</h2>
                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">Progress so far this month</p>
                </div>
            </div>
            <a href="{{ $url }}" wire:navigate class="shrink-0 text-xs font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-300">{{ $progress->isEmpty() ? 'Set goals →' : 'Manage →' }}</a>
        </div>
        <div class="px-4 py-4 sm:px-5 text-sm text-gray-800 dark:text-gray-100">
            @if ($progress->isEmpty())
                <div class="text-center">
                    <x-heroicon-o-flag class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                    <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">No goals for {{ $monthLabel }} yet</div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Set a monthly target for net, sales, shows or new buyers.</p>
                    <a href="{{ $url }}" wire:navigate class="mt-3 inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500">Set this month's goals</a>
                </div>
            @else
                @include('filament.components.goal-bars', ['progress' => $progress])
            @endif
        </div>
    </section>
</x-filament-widgets::widget>

<x-filament-panels::page>
    <div class="space-y-6">
        @if (!$this->show)
            <div class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 text-amber-600 dark:text-amber-400">
                        <x-heroicon-o-information-circle class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-amber-900 dark:text-amber-100">No Show Selected</h3>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-200">
                            Please use the "End of Stream" button from your show to log data, or navigate from a specific show.
                        </p>
                    </div>
                </div>
            </div>
        @else
            <form wire:submit="submit" class="space-y-6">
                {{ $this->form }}

                <div class="flex gap-3">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-500 transition-colors">
                        <x-heroicon-o-check-circle class="h-5 w-5" />
                        Save & Continue
                    </button>
                    <a href="{{ route('filament.admin.resources.shows.edit', $this->show) }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-6 py-2.5 text-sm font-semibold text-gray-900 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <x-heroicon-o-arrow-left class="h-5 w-5" />
                        Back to Show
                    </a>
                </div>
            </form>
        @endif
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 text-center space-y-4">
        <x-heroicon-o-check-circle-stack class="h-12 w-12 text-primary-500 mx-auto" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Laravel Horizon</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
            Monitor queued jobs, failed jobs, throughput, and worker metrics in real time.
        </p>
        <a
            href="{{ url('/horizon') }}"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition"
        >
            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
            Open Horizon Dashboard
        </a>
    </div>
</x-filament-panels::page>

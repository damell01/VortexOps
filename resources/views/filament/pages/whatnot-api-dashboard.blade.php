<x-filament-panels::page>
    <div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700">
        <h2 class="text-lg font-semibold">Whatnot Seller API Connections</h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            The existing scraper remains active. API synchronization is disabled while credentials are pending.
        </p>
        <a href="{{ route('whatnot-api.dashboard') }}" class="mt-4 inline-block rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">
            Open connection health and token setup
        </a>
    </div>
</x-filament-panels::page>

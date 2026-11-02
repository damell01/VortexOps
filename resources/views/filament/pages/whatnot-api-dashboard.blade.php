<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-gray-200 p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold">Whatnot Seller API Connections</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                API capture is disabled by default for every channel. Save a token, run a successful live connection test, and verify the contract and pagination before enabling read-only synchronization. The scraper remains active.
            </p>
            <a href="{{ route('whatnot-api.dashboard') }}" class="mt-4 inline-block rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">
                Open connection setup, health and activation
            </a>
        </section>
        <section class="rounded-xl border border-gray-200 p-5 dark:border-gray-700">
            <h2 class="text-lg font-semibold">Ledger Reconciliation</h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Review show and streamer associations, sales, refunds, tips, fees and business marketing expenses. Uncertain transactions remain in review rather than being allocated to the wrong streamer.
            </p>
            <a href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index') }}" class="mt-4 inline-block rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">
                Open ledger and reconciliation dashboard
            </a>
        </section>
    </div>
    <section class="rounded-xl border border-gray-200 p-5 dark:border-gray-700">
        <h2 class="text-lg font-semibold">Server-side diagnostics</h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Run read-only diagnostics from the production server. No credentials are displayed by these commands.
        </p>
        <div class="mt-3 grid gap-2 text-sm">
            <code>php artisan whatnot:api-diagnose --json</code>
            <code>php artisan ledger:coverage --json</code>
            <code>php artisan ledger:business-summary --json</code>
        </div>
    </section>
</x-filament-panels::page>

<x-filament-panels::page>
<div class="space-y-6">

    {{-- ── Config Status ───────────────────────────────────────────────────── --}}
    @if (! $this->configured)
        <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950 px-5 py-4 text-sm text-amber-800 dark:text-amber-200">
            <p class="font-semibold">Whatnot credentials not configured.</p>
            <p class="mt-1 text-xs">Set <code class="font-mono bg-amber-100 dark:bg-amber-900 px-1 rounded">WHATNOT_EMAIL</code> and <code class="font-mono bg-amber-100 dark:bg-amber-900 px-1 rounded">WHATNOT_PASSWORD</code> in your <code class="font-mono">.env</code> file, then restart the server.</p>
        </div>
    @endif

    {{-- ── Actions ─────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Test Connection --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-5 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Test Connection</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Verify Whatnot credentials are working and the seller dashboard is accessible.
                </p>
            </div>

            @if ($testResult)
                @if ($testStatus !== 'success' && str_contains($testResult, 'Playwright not found'))
                    <div class="rounded-lg bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 px-3 py-3 text-xs space-y-2">
                        <p class="font-semibold text-amber-800 dark:text-amber-200">Playwright is not installed on this server.</p>
                        <p class="text-amber-700 dark:text-amber-300">Run these commands on the VPS to set it up:</p>
                        <div class="space-y-1 font-mono bg-amber-100 dark:bg-amber-900 rounded p-2">
                            <div>npm install -g playwright</div>
                            <div>npx playwright install chromium --with-deps</div>
                        </div>
                        <p class="text-amber-600 dark:text-amber-400">Then click Test Connection again.</p>
                    </div>
                @else
                    <div class="rounded-lg {{ $testStatus === 'success' ? 'bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300' }} px-3 py-2 text-xs">
                        {{ $testResult }}
                    </div>
                @endif
            @endif

            <button
                wire:click="testConnection"
                wire:loading.attr="disabled"
                type="button"
                @disabled(!$this->configured)
                class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 disabled:opacity-40"
            >
                <span wire:loading.remove wire:target="testConnection">
                    <x-heroicon-o-signal class="h-4 w-4" />
                </span>
                <span wire:loading wire:target="testConnection">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="testConnection">Test Connection</span>
                <span wire:loading wire:target="testConnection">Testing…</span>
            </button>
        </div>

        {{-- Run Import --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-5 space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Run Import Now</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    Pull the latest shows from all enabled channels.
                    Runs automatically every 30 min via scheduler.
                    @if ($this->lastImport)
                        <br>Last import: <span class="font-medium">{{ \Carbon\Carbon::parse($this->lastImport)->diffForHumans() }}</span>
                    @else
                        <br>Never run yet.
                    @endif
                </p>
            </div>

            @if ($importResult)
                <div class="rounded-lg {{ $importStatus === 'success' ? 'bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300' }} px-3 py-2 text-xs">
                    {{ $importResult }}
                </div>
            @endif

            <button
                wire:click="runImport"
                wire:loading.attr="disabled"
                type="button"
                @disabled(!$this->configured)
                class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-40"
            >
                <span wire:loading.remove wire:target="runImport">
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                </span>
                <span wire:loading wire:target="runImport">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="runImport">Import Now</span>
                <span wire:loading wire:target="runImport">Importing (this takes 30–90s)…</span>
            </button>

            <p class="text-xs text-gray-400">
                Or run via CLI: <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">php artisan whatnot:import</code>
            </p>
        </div>
    </div>

    {{-- ── Fetch Show URLs ──────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Fetch Show URLs</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Scrapes your Whatnot seller shows list to backfill the detail URL on imported shows.
                Required before "Import Items Sold" becomes available on each show.
            </p>
        </div>

        @if ($urlResult)
            <div class="rounded-lg px-3 py-2 text-xs font-medium {{ $urlStatus === 'success' ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300' }}">
                {{ $urlResult }}
            </div>
        @endif

        <button
            wire:click="fetchShowUrls"
            wire:loading.attr="disabled"
            type="button"
            @disabled(!$this->configured)
            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 disabled:opacity-40"
        >
            <span wire:loading.remove wire:target="fetchShowUrls">
                <x-heroicon-o-link class="h-4 w-4" />
            </span>
            <span wire:loading wire:target="fetchShowUrls">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </span>
            <span wire:loading.remove wire:target="fetchShowUrls">Fetch Show URLs</span>
            <span wire:loading wire:target="fetchShowUrls">Fetching (20–60s)…</span>
        </button>
    </div>

    {{-- ── Channels ─────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Channels ({{ $this->channels->count() }})</h3>
            <a href="{{ \App\Filament\Resources\WhatnotChannelResource::getUrl('index') }}" class="text-xs text-violet-600 dark:text-violet-400 hover:underline">
                Manage Channels →
            </a>
        </div>

        @forelse ($this->channels as $channel)
            <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-50 dark:border-gray-800/50 last:border-b-0">
                <div class="flex-shrink-0 rounded-full {{ $channel->status === 'active' ? 'bg-green-100 dark:bg-green-900' : 'bg-gray-100 dark:bg-gray-800' }} p-1.5">
                    <x-heroicon-o-video-camera class="h-4 w-4 {{ $channel->status === 'active' ? 'text-green-600 dark:text-green-400' : 'text-gray-400' }}" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $channel->name }}</p>
                    <p class="text-xs text-gray-400">{{ '@' . $channel->whatnot_username }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if ($channel->include_in_import)
                        <span class="inline-flex items-center rounded-full bg-violet-100 dark:bg-violet-900 px-2 py-0.5 text-xs font-medium text-violet-700 dark:text-violet-300">Auto-import</span>
                    @endif
                    <span class="inline-flex items-center rounded-full {{ $channel->status === 'active' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' }} px-2 py-0.5 text-xs font-medium">
                        {{ ucfirst($channel->status) }}
                    </span>
                </div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-gray-400">
                No channels configured.
                <a href="{{ \App\Filament\Resources\WhatnotChannelResource::getUrl('create') }}" class="text-violet-600 dark:text-violet-400 hover:underline ml-1">Add a channel →</a>
            </div>
        @endforelse
    </div>

    {{-- ── Recent Shows ─────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Shows</h3>
        </div>

        @if (count($this->recentShows) === 0)
            <div class="px-5 py-8 text-center text-sm text-gray-400">No shows imported yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-400">Date</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-400">Title</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-400">Channel</th>
                            <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-400">Gross</th>
                            <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-400">Units</th>
                            <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                        @foreach ($this->recentShows as $show)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $show['date'] }}</td>
                                <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100 max-w-xs truncate">{{ $show['title'] }}</td>
                                <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400">{{ $show['channel'] }}</td>
                                <td class="px-4 py-2.5 text-right text-xs font-mono tabular-nums text-gray-700 dark:text-gray-300">${{ $show['gross'] }}</td>
                                <td class="px-4 py-2.5 text-right text-xs tabular-nums text-gray-500">{{ $show['units'] }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ match($show['status']) {
                                            'reconciled'     => 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300',
                                            'pending_review' => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                                            'imported'       => 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300',
                                            default          => 'bg-gray-100 dark:bg-gray-800 text-gray-500',
                                        } }}">
                                        {{ str_replace('_', ' ', ucfirst($show['status'])) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Debug / CLI Help ─────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 space-y-2">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">CLI Commands</h3>
        <div class="space-y-1.5 text-xs text-gray-500 dark:text-gray-400">
            <div class="flex gap-3 items-start">
                <code class="flex-shrink-0 rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 font-mono text-gray-700 dark:text-gray-300">php artisan whatnot:import</code>
                <span>Import latest shows from all enabled channels</span>
            </div>
            <div class="flex gap-3 items-start">
                <code class="flex-shrink-0 rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 font-mono text-gray-700 dark:text-gray-300">php artisan whatnot:import --debug</code>
                <span>Import with debug screenshots saved to <code>/tmp/whatnot-debug-*.png</code></span>
            </div>
            <div class="flex gap-3 items-start">
                <code class="flex-shrink-0 rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 font-mono text-gray-700 dark:text-gray-300">php artisan whatnot:import --channel=1</code>
                <span>Import a specific channel by ID</span>
            </div>
        </div>
    </div>

</div>
</x-filament-panels::page>

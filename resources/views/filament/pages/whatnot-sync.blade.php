<x-filament-panels::page>
    {{-- Poll fast only while a run is going, so the live log keeps up without loading an idle page. --}}
    <div class="space-y-6" @if($this->jobActive) wire:poll.3s @else wire:poll.15s @endif>
        @php($job = $this->reportingJob)
        @php($locks = $this->pipelineStatus)
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2 rounded-xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-gray-900 p-5 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ $this->jobActive ? 'bg-blue-500 animate-pulse' : (($job['status'] ?? '') === 'blocked' ? 'bg-amber-500' : (($job['status'] ?? '') === 'failed' ? 'bg-red-500' : 'bg-emerald-500')) }}"></span>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Whatnot Scraper</h2>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $job['phase'] ?? 'Ready for the next sync.' }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $this->jobActive ? 'bg-blue-100 text-blue-700' : (($job['status'] ?? '') === 'blocked' ? 'bg-amber-100 text-amber-700' : (($job['status'] ?? '') === 'failed' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700')) }}">
                        {{ strtoupper($job['status'] ?? 'IDLE') }}
                    </span>
                </div>
                <div class="vx-runs">
                    @foreach([
                        ['catchup', 'Run now: catch up everything', 'Every show since July: discovery, all missing analytics, orders, shipments and ledger. Starts immediately; takes up to a few hours.', 'is-primary'],
                        ['freshness', 'Freshness sync', 'Last 7 days only. Quick, about 10–45 minutes.', ''],
                        ['analytics', 'Analytics only', 'Just the shows still missing analytics, by ID.', ''],
                        ['full', 'Reconciliation', 'Everything since July, in smaller batches.', ''],
                        ['test', 'Smoke test', 'One show through each step, to check sign-in.', ''],
                    ] as [$mode, $label, $hint, $class])
                        <button type="button" wire:click="runReporting('{{ $mode }}')" wire:loading.attr="disabled" wire:target="runReporting"
                            wire:confirm="{{ ($this->jobActive || ($locks['pipeline'] ?? null) || ($locks['browser'] ?? null)) ? 'The scraper is busy. Stop what it is doing and start “'.$label.'” now?' : 'Start “'.$label.'” now? It runs in the background; you can watch it below.' }}"
                            class="vx-run {{ $class }}">
                            <b>{{ $label }}</b><small>{{ $hint }}</small>
                        </button>
                    @endforeach
                </div>
                @if(!empty($job['started_at']))
                    <p class="text-xs text-gray-400">Started {{ \Carbon\Carbon::parse($job['started_at'])->diffForHumans() }}</p>
                @endif
                @if(!empty($job['error']))
                    <div class="rounded-lg bg-red-50 dark:bg-red-950 p-3 text-xs text-red-700 dark:text-red-300">{{ $job['error'] }}</div>
                @endif
                @if($this->jobActive)
                    <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full w-2/3 animate-pulse rounded-full bg-violet-500"></div></div>
                @endif
                @if(!empty($job['output']))
                    {{-- A native <details> loses its open state on every 3s wire:poll
                         re-render, since the server HTML never carries an `open`
                         attribute — it snapped shut before it could be read.
                         Alpine's x-data lives outside that diff, so it survives. --}}
                    <div x-data="{ open: false }" class="rounded-lg border border-gray-200 dark:border-gray-700" wire:key="whatnot-run-output">
                        <button type="button" @click="open = !open" class="w-full cursor-pointer p-3 text-left text-xs font-semibold">
                            <span x-text="open ? '▾' : '▸'"></span> Run output
                        </button>
                        <pre x-show="open" x-cloak class="max-h-72 overflow-auto whitespace-pre-wrap bg-gray-950 p-3 text-[11px] text-gray-100">{{ $job['output'] }}</pre>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
                <div class="flex items-center justify-between">
                    <div><h2 class="text-sm font-semibold">Locks & Processes</h2><p class="text-xs text-gray-500 mt-1">Safe recovery only clears confirmed stale owners.</p></div>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach(['pipeline' => 'Coordinator', 'browser' => 'Browser'] as $key => $label)
                        @php($holder = $locks[$key] ?? null)
                        <div class="rounded-lg border border-gray-100 dark:border-gray-800 p-3">
                            <div class="flex justify-between gap-3"><span class="text-xs font-semibold">{{ $label }}</span><span class="text-xs font-semibold {{ $holder ? ($holder['alive'] ? 'text-blue-600' : 'text-amber-600') : 'text-emerald-600' }}">{{ $holder ? ($holder['alive'] ? 'ACTIVE' : 'STALE') : 'AVAILABLE' }}</span></div>
                            @if($holder)
                                @php($proc = $locks[$key.'_process'] ?? null)
                                <p class="mt-1 text-[11px] text-gray-500">PID {{ $holder['pid'] }} · {{ $holder['host'] }} @if(!empty($holder['label']))· {{ $holder['label'] }}@endif</p>
                                @if($proc)
                                    <p class="mt-1 text-[10px] text-gray-400">PPID {{ $proc['ppid'] ?? '—' }} @if(isset($proc['runtime_seconds']))· running {{ \Carbon\CarbonInterval::seconds($proc['runtime_seconds'])->cascade()->forHumans(['short'=>true,'parts'=>2]) }}@endif</p>
                                    <p class="mt-1 truncate text-[10px] font-mono text-gray-400" title="{{ $proc['command'] }}">{{ $proc['command'] }}</p>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button wire:click="recoverStaleLocks" wire:confirm="Recover only locks whose recorded process is confirmed dead?" class="px-3 py-2 text-xs font-semibold rounded-lg border border-amber-300 text-amber-700 hover:bg-amber-50">Clear Stale</button>
                    <button wire:click="forceClearLocks" wire:confirm="Stop everything the Whatnot scraper is doing right now and clear its locks?" class="px-3 py-2 text-xs font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700">Stop & Clear</button>
                </div>
            </div>
        </div>

        {{-- Live run: what the scraper is printing and the shows it has written to, while it happens. --}}
        @php($log = $this->liveLog)
        @php($pulled = $this->pulledThisRun)
        @php($stats = $this->runStats)
        @php($since = $this->runSince)
        <section class="vxr-live" aria-labelledby="vxr-live-h">
            <div class="vxr-live-head">
                <div>
                    <h2 id="vxr-live-h">{{ $this->jobActive ? 'Live run' : 'Last run' }} @if(!empty($job['mode']))<span>· {{ \App\Filament\Pages\WhatnotSyncPage::RUN_MODES[$job['mode']] ?? $job['mode'] }}</span>@endif</h2>
                    <p>{{ $this->jobActive ? 'Updates every few seconds. You can leave this page; the run keeps going.' : 'Start a run above to watch it here.' }}</p>
                </div>
                @if($stats['elapsed'])<span class="vxr-live-time">{{ $stats['elapsed'] }}</span>@endif
            </div>
            <div class="vxr-live-stats">
                <div><b>{{ number_format($stats['analytics']) }}</b><small>Shows with analytics pulled</small></div>
                <div><b>{{ number_format($stats['orders']) }}</b><small>Shows with orders re-pulled</small></div>
                <div class="{{ $stats['still_due'] ? 'is-due' : 'is-done' }}"><b>{{ number_format($stats['still_due']) }}</b><small>{{ $stats['still_due'] ? 'Shows still missing analytics' : 'All caught up on analytics' }}</small></div>
            </div>
            <div class="vxr-live-grid">
                <div class="vxr-live-col">
                    <h3>What it's doing</h3>
                    <pre class="vxr-live-log" wire:key="vxr-live-log"
                         x-data="{ stick: true }"
                         x-init="$el.scrollTop = $el.scrollHeight; new MutationObserver(() => { if (stick) $el.scrollTop = $el.scrollHeight }).observe($el, { childList: true, subtree: true, characterData: true })"
                         @scroll="stick = $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 40">@forelse($log as $line)<span @class(['is-bad' => preg_match('/fail|error|denied|timeout|challenge|cloudflare|rate limit|stopped/i', $line), 'is-good' => preg_match('/collected|updated|reconciled|created|finished|PASS/', $line) && ! preg_match('/fail|error/i', $line)])>{{ $line }}</span>
@empty<span class="is-idle">{{ $this->jobActive ? 'Starting…' : 'Nothing yet.' }}</span>@endforelse</pre>
                </div>
                <div class="vxr-live-col">
                    <h3>What it's pulled <span>{{ $pulled->count() >= 30 ? 'latest 30' : $pulled->count() }}</span></h3>
                    <div class="vx-pulled">
                        @forelse($pulled as $show)
                            <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="vx-pulled-card" wire:key="pulled-{{ $show->id }}">
                                <span class="vx-pulled-top">
                                    <b>{{ $show->title ?: 'Show #'.$show->id }}</b>
                                    <small>{{ $show->channel?->name }} · {{ $show->show_date?->format('M j') }}</small>
                                </span>
                                <span class="vx-pulled-nums">
                                    <span><small>Gross</small>{{ $show->gross_revenue !== null ? '$'.number_format((float) $show->gross_revenue, 0) : '—' }}</span>
                                    <span><small>Net</small>{{ $show->whatnot_net !== null ? '$'.number_format((float) $show->whatnot_net, 0) : '—' }}</span>
                                    <span><small>Sold</small>{{ $show->units_sold ?? '—' }}</span>
                                    <span><small>Buyers</small>{{ $show->buyers_count ?? '—' }}</span>
                                </span>
                                <span class="vx-pulled-tags">
                                    @if($show->last_analytics_synced_at && $since && $show->last_analytics_synced_at->gte($since))<i class="ok">Analytics {{ $show->analytics_sync_status === 'partial' ? '(partial)' : '' }}</i>@endif
                                    @if($show->last_synced_at && $since && $show->last_synced_at->gte($since))<i>Orders</i>@endif
                                </span>
                            </a>
                        @empty
                            <p class="vxr-live-empty">Shows appear here as their numbers come in.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <div class="rounded-xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-gray-900 p-5 space-y-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Historical Analytics CSV Backfill</h2>
                <p class="mt-1 text-xs text-gray-500">Upload the Whatnot Seller Analytics → Shows CSV for older shows. Current-day and future shows are ignored automatically.</p>
            </div>

            <form wire:submit="importAnalyticsCsv" class="grid grid-cols-1 md:grid-cols-[220px_1fr_auto] gap-3 items-end">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Channel</span>
                    <select wire:model="analyticsCsvChannelId" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 text-sm">
                        <option value="">Choose channel…</option>
                        @foreach($this->channels as $channel)
                            <option value="{{ $channel->id }}">{{ $channel->name }} ({{ chr(64) }}{{ $channel->whatnot_username }})</option>
                        @endforeach
                    </select>
                    @error('analyticsCsvChannelId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                </label>

                <label class="block">
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">Whatnot Shows CSV files</span>
                    <input type="file" wire:model="analyticsCsv" accept=".csv,text/csv" multiple class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-violet-700 hover:file:bg-violet-200 dark:text-gray-300" />
                    @error('analyticsCsv')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                </label>

                <button type="submit" wire:loading.attr="disabled" wire:target="analyticsCsv,importAnalyticsCsv" class="rounded-lg bg-violet-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="importAnalyticsCsv">Import CSV</span>
                    <span wire:loading wire:target="importAnalyticsCsv">Importing…</span>
                </button>
            </form>

            @if($this->analyticsCsvResult)
                @php($r = $this->analyticsCsvResult)
                <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-2">
                    @foreach([
                        'Files' => $r['files'] ?? 0,
                        'Rows' => $r['rows'] ?? 0,
                        'Created' => $r['created'] ?? 0,
                        'Updated' => $r['updated'] ?? 0,
                        'Already complete' => $r['already_complete'] ?? 0,
                        'Current/future ignored' => $r['ignored_current_future'] ?? 0,
                        'Blank metrics' => $r['blank_metrics'] ?? 0,
                        'Unmatched' => $r['unmatched'] ?? 0,
                        'Ambiguous' => $r['ambiguous'] ?? 0,
                        'No-shows' => $r['no_shows'] ?? 0,
                        'Exact matches' => $r['exact_matched'] ?? 0,
                        'Date-adjusted' => $r['date_tolerant_matched'] ?? 0,
                        'Fuzzy matches' => $r['fuzzy_matched'] ?? 0,
                    ] as $label => $value)
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                            <div class="text-lg font-bold">{{ number_format($value) }}</div>
                            <div class="text-[10px] text-gray-500">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
                @if(!empty($r['unmatched_examples']))
                    <details class="rounded-lg border border-amber-200 dark:border-amber-800 p-3">
                        <summary class="cursor-pointer text-xs font-semibold text-amber-700">Unmatched examples</summary>
                        <div class="mt-2 space-y-1 text-[11px] text-gray-500">@foreach($r['unmatched_examples'] as $example)<div>{{ $example }}</div>@endforeach</div>
                    </details>
                @endif
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
                <div><h2 class="text-sm font-semibold">Recent Scraper Activity</h2><p class="text-xs text-gray-500 mt-1">Shows, analytics, orders, shipments and ledger activity in one feed.</p></div>
                <a href="{{ $this->activityUrl }}" class="text-xs font-semibold text-violet-600 hover:underline">View all + filters & pagination</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($this->recentActivity as $activity)
                    <a href="{{ \App\Filament\Resources\ShowIngestionLogResource::getUrl('view', ['record' => $activity]) }}" class="block px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <div class="flex items-start gap-3">
                            <span class="mt-1 h-2 w-2 rounded-full {{ $activity->status === 'success' ? 'bg-emerald-500' : ($activity->status === 'failed' ? 'bg-red-500' : 'bg-amber-500') }}"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ $activity->sourceLabel() }}</span>
                                    <span class="text-[11px] text-gray-400">{{ $activity->channel?->name ?? 'All channels' }}</span>
                                    <span class="text-[11px] text-gray-400">· {{ $activity->created_at?->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $activity->summary() }}</p>
                                @if($activity->show)<p class="mt-1 truncate text-[11px] text-violet-600">{{ $activity->show->title }} · Show #{{ $activity->show_id }}</p>@endif
                                @php($captured = $activity->capturedFields())
                                @if($captured)
                                    <div class="mt-2 flex flex-wrap gap-1.5">@foreach(array_slice($captured,0,4,true) as $label => $value)<span class="rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-[10px]"><strong>{{ $label }}:</strong> {{ $value }}</span>@endforeach</div>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">View →</span>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-gray-500">No scraper activity recorded yet.</div>
                @endforelse
            </div>
        </div>

        {{-- ── Last Sync Status ──────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Last Sync (Shows / Orders / Buyers)</p>
                @if ($this->lastSync)
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-1">
                        {{ $this->lastSync->started_at?->diffForHumans() }}
                        <span class="text-xs font-normal text-gray-400">
                            ({{ $this->lastSync->channel?->name ?? 'All Channels' }})
                        </span>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        +{{ $this->lastSync->shows_created }} shows, +{{ $this->lastSync->orders_created }} orders, +{{ $this->lastSync->buyers_created }} buyers
                    </p>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">No completed sync yet</p>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Last Shipment Refresh</p>
                        @if ($this->lastShipmentSync)
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-1">
                                {{ $this->lastShipmentSync['at']->diffForHumans() }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ $this->lastShipmentSync['updated'] }} order(s) updated across {{ $this->lastShipmentSync['shows_checked'] }} show(s) checked
                                @if (!empty($this->lastShipmentSync['errors']))
                                    <span class="text-red-600 dark:text-red-400">· {{ count($this->lastShipmentSync['errors']) }} channel error(s)</span>
                                @endif
                            </p>
                        @else
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">No shipment refresh yet</p>
                        @endif
                    </div>
                    <button wire:click="syncShipments()" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition-colors shrink-0">
                        <x-heroicon-o-truck class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="syncShipments" />
                        Sync Shipments
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Channels + Quick Sync ──────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
                <div class="rounded-lg bg-blue-50 dark:bg-blue-900/30 p-2 shrink-0">
                    <x-heroicon-o-tv class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Active Channels</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Channels enabled for sync</p>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <button wire:click="syncIncremental()" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                        <x-heroicon-o-arrow-path class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="syncIncremental" />
                        Sync All (Incremental)
                    </button>
                    <button wire:click="syncLast30Days()" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50 transition-colors">
                        <x-heroicon-o-calendar-days class="h-3.5 w-3.5" />
                        Last 30 Days
                    </button>
                    {{ $this->fullResyncAction }}
                </div>
            </div>

            @forelse ($this->channels as $channel)
                <div class="px-6 py-4 flex items-center gap-4 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-800' : '' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $channel->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">@{{ $channel->whatnot_username }}</p>
                    </div>

                    @if ($channel->latestSync)
                        <div class="text-right shrink-0">
                            <span @class([
                                'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' => $channel->latestSync->status === 'completed',
                                'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'   => $channel->latestSync->status === 'failed',
                                'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' => $channel->latestSync->status === 'running',
                            ])>
                                {{ ucfirst($channel->latestSync->status) }}
                            </span>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                {{ $channel->latestSync->started_at?->diffForHumans() }}
                            </p>
                        </div>
                    @else
                        <span class="text-xs text-gray-400 dark:text-gray-500">Never synced</span>
                    @endif

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button wire:click="syncIncremental({{ $channel->id }})" wire:loading.attr="disabled"
                            class="px-2.5 py-1 text-xs rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Sync
                        </button>
                        <button wire:click="syncLast30Days({{ $channel->id }})" wire:loading.attr="disabled"
                            class="px-2.5 py-1 text-xs rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            30d
                        </button>
                        <button wire:click="syncShipments({{ $channel->id }})" wire:loading.attr="disabled"
                            class="px-2.5 py-1 text-xs rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Shipments
                        </button>
                    </div>
                </div>
            @empty
                <div class="px-6 py-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No active channels configured for sync.</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Enable channels in <a href="{{ route('filament.admin.resources.whatnot-channels.index') }}" class="text-blue-600 hover:underline">Whatnot Channels</a>.
                    </p>
                </div>
            @endforelse
        </div>

        {{-- ── Recent Sync Runs ─────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Sync Runs</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Channel</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Type</th>
                            <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Shows</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Orders</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Buyers</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Errors</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Duration</th>
                            <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Started</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                        @forelse ($this->recentSyncs as $sync)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $sync->channel?->name ?? 'All Channels' }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">
                                    {{ match($sync->type) {
                                        'last_30_days' => 'Last 30 days',
                                        'full' => 'Full',
                                        default => 'Incremental',
                                    } }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span @class([
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' => $sync->status === 'completed',
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'   => $sync->status === 'failed',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' => $sync->status === 'running',
                                    ])>
                                        {{ ucfirst($sync->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300">
                                    <span class="text-green-600 dark:text-green-400">+{{ $sync->shows_created }}</span>
                                    <span class="text-gray-400 mx-0.5">/</span>
                                    <span class="text-blue-600 dark:text-blue-400">~{{ $sync->shows_updated }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300">
                                    <span class="text-green-600 dark:text-green-400">+{{ $sync->orders_created }}</span>
                                    <span class="text-gray-400 mx-0.5">/</span>
                                    <span class="text-blue-600 dark:text-blue-400">~{{ $sync->orders_updated }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300">
                                    <span class="text-green-600 dark:text-green-400">+{{ $sync->buyers_created }}</span>
                                    <span class="text-gray-400 mx-0.5">/</span>
                                    <span class="text-blue-600 dark:text-blue-400">~{{ $sync->buyers_updated }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($sync->error_count > 0)
                                        <span class="text-red-600 dark:text-red-400 font-medium">{{ $sync->error_count }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-500 dark:text-gray-400">
                                    {{ $sync->duration_seconds ? $sync->duration_seconds . 's' : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-500 dark:text-gray-400">
                                    {{ $sync->started_at?->diffForHumans() }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No sync runs yet. Click "Sync All" above to start the first sync.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
<style>
.vx-runs { display: grid; grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); gap: .5rem; }
.vx-run { display: grid; gap: .2rem; text-align: left; padding: .65rem .8rem; border-radius: .75rem; border: 1px solid rgb(229 231 235); background: #fff; transition: border-color .15s, box-shadow .15s; }
.vx-run b { font-size: .8rem; color: rgb(17 24 39); }
.vx-run small { font-size: .7rem; line-height: 1.3; color: rgb(107 114 128); }
.vx-run:hover:not(:disabled) { border-color: #a78bfa; box-shadow: 0 0 0 3px rgb(124 58 237 / .08); }
.vx-run:disabled { opacity: .5; cursor: not-allowed; }
.vx-run.is-primary { background: linear-gradient(135deg, #7c3aed, #6d28d9); border-color: #6d28d9; }
.vx-run.is-primary b, .vx-run.is-primary small { color: #fff; }
.vx-run.is-primary small { opacity: .85; }
.dark .vx-run { background: rgb(17 24 39); border-color: rgb(55 65 81); }
.dark .vx-run b { color: rgb(243 244 246); }
.dark .vx-run.is-primary { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
.vxr-live { display: grid; gap: .9rem; padding: 1.1rem 1.2rem; border-radius: .9rem; border: 1px solid rgb(221 214 254); background: #fff; min-width: 0; }
.dark .vxr-live { border-color: rgb(91 33 182 / .6); background: rgb(17 24 39); }
.vxr-live-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
.vxr-live-head h2 { font-size: .9rem; font-weight: 700; }
.vxr-live-head h2 span { font-weight: 500; color: rgb(124 58 237); }
.vxr-live-head p { font-size: .75rem; color: rgb(107 114 128); margin-top: .15rem; }
.vxr-live-time { flex: none; font-size: .72rem; font-weight: 700; padding: .2rem .6rem; border-radius: 999px; background: rgb(245 243 255); color: #6d28d9; }
.dark .vxr-live-time { background: rgb(46 16 101 / .5); color: #ddd6fe; }
.vxr-live-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
.vxr-live-stats div { min-width: 0; padding: .55rem .7rem; border-radius: .7rem; background: rgb(249 250 251); }
.dark .vxr-live-stats div { background: rgb(31 41 55 / .6); }
.vxr-live-stats b { display: block; font-size: 1.15rem; line-height: 1.2; }
.vxr-live-stats small { display: block; font-size: .68rem; color: rgb(107 114 128); }
.vxr-live-stats .is-due b { color: #b45309; }
.vxr-live-stats .is-done b { color: #059669; }
.vxr-live-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: .9rem; }
@media (max-width: 1024px) { .vxr-live-grid { grid-template-columns: minmax(0, 1fr); } }
.vxr-live-col { display: grid; gap: .4rem; align-content: start; min-width: 0; }
.vxr-live-col h3 { font-size: .75rem; font-weight: 700; color: rgb(75 85 99); }
.vxr-live-col h3 span { font-weight: 500; color: rgb(156 163 175); }
.dark .vxr-live-col h3 { color: rgb(209 213 219); }
.vxr-live-log { height: 22rem; overflow-y: auto; margin: 0; padding: .7rem .8rem; border-radius: .7rem; background: #0f0a1f; color: #d1d5db; font-size: .7rem; line-height: 1.5; white-space: pre-wrap; overflow-wrap: anywhere; }
.vxr-live-log .is-bad { color: #fca5a5; }
.vxr-live-log .is-good { color: #86efac; }
.vxr-live-log .is-idle { color: #a78bfa; }
.vx-pulled { display: grid; gap: .45rem; max-height: 22rem; overflow-y: auto; padding-right: .15rem; }
.vx-pulled-card { display: grid; gap: .35rem; padding: .55rem .7rem; border-radius: .7rem; border: 1px solid rgb(229 231 235); background: #fff; animation: vx-pulled-in .35s ease both; }
.vx-pulled-card:hover { border-color: #c4b5fd; }
.dark .vx-pulled-card { border-color: rgb(55 65 81); background: rgb(31 41 55 / .5); }
@keyframes vx-pulled-in { from { opacity: 0; transform: translateY(-4px); } }
.vx-pulled-top { display: grid; min-width: 0; }
.vx-pulled-top b { font-size: .78rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.vx-pulled-top small, .vx-pulled-nums small { font-size: .66rem; color: rgb(107 114 128); }
.vx-pulled-nums { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .3rem; font-size: .76rem; font-weight: 700; }
.vx-pulled-nums small { display: block; font-weight: 500; }
.vx-pulled-tags { display: flex; flex-wrap: wrap; gap: .3rem; }
.vx-pulled-tags i { font-style: normal; font-size: .64rem; font-weight: 700; padding: .1rem .45rem; border-radius: 999px; background: rgb(243 244 246); color: rgb(75 85 99); }
.vx-pulled-tags i.ok { background: rgb(237 233 254); color: #6d28d9; }
.dark .vx-pulled-tags i { background: rgb(55 65 81); color: rgb(209 213 219); }
.dark .vx-pulled-tags i.ok { background: rgb(76 29 149 / .5); color: #ddd6fe; }
.vxr-live-empty { font-size: .76rem; color: rgb(107 114 128); padding: 1rem; text-align: center; border: 1px dashed rgb(221 214 254); border-radius: .7rem; }
@media (prefers-reduced-motion: reduce) { .vx-pulled-card { animation: none; } }
</style>
</x-filament-panels::page>

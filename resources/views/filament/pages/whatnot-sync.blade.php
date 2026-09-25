<x-filament-panels::page>
    <div class="space-y-6" wire:poll.3s>
        @php($job = $this->reportingJob)
        @php($locks = $this->pipelineStatus)
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2 rounded-xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-gray-900 p-5 space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ in_array($job['status'] ?? '', ['queued','running']) ? 'bg-blue-500 animate-pulse' : (($job['status'] ?? '') === 'blocked' ? 'bg-amber-500' : (($job['status'] ?? '') === 'failed' ? 'bg-red-500' : 'bg-emerald-500')) }}"></span>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Whatnot Scraper</h2>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ $job['phase'] ?? 'Ready for the next sync.' }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ in_array($job['status'] ?? '', ['queued','running']) ? 'bg-blue-100 text-blue-700' : (($job['status'] ?? '') === 'blocked' ? 'bg-amber-100 text-amber-700' : (($job['status'] ?? '') === 'failed' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700')) }}">
                        {{ strtoupper($job['status'] ?? 'IDLE') }}
                    </span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <button wire:click="runReporting('freshness')" class="px-3 py-2 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700">Freshness Sync</button>
                    <button wire:click="runReporting('analytics')" wire:confirm="Run missing/incomplete analytics for all channels from July 1 forward?" class="px-3 py-2 text-xs font-semibold rounded-lg bg-violet-600 text-white hover:bg-violet-700">Analytics Backfill</button>
                    <button wire:click="runReporting('full')" wire:confirm="Run the full Whatnot reconciliation from July 1 forward?" class="px-3 py-2 text-xs font-semibold rounded-lg bg-gray-900 text-white hover:bg-gray-800">Full Reconciliation</button>
                    <button wire:click="runReporting('test')" class="px-3 py-2 text-xs font-semibold rounded-lg border border-gray-200 dark:border-gray-700">Smoke Test</button>
                </div>
                @if(!empty($job['started_at']))
                    <p class="text-xs text-gray-400">Started {{ \Carbon\Carbon::parse($job['started_at'])->diffForHumans() }}</p>
                @endif
                @if(!empty($job['error']))
                    <div class="rounded-lg bg-red-50 dark:bg-red-950 p-3 text-xs text-red-700 dark:text-red-300">{{ $job['error'] }}</div>
                @endif
                @if(in_array($job['status'] ?? '', ['queued','running']))
                    <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full w-2/3 animate-pulse rounded-full bg-violet-500"></div></div>
                @endif
                @if(!empty($job['output']))
                    <details class="rounded-lg border border-gray-200 dark:border-gray-700"><summary class="cursor-pointer p-3 text-xs font-semibold">Run output</summary><pre class="max-h-72 overflow-auto whitespace-pre-wrap bg-gray-950 p-3 text-[11px] text-gray-100">{{ $job['output'] }}</pre></details>
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
                    <button wire:click="forceClearLocks" wire:confirm="Force stop the active Whatnot browser owner and release both browser and coordinator locks? Use this only when the scraper is stuck." class="px-3 py-2 text-xs font-semibold rounded-lg bg-red-600 text-white hover:bg-red-700">Stop & Clear</button>
                </div>
            </div>
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
</x-filament-panels::page>

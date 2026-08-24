<x-filament-panels::page>
    <div class="space-y-6">

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

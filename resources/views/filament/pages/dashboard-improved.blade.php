<x-filament-panels::page>
    @if(($roleMode ?? null) === 'admin')
        @include('filament.components.dashboard-reference')
    @else
    @if(! in_array(($roleMode ?? null), ['streamer', 'assistant'], true))<x-dashboard-shortcuts />@endif
    @php
        $pageMode = $roleMode ?? 'user';
    @endphp

    <div
        class="space-y-3 sm:space-y-5"
        @if($pageMode === 'streamer') data-vx-page="streamer-dashboard"
        @elseif($pageMode === 'fulfillment') data-vx-page="fulfillment-dashboard"
        @elseif($pageMode === 'admin') data-vx-page="admin-dashboard"
        @endif
    >
        @if($pageMode === 'streamer')
            <livewire:streamer-home :key="'streamer-home'" />
        @elseif($pageMode === 'assistant')
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-7">
                <div class="text-xs font-bold uppercase tracking-widest text-violet-600">Streamer assistant</div>
                <h1 class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">Your workspace</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Find upcoming shows, check the inventory you can access, and explore the show leaderboard.</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}" class="rounded-xl border border-gray-200 p-4 font-semibold text-violet-700 dark:border-gray-700 dark:text-violet-300">View inventory →</a>
                    <a href="{{ \App\Filament\Pages\Timekeeping::getUrl() }}" class="rounded-xl border border-gray-200 p-4 font-semibold text-violet-700 dark:border-gray-700 dark:text-violet-300">My hours &amp; shows →</a>
                    <div class="rounded-xl border border-gray-200 p-4 font-semibold text-violet-700 dark:border-gray-700 dark:text-violet-300">Show leaderboard ↓</div>
                </div>
            </section>
            @if (\App\Livewire\StreamerLeaderboard::canView())
                <livewire:streamer-leaderboard :arena-compact="true" :key="'assistant-home-leaderboard'" />
            @endif
        @elseif($pageMode === 'fulfillment')
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Fulfillment Operations</div>
                    <h1 class="mt-1 text-xl font-semibold leading-tight text-gray-950 dark:text-white sm:text-2xl">Shipping work that needs attention</h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Work by show first. Open the show, then handle its Whatnot shipments and packing lines.</p>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">
                    @foreach ([
                        ['Shows to Work', $showsToFulfill ?? 0],
                        ['Open Shipments', $openShipments ?? 0],
                        ['Delivered Today', $deliveredToday ?? 0],
                        ['Unassigned', $unassignedShows ?? 0],
                    ] as [$label, $value])
                        <div class="bg-white px-3 py-3 dark:bg-gray-900 sm:p-4">
                            <div class="truncate text-[10px] font-medium uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">{{ $label }}</div>
                            <div class="mt-0.5 text-xl font-semibold leading-none text-gray-950 dark:text-white sm:mt-1 sm:text-2xl">{{ number_format((float)$value) }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900 sm:p-4">
                <div class="grid gap-1 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:grid-cols-3 sm:gap-3">
                    <span><strong class="text-gray-700 dark:text-gray-200">1.</strong> Open a show with work.</span>
                    <span><strong class="text-gray-700 dark:text-gray-200">2.</strong> Pack and verify its shipment lines.</span>
                    <span><strong class="text-gray-700 dark:text-gray-200">3.</strong> Update shipment status/tracking as work moves.</span>
                </div>
            </section>

            <section class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/30 sm:p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm font-semibold text-blue-900 dark:text-blue-100">Ready to work the queue?</div>
                        <p class="mt-0.5 text-xs leading-5 text-blue-700 dark:text-blue-300">The Fulfillment Center keeps the list show-first so mobile users do not have to scan a giant shipment table.</p>
                    </div>
                    <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">Open Fulfillment Center</a>
                </div>
            </section>
        @elseif($pageMode === 'admin')
            <section class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Inventory Health</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Current stock status at a glance.</p>
                        </div>
                        <a href="{{ App\Filament\Resources\InventoryItemResource::getUrl('index') }}" class="text-xs font-semibold text-primary-600">View Inventory →</a>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/20"><div class="text-xs text-gray-500">In Stock</div><div class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($inventoryHealth['in'] ?? 0) }}</div></div>
                        <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/20"><div class="text-xs text-gray-500">Low Stock</div><div class="mt-1 text-2xl font-bold text-amber-600">{{ number_format($inventoryHealth['low'] ?? 0) }}</div></div>
                        <div class="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/20"><div class="text-xs text-gray-500">Out of Stock</div><div class="mt-1 text-2xl font-bold text-rose-600">{{ number_format($inventoryHealth['out'] ?? 0) }}</div></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                    <div class="flex items-center justify-between"><div><h2 class="text-sm font-semibold text-gray-950 dark:text-white">Recent Inventory Activity</h2><p class="mt-0.5 text-xs text-gray-500">Latest stock changes.</p></div></div>
                    <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse(($recentInventoryActivity ?? []) as $activity)
                            <div class="flex items-center gap-3 py-2.5">
                                <span class="w-14 shrink-0 text-[11px] text-gray-400">{{ $activity['time'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-800 dark:text-gray-100">{{ $activity['name'] }}</span>
                                @php $moved = $activity['transfer'] ?? false; $q = (float) $activity['qty']; @endphp<span class="text-xs font-semibold {{ $moved || $q == 0 ? 'text-gray-500' : ($q > 0 ? 'text-emerald-600' : 'text-rose-600') }}">{{ $moved ? 'Moved ' : ($q > 0 ? '+' : ($q < 0 ? '−' : '')) }}{{ rtrim(rtrim(number_format(abs($q), 2), '0'), '.') }}</span>
                                <span class="hidden text-[11px] text-gray-400 sm:block">{{ $activity['type'] }}</span>
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-gray-500">No recent inventory activity.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif

        @if($pageMode !== 'streamer')
        <div class="min-w-0">
            <x-filament-widgets::widgets :widgets="$this->getWidgets()" :columns="$this->getColumns()" />
        </div>
        @endif
    </div>
    @endif
</x-filament-panels::page>

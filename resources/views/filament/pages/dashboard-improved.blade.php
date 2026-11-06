<x-filament-panels::page>
    @if(($roleMode ?? null) === 'admin')
        @include('filament.components.dashboard-reference')
    @else
    @if(!in_array($roleMode ?? null, ['streamer', 'assistant'], true))<x-dashboard-shortcuts />@endif
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
        @if($pageMode === 'fulfillment')
            <div data-vx-workspace="fulfillment" class="space-y-4">
                <section class="overflow-hidden rounded-2xl border border-violet-200 bg-white shadow-sm dark:border-violet-900 dark:bg-gray-900">
                    <div class="flex flex-wrap items-start justify-between gap-4 bg-gradient-to-r from-violet-50 to-white p-5 dark:from-violet-950/40 dark:to-gray-900 sm:p-6">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-widest text-violet-700 dark:text-violet-300">Vortex Ops / Fulfillment Center</p>
                            <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">{{ $fulfillmentIsManager ?? false ? 'Fulfillment Command Center' : 'My Packing Workspace' }}</h1>
                            <p class="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-300">{{ $fulfillmentIsManager ?? false ? 'Manage stations, packers, exceptions and shipments across your operation.' : 'Your assigned shows, packing stations and shipment tasks in one place.' }}</p>
                        </div>
                        <span class="rounded-full border border-violet-200 bg-white px-3 py-1.5 text-xs font-bold text-violet-800 dark:border-violet-800 dark:bg-violet-950 dark:text-violet-200">{{ $fulfillmentIsManager ?? false ? 'Fulfillment admin' : 'Fulfillment team' }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-3 xl:grid-cols-6">
                        @foreach([
                            ['Shows to work', $showsToFulfill ?? 0],
                            ['Active stations', $fulfillmentActiveStations ?? 0],
                            ['Buyers waiting', $fulfillmentPendingBuyers ?? 0],
                            ['Open shipments', $openShipments ?? 0],
                            ['Open issues', $fulfillmentOpenIssues ?? 0],
                            ['Delivered today', $deliveredToday ?? 0],
                        ] as [$label, $value])
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
                                <p class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $label }}</p>
                                <p class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format((int) $value) }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('index') }}" class="group rounded-2xl border border-violet-200 bg-violet-600 p-5 text-white shadow-sm transition hover:bg-violet-700 focus-visible:outline focus-visible:outline-4 focus-visible:outline-violet-300">
                        <p class="text-xs font-semibold uppercase tracking-wider text-violet-100">Primary workspace</p>
                        <h2 class="mt-2 text-lg font-bold">Open my fulfillment queue</h2>
                        <p class="mt-1 text-sm text-violet-100">Select a show, open its station board and verify packing.</p>
                        <span class="mt-4 inline-block text-sm font-semibold">View assigned shows →</span>
                    </a>
                    @if($fulfillmentIsManager ?? false)
                        <a href="{{ \App\Filament\Pages\FulfillmentOperations::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-300 dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-xs font-semibold uppercase tracking-wider text-violet-700 dark:text-violet-300">Manager tools</p>
                            <h2 class="mt-2 text-lg font-bold text-gray-950 dark:text-white">Live operations board</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Station occupancy, staff activity and issue resolution.</p>
                            <span class="mt-4 inline-block text-sm font-semibold text-violet-700 dark:text-violet-300">Open manager board →</span>
                        </a>
                    @endif
                    <a href="{{ \App\Filament\Pages\FulfillmentReports::getUrl(panel:'admin') }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-300 dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-xs font-semibold uppercase tracking-wider text-violet-700 dark:text-violet-300">Performance reports</p>
                        <h2 class="mt-2 text-lg font-bold text-gray-950 dark:text-white">{{ $fulfillmentIsManager ?? false ? 'Team reports' : 'My reports' }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Packing activity, verified scans, reported issues and recent history.</p>
                        <span class="mt-4 inline-block text-sm font-semibold text-violet-700 dark:text-violet-300">View reports →</span>
                    </a>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Packing workflow</p>
                        <h2 class="mt-2 text-lg font-bold text-gray-950 dark:text-white">Verify before shipping</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">1. Select a show · 2. Scan buyer items · 3. Resolve holds · 4. Confirm packing</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-gray-950 dark:text-white">Packing in progress</h2>
                        <span class="text-xs text-gray-500">Shows visible to your role</span>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse(($fulfillmentRecentRuns ?? []) as $run)
                            <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $run->show_id]) }}" class="flex min-h-14 items-center justify-between gap-3 rounded-lg px-2 py-3 hover:bg-violet-50 dark:hover:bg-gray-800">
                                <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $run->title }}</p><p class="text-xs text-gray-500">{{ $run->station_name }} · Started {{ \Illuminate\Support\Carbon::parse($run->started_at)->diffForHumans() }}</p></div>
                                <span class="shrink-0 text-sm font-semibold text-violet-700 dark:text-violet-300">Open →</span>
                            </a>
                        @empty
                            <p class="py-6 text-sm text-gray-500">No packing stations are currently active in your queue.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif
        @if($pageMode === 'assistant')
            @include('filament.components.assistant-dashboard')
        @elseif($pageMode === 'streamer')
            <livewire:streamer-home :key="'streamer-home'" />
        @elseif($pageMode === 'fulfillment')
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="p-4 sm:p-6">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Fulfillment Operations</div>
                    <h1 class="mt-1 text-xl font-semibold leading-tight text-gray-950 dark:text-white sm:text-2xl">Shipping work that needs attention</h1>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Work by show first. Open the show, then handle its Whatnot shipments and packing lines.</p>
                </div>

                <div class="vxr-stats border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px">
                    @foreach ([
                        ['Shows to Work', $showsToFulfill ?? 0, 'heroicon-o-video-camera', 'accent'],
                        ['Open Shipments', $openShipments ?? 0, 'heroicon-o-truck', 'info'],
                        ['Delivered Today', $deliveredToday ?? 0, 'heroicon-o-check-circle', 'ok'],
                        ['Unassigned', $unassignedShows ?? 0, 'heroicon-o-user-minus', 'warn'],
                    ] as [$label, $value, $icon, $tone])
                        <x-vxr.stat :label="$label" :value="number_format((float) $value)" :icon="$icon" :tone="$tone" compact />
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
                    <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('index') }}" class="vxr-btn vxr-btn--primary">Open Fulfillment Center</a>
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
                                <span class="w-14 shrink-0 text-[11px] text-gray-500">{{ $activity['time'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-800 dark:text-gray-100">{{ $activity['name'] }}</span>
                                @php $moved = $activity['transfer'] ?? false; $q = (float) $activity['qty']; @endphp<span class="text-xs font-semibold {{ $moved || $q == 0 ? 'text-gray-500' : ($q > 0 ? 'text-emerald-600' : 'text-rose-600') }}">{{ $moved ? 'Moved ' : ($q > 0 ? '+' : ($q < 0 ? '−' : '')) }}{{ rtrim(rtrim(number_format(abs($q), 2), '0'), '.') }}</span>
                                <span class="hidden text-[11px] text-gray-500 sm:block">{{ $activity['type'] }}</span>
                            </div>
                        @empty
                            <div class="py-5 text-center text-xs text-gray-500">No recent inventory activity.</div>
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

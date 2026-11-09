@php
    $overview = $dashboardOverview;
    $stockTotal = max(0, (int) ($inventoryHealth['in'] ?? 0) + (int) ($inventoryHealth['out'] ?? 0));
    $low = min((int) ($inventoryHealth['low'] ?? 0), (int) ($inventoryHealth['in'] ?? 0));
    $healthy = max(0, (int) ($inventoryHealth['in'] ?? 0) - $low);
    $green = $stockTotal ? $healthy / $stockTotal * 100 : 0;
    $amber = $stockTotal ? $low / $stockTotal * 100 : 0;
    $shortcuts = collect(\App\Support\DashboardShortcuts::forCurrentUser());
    $guide = $shortcuts->firstWhere('label', 'Inventory guide');
@endphp
<div class="vx-dashboard-reference space-y-4" data-vx-page="admin-dashboard">
    <style>
        /* Compact, content-sized operations workspace. Preserve Filament top navigation. */
        .vx-dashboard-reference { --vx-dashboard-border: rgba(139,92,246,.19); }
        .vx-dashboard-reference .vx-dashboard-heading { background:linear-gradient(105deg,rgba(245,243,255,.9),rgba(255,255,255,.45)); border:1px solid var(--vx-dashboard-border); padding:1rem 1.1rem; }
        .dark .vx-dashboard-reference .vx-dashboard-heading { background:linear-gradient(105deg,rgba(49,26,85,.65),rgba(17,24,39,.3)); }
        .vx-dashboard-reference .vx-dashboard-main-grid > *, .vx-dashboard-reference .vx-dashboard-secondary-grid > * { align-self:start; }
        .vx-dashboard-reference .vx-dashboard-main-grid .fi-wi, .vx-dashboard-reference .vx-dashboard-secondary-grid .fi-wi { align-self:start; }
        .vx-dashboard-reference .fi-wi-stats-overview-stat { background-image:linear-gradient(135deg,rgba(250,245,255,.75),transparent 70%); border-color:var(--vx-dashboard-border); }
        .dark .vx-dashboard-reference .fi-wi-stats-overview-stat { background-image:linear-gradient(135deg,rgba(91,33,182,.13),transparent 70%); }
        .vx-dashboard-reference .fi-wi-stats-overview-stat-value { font-size:clamp(1.1rem,1.5vw,1.5rem)!important; font-weight:800; font-variant-numeric:tabular-nums; }
        .vx-dashboard-reference .vx-dashboard-action { transition:background-color .15s,border-color .15s,box-shadow .15s; }
        .vx-dashboard-reference .vx-dashboard-action:hover { box-shadow:0 4px 14px rgba(109,40,217,.13); }
        .vx-dashboard-reference .vx-donut { box-shadow:0 0 0 1px rgba(139,92,246,.12); }
        @media(max-width:640px){.vx-dashboard-reference .vx-dashboard-heading{padding:.85rem}.vx-dashboard-reference .vx-dashboard-action{flex:1 1 auto;justify-content:center}}
        @media(prefers-reduced-motion:reduce){.vx-dashboard-reference .vx-dashboard-action{transition:none}}

        .vx-dashboard-reference .vx-dashboard-main-grid > *, .vx-dashboard-reference .vx-dashboard-secondary-grid > * { min-width:0; }
        .vx-dashboard-reference .vx-dashboard-main-grid .fi-wi, .vx-dashboard-reference .vx-dashboard-main-grid .fi-section { height:auto; }
        .vx-dashboard-reference .vx-dashboard-action:first-child { background:linear-gradient(135deg,#7c3aed,#a21caf);color:white;border-color:#a855f7; }
        .vx-dashboard-reference .fi-wi-stats-overview { gap:.75rem; }
        .vx-dashboard-reference .fi-wi-stats-overview-stat { border:1px solid rgba(139,92,246,.2); }
        .dark .vx-dashboard-reference .fi-wi-stats-overview-stat { border-color:rgba(167,139,250,.25); }
        .vx-dashboard-reference .vx-dashboard-secondary-grid .vx-today { height:auto; }
        @media(max-width:1279px){.vx-dashboard-reference .vx-dashboard-main-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.vx-dashboard-reference .vx-dashboard-main-grid> :first-child { grid-column:1/-1; }.vx-dashboard-reference .vx-dashboard-secondary-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.vx-dashboard-reference .vx-dashboard-secondary-grid .vx-today { grid-column:1/-1; }}
        @media(max-width:767px){.vx-dashboard-reference .vx-dashboard-main-grid,.vx-dashboard-reference .vx-dashboard-secondary-grid { grid-template-columns:minmax(0,1fr); }.vx-dashboard-reference .vx-dashboard-main-grid> :first-child,.vx-dashboard-reference .vx-dashboard-secondary-grid .vx-today { grid-column:auto; }}

        .vx-dashboard-reference .fi-wi-stats-overview-stat{border-radius:1rem;box-shadow:0 3px 12px rgba(30,41,59,.04);min-width:0}
        .vx-dashboard-reference .fi-wi-stats-overview-stat-value{font-size:clamp(1rem,1.3vw,1.2rem)!important;line-height:1.25;white-space:normal!important;overflow:visible!important;text-overflow:clip!important;overflow-wrap:anywhere}
        .vx-dashboard-reference .fi-wi-stats-overview-stat-label{font-size:.75rem}
        .vx-dashboard-reference .fi-section{border-radius:1rem}
        .vx-dashboard-reference .fi-wi{min-width:0}
        .vx-dashboard-reference .vx-interactive-tile{transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease}
        .vx-dashboard-reference .vx-interactive-tile:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(40,32,90,.09);border-color:rgb(196 181 253)}
        .vx-dashboard-reference .vx-interactive-tile:focus-within{outline:2px solid #7c3aed;outline-offset:2px}
        .vx-dashboard-reference details.vx-interactive-tile>summary{list-style:none}
        .vx-dashboard-reference details.vx-interactive-tile>summary::-webkit-details-marker{display:none}
        .vx-dashboard-reference details.vx-interactive-tile[open] .vx-expand-chevron{transform:rotate(180deg)}
        .vx-dashboard-reference .vx-expand-chevron{transition:transform .16s ease}
        @media(prefers-reduced-motion:reduce){.vx-dashboard-reference .vx-interactive-tile,.vx-dashboard-reference .vx-expand-chevron{transition:none!important}.vx-dashboard-reference .vx-interactive-tile:hover{transform:none}}

        
        .vx-dashboard-reference .vx-donut{background:conic-gradient(#10b981 0 var(--green),#f59e0b var(--green) var(--amber),#fb7185 var(--amber) 100%)}
        .vx-dashboard-reference .vx-middle .fi-wi,.vx-dashboard-reference .vx-middle .fi-section{height:auto}
        /* The widget wrapper carries a navy background in dark mode and is
           stretched to the row's height, so it showed around the card. Let
           the card fill the row instead, matching its neighbours. */
        .vx-dashboard-reference .vx-middle .fi-wi{background:transparent!important}
        .vx-dashboard-reference .vx-middle .fi-wi-widget,.vx-dashboard-reference .vx-middle .fi-wi-widget>section{height:100%}
        @media(max-width:640px){.vx-dashboard-reference .fi-wi-stats-overview-stat-value{font-size:1.25rem}.vx-dashboard-reference .fi-wi-stats-overview-stat{padding:.8rem}.vx-dashboard-reference .vx-hero-art{display:none}}
    </style>
    <header class="vx-dashboard-heading flex flex-wrap items-center justify-between gap-4 rounded-2xl px-1 py-2">
        <div class="min-w-0">
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-950 dark:text-white sm:text-3xl">Operations Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Manage inventory, shows, and performance across your Vortex channels.</p>
        </div>
        <nav class="flex flex-wrap items-center gap-2" aria-label="Dashboard quick actions">
            @foreach($shortcuts->reject(fn ($link) => $link['label'] === 'Inventory guide') as $link)
                <a href="{{ $link['url'] }}" class="vx-dashboard-action inline-flex min-h-10 items-center gap-2 rounded-xl border border-violet-200 bg-white px-3 text-xs font-semibold text-violet-900 shadow-sm hover:border-violet-400 hover:bg-violet-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-500 dark:border-violet-800 dark:bg-gray-900 dark:text-violet-100 dark:hover:bg-violet-950">
                    <x-dynamic-component :component="$link['icon']" class="h-4 w-4" />{{ $link['label'] }}
                </a>
            @endforeach
            @if($guide)<a href="{{ $guide['url'] }}" class="vx-dashboard-action inline-flex min-h-10 items-center gap-2 rounded-xl border border-violet-200 bg-white px-3 text-xs font-semibold text-violet-900 shadow-sm hover:bg-violet-50 dark:border-violet-800 dark:bg-gray-900 dark:text-violet-100"><x-heroicon-o-book-open class="h-4 w-4" />Inventory guide</a>@endif
        </nav>
    </header>

    <x-filament-widgets::widgets :widgets="[\App\Filament\Widgets\DashboardShowsKpiWidget::class]" :columns="1" />

    <div class="vx-dashboard-main-grid grid items-start gap-3 xl:grid-cols-[minmax(0,1.55fr)_minmax(0,.95fr)_minmax(0,.95fr)]">
        <div class="min-w-0">        @include('filament.components.dashboard-channel-performance')
        </div>
        <section class="vx-interactive-tile min-w-0 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-2"><div><h2 class="text-base font-bold text-gray-950 dark:text-white">Inventory Health</h2><p class="mt-1 text-xs text-gray-500">Current stock status at a glance.</p></div>@if(\App\Filament\Resources\InventoryItemResource::canAccess())<a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl() }}" class="text-xs font-semibold text-primary-600">View inventory →</a>@endif</div>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-5">
                <div class="vx-donut relative flex h-36 w-36 shrink-0 items-center justify-center rounded-full" style="--green:{{ $green }}%;--amber:{{ $green + $amber }}%" role="img" aria-label="{{ $healthy }} in stock above reorder level, {{ $low }} low stock, {{ $inventoryHealth['out'] ?? 0 }} out of stock">
                    <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white dark:bg-gray-900"><strong class="text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($stockTotal) }}</strong><span class="mt-1 text-[10px] text-gray-500">Total items</span></div>
                </div>
                <div class="min-w-[130px] flex-1 space-y-3 text-xs">
                    @foreach([['In stock', $healthy, '#10b981'], ['Low stock', $low, '#f59e0b'], ['Out of stock', $inventoryHealth['out'] ?? 0, '#fb7185']] as [$label,$count,$color])
                    <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded" style="background:{{ $color }}"></span><span class="flex-1 text-gray-600 dark:text-gray-300">{{ $label }}</span><strong class="tabular-nums text-gray-950 dark:text-white">{{ number_format($count) }}</strong><span class="w-8 text-right text-gray-500">{{ $stockTotal ? round($count/$stockTotal*100) : 0 }}%</span></div>
                    @endforeach
                </div>
            </div>
            @if(!$stockTotal)<p class="mt-4 text-xs text-gray-500">No active inventory items yet.</p>@endif
        </section>
        <div class="min-w-0"><x-filament-widgets::widgets :widgets="[\App\Filament\Widgets\DashboardNeedsAttentionWidget::class]" :columns="1" /></div>
    </div>
    <div class="vx-dashboard-secondary-grid grid items-start gap-3 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_minmax(220px,.7fr)]">
        <div class="min-w-0"><x-filament-widgets::widgets :widgets="[\App\Filament\Widgets\UpcomingShowsWidget::make(['limit'=>3])]" :columns="1" /></div>
        <section class="vx-interactive-tile min-w-0 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-2"><div><h2 class="text-base font-bold text-gray-950 dark:text-white">Recent Inventory Activity</h2><p class="mt-1 text-xs text-gray-500">Latest stock changes.</p></div>@if(\App\Filament\Resources\InventoryMovementResource::canAccess())<a href="{{ \App\Filament\Resources\InventoryMovementResource::getUrl() }}" class="shrink-0 text-xs font-semibold text-primary-600">View all →</a>@endif</div>
            <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @forelse(($recentInventoryActivity ?? []) as $activity)
                <div class="flex items-center gap-3 py-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950"><x-heroicon-o-cube class="h-5 w-5" /></span><div class="min-w-0 flex-1"><span class="text-[10px] text-gray-500">{{ $activity['time'] }}</span><div class="truncate text-xs font-semibold text-gray-900 dark:text-white">{{ $activity['name'] }}</div></div>@php $moved = $activity['transfer'] ?? false; $q = (float) $activity['qty']; @endphp<span class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold {{ $moved || $q == 0 ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' : ($q > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300') }}">{{ $moved ? 'Moved ' : ($q > 0 ? '+' : ($q < 0 ? '−' : '')) }}{{ rtrim(rtrim(number_format(abs($q), 2), '0'), '.') }}</span><span class="hidden max-w-16 text-[10px] text-gray-500 xl:block">{{ $activity['type'] }}</span></div>
                @empty<p class="py-5 text-center text-xs text-gray-500">No recent inventory activity.</p>@endforelse
            </div>
        </section>
    <aside class="vx-today rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900" aria-label="Today at a glance">
                <div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-300"><x-heroicon-o-calendar-days class="h-5 w-5" /></span><div><div class="text-xs font-medium text-gray-500 dark:text-gray-400">Today at a glance</div><div class="text-base font-bold text-gray-950 dark:text-white">{{ $overview['date'] }}</div></div></div>
                <div class="mt-3 space-y-0.5">
                    @foreach($overview['today'] as $row)
                    <a href="{{ $row['url'] }}" class="flex min-h-8 items-center gap-3 rounded-lg px-1.5 text-sm text-gray-700 hover:bg-primary-50 dark:text-gray-200 dark:hover:bg-white/5"><strong class="w-7 text-right text-base tabular-nums text-gray-950 dark:text-white">{{ number_format($row['count']) }}</strong><span class="flex-1">@if($row['live'] ?? false)<span class="vx-live-dot" aria-hidden="true"></span> @endif{{ $row['label'] }}</span><x-heroicon-o-chevron-right class="h-3.5 w-3.5 text-gray-400" /></a>
                    @endforeach
                </div>
    </aside>
    </div>
    @if(\App\Filament\Resources\WhatnotLedgerResource::canAccess())
        <section class="vx-interactive-tile rounded-2xl border border-violet-200 bg-violet-50 p-4 shadow-sm dark:border-violet-800 dark:bg-violet-950/30" aria-label="Ledger tip review shortcut">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-950 dark:text-white">Tip attribution review</h2>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">Review imported tips without an approved business assignment. No automatic payroll changes.</p>
                </div>
                <a class="inline-flex min-h-10 items-center rounded-xl bg-violet-700 px-4 text-xs font-bold text-white hover:bg-violet-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-500" href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['review' => 'tips_needs_business']) }}">Review tips →</a>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                <a href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['review' => 'tips_needs_business']) }}" class="rounded-xl border border-violet-200 bg-white p-3 text-xs font-semibold text-gray-800 hover:border-violet-400 dark:border-violet-800 dark:bg-gray-900 dark:text-gray-100">Unverified business tips <span class="block mt-1 text-violet-600 dark:text-violet-300">Review assignments →</span></a>
                <a href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('index', ['review' => 'tips_missing_streamer']) }}" class="rounded-xl border border-violet-200 bg-white p-3 text-xs font-semibold text-gray-800 hover:border-violet-400 dark:border-violet-800 dark:bg-gray-900 dark:text-gray-100">Tips missing streamer <span class="block mt-1 text-violet-600 dark:text-violet-300">Review show matches →</span></a>
                <a href="{{ \App\Filament\Resources\WhatnotLedgerResource::getUrl('tips') }}" class="rounded-xl border border-violet-200 bg-white p-3 text-xs font-semibold text-gray-800 hover:border-violet-400 dark:border-violet-800 dark:bg-gray-900 dark:text-gray-100">Streamer tip totals <span class="block mt-1 text-violet-600 dark:text-violet-300">Open breakdown →</span></a>
            </div>
        </section>
    @endif

    @if (\App\Livewire\StreamerLeaderboard::canView())@vite('resources/js/colosseum.js')<livewire:streamer-leaderboard :arena-compact="true" :key="'admin-dashboard-leaderboard'" />@endif
    <details class="vx-interactive-tile rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><summary class="flex cursor-pointer items-center justify-between gap-2 text-sm font-semibold text-gray-900 dark:text-white">Recent shows <x-heroicon-o-chevron-down class="vx-expand-chevron h-4 w-4 text-gray-500" /></summary><div class="mt-4"><x-filament-widgets::widgets :widgets="[\App\Filament\Widgets\RecentShowsWidget::class]" :columns="1" /></div></details>
</div>

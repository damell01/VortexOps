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
        .vx-dashboard-reference .fi-wi-stats-overview-stat{border-radius:1rem;box-shadow:0 3px 12px rgba(30,41,59,.04);min-width:0}
        .vx-dashboard-reference .fi-wi-stats-overview-stat-value{font-size:clamp(1.1rem,1.7vw,1.5rem);line-height:1.25;overflow-wrap:anywhere}
        .vx-dashboard-reference .fi-wi-stats-overview-stat-label{font-size:.75rem}
        .vx-dashboard-reference .fi-section{border-radius:1rem}
        .vx-dashboard-reference .fi-wi{min-width:0}
        .vx-dashboard-reference .vx-dashboard-hero{background:radial-gradient(ellipse at 58% 30%,rgba(109,40,217,.45),transparent 55%),linear-gradient(110deg,#080d22,#121a3b 55%,#21124d)}
        .vx-dashboard-reference .vx-donut{background:conic-gradient(#10b981 0 var(--green),#f59e0b var(--green) var(--amber),#fb7185 var(--amber) 100%)}
        .vx-dashboard-reference .vx-middle .fi-wi,.vx-dashboard-reference .vx-middle .fi-section{height:100%}
        @media(max-width:640px){.vx-dashboard-reference .fi-wi-stats-overview-stat-value{font-size:1.25rem}.vx-dashboard-reference .fi-wi-stats-overview-stat{padding:.8rem}.vx-dashboard-reference .vx-hero-art{display:none}}
    </style>
    <section class="vx-dashboard-hero relative overflow-hidden rounded-2xl p-5 text-white shadow-lg sm:p-6">
        <div class="vx-hero-art pointer-events-none absolute inset-y-0 right-[23%] hidden w-[300px] opacity-35 lg:block" aria-hidden="true">
            <img src="{{ asset('images/hit-slab.webp') }}" alt="" class="absolute -top-6 left-12 w-36 -rotate-12 rounded-lg" />
            <img src="{{ asset('images/pack-cards.webp') }}" alt="" class="absolute bottom-0 right-0 w-44 rotate-12" />
        </div>
        <div class="relative grid gap-5 lg:grid-cols-[1fr_260px]">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $overview['greeting'] }}, {{ $overview['name'] }} <span aria-hidden="true">👋</span></h1>
                <p class="mt-2 text-sm text-indigo-100">Here's what's happening with Vortex Ops today.</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach($shortcuts->reject(fn ($link) => $link['label'] === 'Inventory guide') as $link)
                    <a href="{{ $link['url'] }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-xs font-semibold transition {{ $loop->first ? 'border-violet-400 bg-violet-600 shadow-lg shadow-violet-900/40 hover:bg-violet-500' : 'border-white/20 bg-white/5 hover:bg-white/15' }}"><x-dynamic-component :component="$link['icon']" class="h-4 w-4" />{{ $link['label'] }}</a>
                    @endforeach
                </div>
                @if($guide)<a href="{{ $guide['url'] }}" class="mt-3 inline-flex min-h-9 items-center gap-1.5 text-xs font-medium text-indigo-100 hover:text-white"><x-heroicon-o-book-open class="h-4 w-4" />Inventory guide →</a>@endif
            </div>
            <aside class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm" aria-label="Today at a glance">
                <div class="flex items-center gap-3"><x-heroicon-o-calendar-days class="h-6 w-6 text-indigo-200" /><div><div class="text-xs font-medium text-indigo-100">Today at a glance</div><div class="mt-1 text-sm font-bold">{{ $overview['date'] }}</div></div></div>
                <div class="mt-3 space-y-1">
                    @foreach($overview['today'] as $row)
                    <a href="{{ $row['url'] }}" class="flex min-h-8 items-center gap-3 rounded-lg px-1 text-xs hover:bg-white/10"><strong class="w-7 text-right text-base tabular-nums">{{ number_format($row['count']) }}</strong><span class="flex-1 text-indigo-100">{{ $row['label'] }}</span><x-heroicon-o-chevron-right class="h-3 w-3" /></a>
                    @endforeach
                </div>
            </aside>
        </div>
    </section>

    <x-filament-widgets::widgets :widgets="[\App\Filament\Widgets\DashboardShowsKpiWidget::class]" :columns="1" />

    <div class="vx-middle grid gap-4 lg:grid-cols-3">
        <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-2"><div><h2 class="text-base font-bold text-gray-950 dark:text-white">Inventory Health</h2><p class="mt-1 text-xs text-gray-500">Current stock status at a glance.</p></div><a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl() }}" class="text-xs font-semibold text-primary-600">View inventory →</a></div>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-5">
                <div class="vx-donut relative flex h-36 w-36 shrink-0 items-center justify-center rounded-full" style="--green:{{ $green }}%;--amber:{{ $green + $amber }}%" role="img" aria-label="{{ $healthy }} in stock above reorder level, {{ $low }} low stock, {{ $inventoryHealth['out'] ?? 0 }} out of stock">
                    <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white dark:bg-gray-900"><strong class="text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($stockTotal) }}</strong><span class="mt-1 text-[10px] text-gray-500">Total items</span></div>
                </div>
                <div class="min-w-[130px] flex-1 space-y-3 text-xs">
                    @foreach([['In stock', $healthy, '#10b981'], ['Low stock', $low, '#f59e0b'], ['Out of stock', $inventoryHealth['out'] ?? 0, '#fb7185']] as [$label,$count,$color])
                    <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded" style="background:{{ $color }}"></span><span class="flex-1 text-gray-600 dark:text-gray-300">{{ $label }}</span><strong class="tabular-nums text-gray-950 dark:text-white">{{ number_format($count) }}</strong><span class="w-8 text-right text-gray-400">{{ $stockTotal ? round($count/$stockTotal*100) : 0 }}%</span></div>
                    @endforeach
                </div>
            </div>
            @if(!$stockTotal)<p class="mt-4 text-xs text-gray-500">No active inventory items yet.</p>@endif
        </section>
        <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-2"><div><h2 class="text-base font-bold text-gray-950 dark:text-white">Recent Inventory Activity</h2><p class="mt-1 text-xs text-gray-500">Latest stock changes.</p></div>@if(\App\Filament\Resources\InventoryMovementResource::canAccess())<a href="{{ \App\Filament\Resources\InventoryMovementResource::getUrl() }}" class="shrink-0 text-xs font-semibold text-primary-600">View all →</a>@endif</div>
            <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @forelse(($recentInventoryActivity ?? []) as $activity)
                <div class="flex items-center gap-3 py-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950"><x-heroicon-o-cube class="h-5 w-5" /></span><div class="min-w-0 flex-1"><span class="text-[10px] text-gray-400">{{ $activity['time'] }}</span><div class="truncate text-xs font-semibold text-gray-900 dark:text-white">{{ $activity['name'] }}</div></div><span class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold {{ $activity['qty'] >= 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300' }}">{{ $activity['qty'] >= 0 ? '+' : '' }}{{ number_format($activity['qty'],2) }}</span><span class="hidden max-w-16 text-[10px] text-gray-400 xl:block">{{ $activity['type'] }}</span></div>
                @empty<p class="py-8 text-center text-xs text-gray-500">No recent inventory activity.</p>@endforelse
            </div>
        </section>
        <div class="min-w-0"><x-filament-widgets::widgets :widgets="[\App\Filament\Widgets\DashboardNeedsAttentionWidget::class]" :columns="1" /></div>
    </div>

    <div class="grid items-start gap-4 lg:grid-cols-[.9fr_1.1fr]">
        <div class="min-w-0"><x-filament-widgets::widgets :widgets="[\App\Filament\Widgets\UpcomingShowsWidget::make(['limit'=>3])]" :columns="1" /></div>
        @include('filament.components.dashboard-channel-performance')
    </div>
    <details class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">Recent shows</summary><div class="mt-4"><x-filament-widgets::widgets :widgets="[\App\Filament\Widgets\RecentShowsWidget::class]" :columns="1" /></div></details>
</div>

<x-filament-panels::page>
    @php
        $kpis = $this->kpis;
        $rows = $this->rows;
        $views = [
            'best_margin' => 'Best Margin',
            'reorder'     => 'Reorder Soon',
            'all'         => 'All Products',
            'dead_stock'  => 'Dead Stock (' . \App\Filament\Pages\ProductInsights::DEAD_DAYS . 'd+)',
            'never_sold'  => 'Never Sold',
        ];
        $topProducts = $rows->sortByDesc('revenue')->take(6);
        $maxProductRevenue = max(1, (float) $topProducts->max('revenue'));
        $reorderCount = $rows->where('needs_reorder', true)->count();
        $deadCount = $rows->where('is_dead', true)->count();
    @endphp

    <div class="space-y-5">

        <section class="overflow-hidden rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-indigo-50 p-5 dark:border-violet-500/20 dark:from-violet-950/30 dark:via-gray-900 dark:to-indigo-950/20">
            <div class="inline-flex rounded-full bg-violet-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Inventory intelligence</div>
            <h2 class="mt-3 text-xl font-bold tracking-tight text-gray-950 dark:text-white">Product Insights</h2>
            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">See what is moving, what is tying up capital, and which products may need attention.</p>
        </section>


        {{-- KPI cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Inventory Value</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($kpis['inventory_value'], 0) }}</p>
                <p class="text-[11px] text-gray-400">capital in on-hand stock</p>
            </div>
            <div class="rounded-xl border border-rose-200 dark:border-rose-500/20 bg-rose-50 dark:bg-rose-500/10 px-5 py-4">
                <p class="text-xs font-medium uppercase tracking-wide text-rose-600 dark:text-rose-300 cursor-help" title="Capital tied up in products that haven't sold within the dead-stock window — inventory that isn't moving.">Dead Stock</p>
                <p class="mt-1 text-2xl font-bold text-rose-700 dark:text-rose-200">${{ number_format($kpis['dead_value'], 0) }}</p>
                <p class="text-[11px] text-rose-500/80 dark:text-rose-300/70">tied up, no sale in {{ \App\Filament\Pages\ProductInsights::DEAD_DAYS }}d</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 cursor-help" title="SKU = Stock Keeping Unit — a distinct product. Active SKUs is how many products are currently in your catalog.">Active SKUs</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($kpis['active_skus']) }}</p>
                <p class="text-[11px] text-gray-400">{{ number_format($kpis['sold_skus']) }} have sold</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 px-5 py-4">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Showing</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($rows->count()) }}</p>
                <p class="text-[11px] text-gray-400">
                    {{ $views[$this->view] ?? $this->view }}@if ($rows->count() >= \App\Filament\Pages\ProductInsights::ROW_LIMIT) · top {{ \App\Filament\Pages\ProductInsights::ROW_LIMIT }}@endif
                </p>
            </div>
        </div>

        {{-- View filter chips --}}
        <div class="sticky top-[64px] z-20 flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
            @foreach ($views as $key => $label)
                <button type="button" wire:click="setView('{{ $key }}')"
                    @class([
                        'px-3 py-1.5 rounded-full text-xs font-semibold transition',
                        'bg-primary-600 text-white' => $this->view === $key,
                        'bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/10' => $this->view !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach

            <span class="flex-1"></span>

            {{-- AI narrative summary --}}
            @if ($this->aiNarrativeEnabled())
                <button
                    type="button"
                    wire:click="generateNarrative"
                    wire:loading.attr="disabled"
                    wire:target="generateNarrative"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition disabled:opacity-60"
                >
                    <x-heroicon-o-sparkles class="h-3.5 w-3.5" wire:loading.remove wire:target="generateNarrative" />
                    <svg wire:loading wire:target="generateNarrative" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="generateNarrative">Summarize catalogue</span>
                    <span wire:loading wire:target="generateNarrative">Summarizing…</span>
                </button>
            @endif

            <a
                wire:click.prevent="exportCsv"
                href="#"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition"
            >
                <x-heroicon-o-arrow-down-tray class="h-3.5 w-3.5" />
                Export CSV
            </a>
        </div>

        @if ($narrative)
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 px-5 py-4 text-sm leading-relaxed text-indigo-900 shadow-sm dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-100">
                <div class="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-300">
                    <x-heroicon-o-sparkles class="h-3.5 w-3.5" />
                    AI Summary
                </div>
                {{ $narrative }}
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-[1.5fr_.8fr]">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3"><div><h3 class="text-sm font-bold text-gray-950 dark:text-white">Top products by revenue</h3><p class="mt-1 text-xs text-gray-500">Highest-revenue products in the current view.</p></div><span class="rounded-full bg-primary-50 px-2.5 py-1 text-[11px] font-bold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">{{ number_format($rows->sum('revenue'),0) }} total</span></div>
                <div class="mt-5 space-y-3">@forelse($topProducts as $product)<div><div class="mb-1 flex items-center justify-between gap-3 text-xs"><span class="truncate font-semibold text-gray-700 dark:text-gray-200">{{ $product['name'] }}</span><span class="font-bold tabular-nums text-gray-950 dark:text-white">${{ number_format($product['revenue'],0) }}</span></div><div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5"><div class="h-full rounded-full bg-primary-500" style="width:{{ max(4,($product['revenue']/$maxProductRevenue)*100) }}%"></div></div></div>@empty<p class="text-sm text-gray-400">No product revenue in this view.</p>@endforelse</div>
            </section>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                <button type="button" wire:click="setView('reorder')" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-left shadow-sm transition hover:border-amber-300 dark:border-amber-500/20 dark:bg-amber-950/20"><div class="text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Reorder attention</div><div class="mt-2 text-3xl font-black text-amber-800 dark:text-amber-200">{{ number_format($reorderCount) }}</div><p class="mt-1 text-xs text-amber-700/80 dark:text-amber-300/70">Products in this view that may need replenishment.</p></button>
                <button type="button" wire:click="setView('dead_stock')" class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-left shadow-sm transition hover:border-rose-300 dark:border-rose-500/20 dark:bg-rose-950/20"><div class="text-xs font-bold uppercase tracking-wide text-rose-700 dark:text-rose-300">Dead stock</div><div class="mt-2 text-3xl font-black text-rose-800 dark:text-rose-200">{{ number_format($deadCount) }}</div><p class="mt-1 text-xs text-rose-700/80 dark:text-rose-300/70">Products in this view with no sale inside the dead-stock window.</p></button>
            </div>
        </div>

        {{-- Metrics table --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Product</th>
                        <th class="px-3 py-2 text-right font-medium">On Hand</th>
                        <th class="px-3 py-2 text-right font-medium">Sold</th>
                        <th class="px-3 py-2 text-right font-medium">Revenue</th>
                        <th class="px-3 py-2 text-right font-medium">Margin</th>
                        <th class="px-3 py-2 text-right font-medium cursor-help" title="Units sold ÷ (units sold + units on hand). High means the product moves fast.">Sell-through</th>
                        <th class="px-3 py-2 text-right font-medium cursor-help" title="Based on the last {{ \App\Filament\Pages\ProductInsights::VELOCITY_WINDOW_DAYS }} days of sales, projected across the vendor's lead time plus a {{ \App\Filament\Pages\ProductInsights::SAFETY_STOCK_DAYS }}-day safety buffer. Blank when there's no recent sales history to estimate from.">Suggested Reorder</th>
                        <th class="px-3 py-2 text-right font-medium">Capital</th>
                        <th class="px-3 py-2 text-right font-medium">Last Sold</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($rows as $r)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $r['name'] }}</div>
                                @if ($r['category'])
                                    <div class="text-[11px] text-gray-400">{{ $r['category'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums {{ $r['is_dead'] ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-gray-600 dark:text-gray-300' }}">
                                {{ number_format($r['on_hand']) }}
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ number_format($r['units_sold']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">${{ number_format($r['revenue'], 0) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-semibold {{ $r['margin'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                ${{ number_format($r['margin'], 0) }}
                                @if (! is_null($r['margin_pct']))
                                    <span class="text-xs font-normal text-gray-400">{{ $r['margin_pct'] }}%</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums {{ $r['needs_reorder'] ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-600 dark:text-gray-300' }}">
                                {{ is_null($r['sell_through']) ? '—' : $r['sell_through'] . '%' }}
                                @if ($r['needs_reorder'])
                                    <span class="ml-1 text-xs font-normal text-amber-500" title="Fast seller running low — consider restocking">↑</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                @if (is_null($r['suggested_reorder_qty']))
                                    <span class="text-gray-400">—</span>
                                @else
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">{{ number_format($r['suggested_reorder_qty']) }}</span>
                                    @if (! is_null($r['days_of_stock_remaining']))
                                        <div class="text-xs font-normal text-gray-400">{{ $r['days_of_stock_remaining'] }}d left</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">${{ number_format($r['capital'], 0) }}</td>
                            <td class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">
                                @if (is_null($r['days_since_sold']))
                                    <span class="text-gray-400">never</span>
                                @else
                                    <span title="{{ $r['last_sold_at']?->format('M j, Y') }}">{{ $r['days_since_sold'] }}d ago</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-8 text-center text-gray-400">No products match this view.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
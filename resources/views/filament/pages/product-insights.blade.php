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
    @endphp

    <div class="space-y-5">

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
                <p class="text-[11px] text-gray-400">{{ $views[$this->view] ?? $this->view }}</p>
            </div>
        </div>

        {{-- View filter chips --}}
        <div class="flex flex-wrap gap-2">
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
                                    <span class="text-[10px] font-normal text-gray-400">{{ $r['margin_pct'] }}%</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums {{ $r['needs_reorder'] ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-600 dark:text-gray-300' }}">
                                {{ is_null($r['sell_through']) ? '—' : $r['sell_through'] . '%' }}
                                @if ($r['needs_reorder'])
                                    <span class="ml-1 text-[10px] font-normal text-amber-500" title="Fast seller running low — consider restocking">↑</span>
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
                            <td colspan="8" class="px-3 py-8 text-center text-gray-400">No products match this view.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>

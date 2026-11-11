<?php

namespace App\Filament\Widgets;

use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\Show;
use App\Support\ChannelContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class DashboardShowsKpiWidget extends BaseWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 0;
    protected int|string|array $columnSpan = 'full';
    protected int|array|null $columns = ['default' => 2, 'md' => 3, 'xl' => 6];

    protected function getStats(): array
    {
        $channel = ChannelContext::currentId() ?? 'all';

        [$value, $items, $units, $monthGross, $monthNet, $monthHours, $valueTrend, $salesTrend] = Cache::remember(
            "widget:dashboard_business_kpi:v2:{$channel}",
            120,
            function () {
                // Aggregate stock in SQL rather than hydrating every active product
                // and its stock rows for six small dashboard KPI cards.
                $inventory = Product::query()
                    ->where('is_active', true)
                    ->leftJoinSub(
                        \Illuminate\Support\Facades\DB::table('inventory_stock')
                            ->select('inventory_item_id')
                            ->selectRaw('SUM(quantity) AS stock_quantity')
                            ->groupBy('inventory_item_id'),
                        'stock_totals',
                        'stock_totals.inventory_item_id',
                        '=',
                        'products.id'
                    )
                    ->selectRaw('COUNT(*) AS product_count')
                    ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(stock_totals.stock_quantity, 0) > 0 THEN stock_totals.stock_quantity ELSE 0 END), 0) AS units_total')
                    ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(stock_totals.stock_quantity, 0) > 0 THEN stock_totals.stock_quantity ELSE 0 END * CASE WHEN COALESCE(products.average_cost, 0) > 0 THEN products.average_cost WHEN COALESCE(products.unit_cost, 0) > 0 THEN products.unit_cost ELSE 0 END), 0) AS inventory_value')
                    ->first();
                $items = (int) $inventory->product_count;
                $units = (float) $inventory->units_total;
                $value = (float) $inventory->inventory_value;

                $monthShows = Show::query()
                    ->inChannelContext()
                    ->whereBetween('show_date', [now()->startOfMonth(), now()->endOfMonth()]);

                $monthGross = (float) (clone $monthShows)->whereNotNull('gross_revenue')->sum('gross_revenue');
                $monthNet = (float) (clone $monthShows)->whereNotNull('completed_earnings')->sum('completed_earnings');
                $monthHours = (float) (clone $monthShows)->whereNotNull('show_duration')->sum('show_duration') / 60;

                $valueTrend = InventorySnapshot::query()
                    ->where('snapshot_date', '>=', now()->subDays(7))
                    ->orderBy('snapshot_date')
                    ->get()
                    ->groupBy(fn ($s) => $s->snapshot_date->format('Y-m-d'))
                    ->map(fn ($rows) => (float) $rows->last()->total_value)
                    ->values()->all();

                $salesByDay = Show::query()
                    ->inChannelContext()
                    ->whereBetween('show_date', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
                    ->selectRaw('DATE(show_date) as day, COALESCE(SUM(gross_revenue), 0) as gross_total')
                    ->groupByRaw('DATE(show_date)')
                    ->pluck('gross_total', 'day');

                $salesTrend = [];
                for ($i = 6; $i >= 0; $i--) {
                    $salesTrend[] = (float) ($salesByDay[now()->subDays($i)->toDateString()] ?? 0);
                }

                return [$value, $items, $units, $monthGross, $monthNet, $monthHours, $valueTrend, $salesTrend];
            }
        );

        $valueTrend = count($valueTrend) > 1 ? $valueTrend : [$value, $value];
        $salesTrend = count($salesTrend) > 1 ? $salesTrend : [$monthGross, $monthGross];

        return [
            Stat::make('Total Inventory Value', '$'.number_format($value, 2))
                ->description('Current on-hand valuation')->icon('heroicon-o-cube')->chart($valueTrend)->color('primary'),
            Stat::make('Total Items', number_format($items))
                ->description('Active inventory SKUs')->icon('heroicon-o-tag')->color('info'),
            Stat::make('Units on Hand', number_format($units))
                ->description('Across all inventory locations')->icon('heroicon-o-archive-box')->color('success'),
            Stat::make('Whatnot Gross', '$'.number_format($monthGross, 2))
                ->description('Gross revenue this month')->icon('heroicon-o-banknotes')->chart($salesTrend)->color('warning'),
            Stat::make('Whatnot Net', '$'.number_format($monthNet, 2))
                ->description('Completed earnings this month')->icon('heroicon-o-currency-dollar')->color('success'),
            Stat::make('Stream Hours', number_format($monthHours, 1))
                ->description('Whatnot show duration this month')->icon('heroicon-o-clock')->color('primary'),
        ];
    }
}

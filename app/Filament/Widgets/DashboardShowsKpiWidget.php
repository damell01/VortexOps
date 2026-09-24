<?php

namespace App\Filament\Widgets;

use App\Models\InventorySnapshot;
use App\Models\InventoryStock;
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
    protected int|array|null $columns = ['default' => 2, 'md' => 4, 'xl' => 4];

    protected function getStats(): array
    {
        $channel = ChannelContext::currentId() ?? 'all';

        [$value, $items, $units, $monthSales, $valueTrend, $salesTrend] = Cache::remember(
            "widget:dashboard_business_kpi:v1:{$channel}",
            120,
            function () {
                $products = Product::query()->where('is_active', true)->with('stock')->get();
                $items = $products->count();
                $units = (float) $products->sum(fn (Product $p) => max(0, (float) $p->stock->sum('quantity')));
                $value = (float) $products->sum(fn (Product $p) => max(0, (float) $p->stock->sum('quantity')) * (float) ($p->costBasis() ?? 0));

                $monthSales = (float) Show::query()
                    ->inChannelContext()
                    ->whereBetween('show_date', [now()->startOfMonth(), now()->endOfMonth()])
                    ->whereNotNull('gross_revenue')
                    ->sum('gross_revenue');

                $valueTrend = InventorySnapshot::query()
                    ->where('snapshot_date', '>=', now()->subDays(7))
                    ->orderBy('snapshot_date')
                    ->get()
                    ->groupBy(fn ($s) => $s->snapshot_date->format('Y-m-d'))
                    ->map(fn ($rows) => (float) $rows->last()->total_value)
                    ->values()->all();

                $salesTrend = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->toDateString();
                    $salesTrend[] = (float) Show::query()->inChannelContext()
                        ->whereDate('show_date', $date)->whereNotNull('gross_revenue')->sum('gross_revenue');
                }

                return [$value, $items, $units, $monthSales, $valueTrend, $salesTrend];
            }
        );

        $valueTrend = count($valueTrend) > 1 ? $valueTrend : [$value, $value];
        $salesTrend = count($salesTrend) > 1 ? $salesTrend : [$monthSales, $monthSales];

        return [
            Stat::make('Total Inventory Value', '$'.number_format($value, 2))
                ->description('Current on-hand valuation')
                ->icon('heroicon-o-cube')
                ->chart($valueTrend)
                ->color('primary'),
            Stat::make('Total Items', number_format($items))
                ->description('Active inventory SKUs')
                ->icon('heroicon-o-tag')
                ->color('info'),
            Stat::make('Units on Hand', number_format($units))
                ->description('Across all inventory locations')
                ->icon('heroicon-o-archive-box')
                ->color('success'),
            Stat::make("This Month's Sales", '$'.number_format($monthSales, 2))
                ->description('Whatnot Estimated Sales')
                ->icon('heroicon-o-banknotes')
                ->chart($salesTrend)
                ->color('warning'),
        ];
    }
}

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
    protected int|array|null $columns = ['default' => 2, 'md' => 3, 'xl' => 6];

    protected function getStats(): array
    {
        $channel = ChannelContext::currentId() ?? 'all';

        [$value, $items, $units, $monthGross, $monthNet, $monthHours, $valueTrend, $salesTrend] = Cache::remember(
            "widget:dashboard_business_kpi:v2:{$channel}",
            120,
            function () {
                $products = Product::query()->where('is_active', true)->with('stock')->get();
                $items = $products->count();
                $units = (float) $products->sum(fn (Product $p) => max(0, (float) $p->stock->sum('quantity')));
                $value = (float) $products->sum(fn (Product $p) => max(0, (float) $p->stock->sum('quantity')) * (float) ($p->costBasis() ?? 0));

                $monthShows = Show::query()
                    ->inChannelContext()
                    ->whereBetween('show_date', [now()->startOfMonth(), now()->endOfMonth()]);

                $monthGross = (float) (clone $monthShows)->whereNotNull('gross_revenue')->sum('gross_revenue');
                $monthNet = (float) (clone $monthShows)->whereNotNull('completed_earnings')->sum('completed_earnings');
                // Whatnot reports show_duration in minutes; derive dashboard hours from the imported duration.
                $monthHours = (float) (clone $monthShows)->whereNotNull('show_duration')->sum('show_duration') / 60;

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

                return [$value, $items, $units, $monthGross, $monthNet, $monthHours, $valueTrend, $salesTrend];
            }
        );

        $valueTrend = count($valueTrend) > 1 ? $valueTrend : [$value, $value];
        $salesTrend = count($salesTrend) > 1 ? $salesTrend : [$monthGross, $monthGross];

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
            Stat::make('Whatnot Gross', '
        ];
    }
}
.number_format($monthGross, 2))
                ->description('Gross revenue this month')
                ->icon('heroicon-o-banknotes')
                ->chart($salesTrend)
                ->color('warning'),
            Stat::make('Whatnot Net', '
        ];
    }
}
.number_format($monthNet, 2))
                ->description('Completed earnings this month')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make('Stream Hours', number_format($monthHours, 1))
                ->description('Whatnot show duration this month')
                ->icon('heroicon-o-clock')
                ->color('primary'),
        ];
    }
}

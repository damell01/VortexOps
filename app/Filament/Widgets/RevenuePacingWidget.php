<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use App\Support\ChannelContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * "Are we pacing ahead or behind" — visible any day of the week, not just at
 * the Wednesday mid-week report. Compares revenue-so-far this week to the
 * same relative day across the trailing 4 weeks, plus a month-level pacing
 * figure and a projected full-month total extrapolated from the current
 * daily run rate.
 */
class RevenuePacingWidget extends BaseWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    }

    protected function getStats(): array
    {
        $scope = (ChannelContext::currentId() ?? 'all') . ':' . now()->toDateString();

        $week  = Cache::flexible("widget:revenue_pacing:week:{$scope}", [300, 900], fn () => Show::weekPacing());
        $month = Cache::flexible("widget:revenue_pacing:month:{$scope}", [300, 900], fn () => Show::monthPacing());

        $weekPct  = $week['pacing_pct'];
        $monthPct = $month['pacing_pct'];

        $weekDescription = $weekPct === null
            ? 'Not enough history yet to compare'
            : (($weekPct >= 0 ? '+' : '') . number_format($weekPct, 1) . '% vs. last 4 weeks, same point in the week');

        $monthDescription = $monthPct === null
            ? 'Not enough history yet to compare'
            : (($monthPct >= 0 ? '+' : '') . number_format($monthPct, 1) . '% vs. last 3 months, same point in the month');

        return [
            Stat::make('This Week So Far', '$' . number_format($week['this_week_revenue'], 2))
                ->description($weekDescription)
                ->descriptionIcon($weekPct === null ? null : ($weekPct >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'))
                ->icon('heroicon-o-calendar-days')
                ->color($weekPct === null ? 'gray' : ($weekPct >= 0 ? 'success' : 'danger')),

            Stat::make('Projected This Month', $month['projected_month_total'] === null ? '—' : ('$' . number_format($month['projected_month_total'], 2)))
                ->description($monthDescription)
                ->descriptionIcon($monthPct === null ? null : ($monthPct >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'))
                ->icon('heroicon-o-chart-bar')
                ->color($monthPct === null ? 'gray' : ($monthPct >= 0 ? 'success' : 'danger')),
        ];
    }
}

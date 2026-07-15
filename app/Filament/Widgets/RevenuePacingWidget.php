<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * "Are we pacing ahead or behind" — visible any day of the week, not just at
 * the Wednesday mid-week report. Compares revenue-so-far this week to the
 * same relative day across the trailing 4 weeks.
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
        $cacheKey = 'widget:revenue_pacing:' . (ChannelContext::currentId() ?? 'all') . ':' . now()->toDateString();

        $pacing = Cache::flexible($cacheKey, [300, 900], fn () => Show::weekPacing());

        $pct = $pacing['pacing_pct'];

        $description = $pct === null
            ? 'Not enough history yet to compare'
            : (($pct >= 0 ? '+' : '') . number_format($pct, 1) . '% vs. last 4 weeks, same point in the week');

        return [
            Stat::make('This Week So Far', '$' . number_format($pacing['this_week_revenue'], 2))
                ->description($description)
                ->descriptionIcon($pct === null ? null : ($pct >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'))
                ->icon('heroicon-o-calendar-days')
                ->color($pct === null ? 'gray' : ($pct >= 0 ? 'success' : 'danger')),
        ];
    }
}

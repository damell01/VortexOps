<?php

namespace App\Filament\Widgets;

use App\Models\Payout;
use App\Models\Show;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ShowsKpiWidget extends BaseWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('streams');
    }

    protected function getStats(): array
    {
        $cacheKey = 'widget:shows_kpi:' . (ChannelContext::currentId() ?? 'all');

        [$weekShows, $weekRevenue, $pendingReview, $draftPayoutTotal] =
            Cache::remember($cacheKey, 120, function () {
                $weekStart = now()->startOfWeek()->toDateString();
                $weekEnd   = now()->endOfWeek()->toDateString();

                $weekShows    = Show::whereBetween('show_date', [$weekStart, $weekEnd])->inChannelContext()->count();
                $weekRevenue  = (float) Show::whereBetween('show_date', [$weekStart, $weekEnd])
                    ->whereNotNull('whatnot_net')
                    ->inChannelContext()
                    ->sum('whatnot_net');
                $pendingReview = Show::where('status', 'pending_review')->inChannelContext()->count();
                $draftPayoutTotal = AdminModules::isEnabled('payouts')
                    ? (float) Payout::where('status', 'draft')->inChannelContext()->sum('calculated_payout')
                    : 0.0;

                return [$weekShows, $weekRevenue, $pendingReview, $draftPayoutTotal];
            });

        return [
            Stat::make('Shows This Week', $weekShows)
                ->description(now()->format('M j') . ' – ' . now()->endOfWeek()->format('M j'))
                ->icon('heroicon-o-video-camera')
                ->color('primary'),

            Stat::make('Revenue This Week', '$' . number_format($weekRevenue, 2))
                ->description('Whatnot net proceeds')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Pending Review', $pendingReview)
                ->description($pendingReview > 0 ? 'Shows awaiting streamer assignment' : 'No shows in review queue')
                ->icon('heroicon-o-clock')
                ->color($pendingReview > 0 ? 'warning' : 'gray'),

            Stat::make('Draft Payouts', '$' . number_format($draftPayoutTotal, 2))
                ->description('Awaiting approval across all streamers')
                ->icon('heroicon-o-currency-dollar')
                ->color($draftPayoutTotal > 0 ? 'info' : 'gray'),
        ];
    }
}

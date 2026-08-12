<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTrend;
use App\Models\Payout;
use App\Models\Show;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ShowsKpiWidget extends BaseWidget
{
    use HasTrend;

    protected static bool $isLazy = true;
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('streams');
    }

    protected function getStats(): array
    {
        $cacheKey = 'widget:shows_kpi:' . (ChannelContext::currentId() ?? 'all');

        [$weekShows, $weekRevenue, $pendingReview, $draftPayoutTotal, $dailyShows, $dailyRevenue, $priorWeekShows, $priorWeekRevenue, $weekHours, $priorWeekHours] =
            Cache::remember($cacheKey, 120, function () {
                $weekStart = now()->startOfWeek()->toDateString();
                // Upper bounds include a time component: a show_date row dated
                // "today" is stored with a midnight timestamp, which a bare
                // date-string upper bound would exclude via lexical comparison
                // on SQLite (see Show::weekPacing() for the same trap).
                $weekEnd   = now()->endOfWeek()->endOfDay()->toDateTimeString();
                // Fair week-over-week comparison: same weekday span last week,
                // not last week's full total vs. this week's partial-so-far total.
                $priorWeekStart = now()->subWeek()->startOfWeek()->toDateString();
                $priorWeekEnd   = now()->subWeek()->endOfDay()->toDateTimeString();

                $weekShows    = Show::whereBetween('show_date', [$weekStart, $weekEnd])->inChannelContext()->count();
                $weekRevenue  = (float) Show::whereBetween('show_date', [$weekStart, $weekEnd])
                    ->whereNotNull('whatnot_net')
                    ->inChannelContext()
                    ->sum('whatnot_net');
                $priorWeekShows = Show::whereBetween('show_date', [$priorWeekStart, $priorWeekEnd])->inChannelContext()->count();
                $priorWeekRevenue = (float) Show::whereBetween('show_date', [$priorWeekStart, $priorWeekEnd])
                    ->whereNotNull('whatnot_net')
                    ->inChannelContext()
                    ->sum('whatnot_net');
                $weekHours = (float) Show::whereBetween('show_date', [$weekStart, $weekEnd])
                    ->inChannelContext()
                    ->sum('show_duration') / 60;
                $priorWeekHours = (float) Show::whereBetween('show_date', [$priorWeekStart, $priorWeekEnd])
                    ->inChannelContext()
                    ->sum('show_duration') / 60;

                $pendingReview = Show::where('status', 'pending_review')->inChannelContext()->count();
                $draftPayoutTotal = AdminModules::isEnabled('payouts')
                    ? (float) Payout::where('status', 'draft')->inChannelContext()->sum('calculated_payout')
                    : 0.0;

                // Trailing 7 days, oldest first, for the sparkline.
                $dailyShows   = [];
                $dailyRevenue = [];
                for ($i = 6; $i >= 0; $i--) {
                    $day = now()->subDays($i)->toDateString();
                    $dailyShows[]   = Show::where('show_date', $day)->inChannelContext()->count();
                    $dailyRevenue[] = (float) Show::where('show_date', $day)->whereNotNull('whatnot_net')->inChannelContext()->sum('whatnot_net');
                }

                return [$weekShows, $weekRevenue, $pendingReview, $draftPayoutTotal, $dailyShows, $dailyRevenue, $priorWeekShows, $priorWeekRevenue, $weekHours, $priorWeekHours];
            });

        return [
            Stat::make('Shows This Week', $weekShows)
                ->description(now()->format('M j') . ' – ' . now()->endOfWeek()->format('M j') . $this->trendSuffix($weekShows, $priorWeekShows))
                ->descriptionIcon($this->trendIcon($weekShows, $priorWeekShows))
                ->chart($dailyShows)
                ->icon('heroicon-o-video-camera')
                ->color($this->trendColor($weekShows, $priorWeekShows, 'primary')),

            Stat::make('Stream Hours This Week', number_format($weekHours, 1))
                ->description('Across all shows' . $this->trendSuffix($weekHours, $priorWeekHours))
                ->descriptionIcon($this->trendIcon($weekHours, $priorWeekHours))
                ->icon('heroicon-o-clock')
                ->color($this->trendColor($weekHours, $priorWeekHours, 'primary')),

            Stat::make('Revenue This Week', '$' . number_format($weekRevenue, 2))
                ->description('Whatnot net proceeds' . $this->trendSuffix($weekRevenue, $priorWeekRevenue))
                ->descriptionIcon($this->trendIcon($weekRevenue, $priorWeekRevenue))
                ->chart($dailyRevenue)
                ->icon('heroicon-o-banknotes')
                ->color($this->trendColor($weekRevenue, $priorWeekRevenue, 'success')),

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

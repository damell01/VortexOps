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

    /**
     * Keep the financial figures explicit: Whatnot Estimated Sales is gross,
     * while Total Estimated Earnings is the Whatnot-provided estimated net.
     */
    protected int | array | null $columns = [
        'default' => 2,
        'md'      => 3,
        'xl'      => 6,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('streams');
    }

    protected function getStats(): array
    {
        $cacheKey = 'widget:shows_kpi:v2:' . (ChannelContext::currentId() ?? 'all');

        [
            $weekShows,
            $weekGross,
            $weekEstimatedNet,
            $pendingReview,
            $draftPayoutTotal,
            $dailyShows,
            $dailyGross,
            $dailyEstimatedNet,
            $priorWeekShows,
            $priorWeekGross,
            $priorWeekEstimatedNet,
            $weekHours,
            $priorWeekHours,
        ] = Cache::remember($cacheKey, 120, function () {
            $weekStart = now()->startOfWeek()->toDateString();
            $weekEnd   = now()->endOfWeek()->endOfDay()->toDateTimeString();

            // Fair week-over-week comparison: compare this week's elapsed span
            // with the equivalent span last week rather than a completed week.
            $priorWeekStart = now()->subWeek()->startOfWeek()->toDateString();
            $priorWeekEnd   = now()->subWeek()->endOfDay()->toDateTimeString();

            $weekQuery = fn () => Show::whereBetween('show_date', [$weekStart, $weekEnd])->inChannelContext();
            $priorWeekQuery = fn () => Show::whereBetween('show_date', [$priorWeekStart, $priorWeekEnd])->inChannelContext();

            $weekShows = $weekQuery()->count();
            $weekGross = (float) $weekQuery()->whereNotNull('gross_revenue')->sum('gross_revenue');
            $weekEstimatedNet = (float) $weekQuery()->whereNotNull('whatnot_net')->sum('whatnot_net');

            $priorWeekShows = $priorWeekQuery()->count();
            $priorWeekGross = (float) $priorWeekQuery()->whereNotNull('gross_revenue')->sum('gross_revenue');
            $priorWeekEstimatedNet = (float) $priorWeekQuery()->whereNotNull('whatnot_net')->sum('whatnot_net');

            $weekHours = (float) $weekQuery()->sum('show_duration') / 60;
            $priorWeekHours = (float) $priorWeekQuery()->sum('show_duration') / 60;

            $pendingReview = Show::where('status', 'pending_review')->inChannelContext()->count();
            $draftPayoutTotal = AdminModules::isEnabled('payouts')
                ? (float) Payout::where('status', 'draft')->inChannelContext()->sum('calculated_payout')
                : 0.0;

            // Trailing 7 days, oldest first, for the sparklines.
            $dailyShows = [];
            $dailyGross = [];
            $dailyEstimatedNet = [];

            for ($i = 6; $i >= 0; $i--) {
                $day = now()->subDays($i)->toDateString();
                $dailyShows[] = Show::where('show_date', $day)->inChannelContext()->count();
                $dailyGross[] = (float) Show::where('show_date', $day)
                    ->whereNotNull('gross_revenue')
                    ->inChannelContext()
                    ->sum('gross_revenue');
                $dailyEstimatedNet[] = (float) Show::where('show_date', $day)
                    ->whereNotNull('whatnot_net')
                    ->inChannelContext()
                    ->sum('whatnot_net');
            }

            return [
                $weekShows,
                $weekGross,
                $weekEstimatedNet,
                $pendingReview,
                $draftPayoutTotal,
                $dailyShows,
                $dailyGross,
                $dailyEstimatedNet,
                $priorWeekShows,
                $priorWeekGross,
                $priorWeekEstimatedNet,
                $weekHours,
                $priorWeekHours,
            ];
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

            Stat::make('Gross Revenue', '$' . number_format($weekGross, 2))
                ->description('Whatnot Estimated Sales' . $this->trendSuffix($weekGross, $priorWeekGross))
                ->descriptionIcon($this->trendIcon($weekGross, $priorWeekGross))
                ->chart($dailyGross)
                ->icon('heroicon-o-currency-dollar')
                ->color($this->trendColor($weekGross, $priorWeekGross, 'primary')),

            Stat::make('Estimated Net Earnings', '$' . number_format($weekEstimatedNet, 2))
                ->description('Whatnot Total Estimated Earnings' . $this->trendSuffix($weekEstimatedNet, $priorWeekEstimatedNet))
                ->descriptionIcon($this->trendIcon($weekEstimatedNet, $priorWeekEstimatedNet))
                ->chart($dailyEstimatedNet)
                ->icon('heroicon-o-banknotes')
                ->color($this->trendColor($weekEstimatedNet, $priorWeekEstimatedNet, 'success')),

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

<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTrend;
use App\Models\Payout;
use App\Models\Show;
use App\Models\WhatnotLedgerEntry;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ShowsKpiWidget extends BaseWidget
{
    use HasTrend;

    protected static bool $isLazy = true;
    protected static ?int $sort = 0;

    /**
     * Keep the financial figures explicit:
     * - Gross Revenue = Whatnot Estimated Sales
     * - Estimated Net Earnings = Whatnot Total Estimated Earnings
     * - Completed Earnings = Whatnot Completed Earnings
     * - Ledger Net = signed Whatnot ledger activity for the period
     */
    protected int | array | null $columns = [
        'default' => 2,
        'md'      => 4,
        'xl'      => 4,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('streams');
    }

    protected function getStats(): array
    {
        $cacheKey = 'widget:shows_kpi:v3:' . (ChannelContext::currentId() ?? 'all');

        [
            $weekShows,
            $weekGross,
            $weekEstimatedNet,
            $weekCompleted,
            $weekLedgerNet,
            $pendingReview,
            $draftPayoutTotal,
            $dailyShows,
            $dailyGross,
            $dailyEstimatedNet,
            $dailyCompleted,
            $dailyLedgerNet,
            $priorWeekShows,
            $priorWeekGross,
            $priorWeekEstimatedNet,
            $priorWeekCompleted,
            $priorWeekLedgerNet,
            $weekHours,
            $priorWeekHours,
        ] = Cache::remember($cacheKey, 120, function () {
            $weekStart = now()->startOfWeek()->startOfDay();
            $weekEnd   = now()->endOfWeek()->endOfDay();

            // Fair week-over-week comparison: compare this week's elapsed span
            // with the equivalent span last week rather than a completed week.
            $priorWeekStart = now()->subWeek()->startOfWeek()->startOfDay();
            $priorWeekEnd   = now()->subWeek()->endOfDay();

            $weekQuery = fn () => Show::whereBetween('show_date', [$weekStart, $weekEnd])->inChannelContext();
            $priorWeekQuery = fn () => Show::whereBetween('show_date', [$priorWeekStart, $priorWeekEnd])->inChannelContext();

            $ledgerQuery = function ($start, $end) {
                if (! Schema::hasTable('whatnot_ledger_entries')) {
                    return null;
                }

                $query = WhatnotLedgerEntry::query()->whereBetween('created_date', [$start, $end]);

                if (ChannelContext::isScoped()) {
                    $query->where('whatnot_channel_id', ChannelContext::currentId());
                }

                return $query;
            };

            $weekShows = $weekQuery()->count();
            $weekGross = (float) $weekQuery()->whereNotNull('gross_revenue')->sum('gross_revenue');
            $weekEstimatedNet = (float) $weekQuery()->whereNotNull('whatnot_net')->sum('whatnot_net');
            $weekCompleted = (float) $weekQuery()->whereNotNull('completed_earnings')->sum('completed_earnings');
            $weekLedgerNet = (float) ($ledgerQuery($weekStart, $weekEnd)?->sum('amount') ?? 0);

            $priorWeekShows = $priorWeekQuery()->count();
            $priorWeekGross = (float) $priorWeekQuery()->whereNotNull('gross_revenue')->sum('gross_revenue');
            $priorWeekEstimatedNet = (float) $priorWeekQuery()->whereNotNull('whatnot_net')->sum('whatnot_net');
            $priorWeekCompleted = (float) $priorWeekQuery()->whereNotNull('completed_earnings')->sum('completed_earnings');
            $priorWeekLedgerNet = (float) ($ledgerQuery($priorWeekStart, $priorWeekEnd)?->sum('amount') ?? 0);

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
            $dailyCompleted = [];
            $dailyLedgerNet = [];

            for ($i = 6; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $dayStart = $day->copy()->startOfDay();
                $dayEnd = $day->copy()->endOfDay();
                $date = $day->toDateString();

                $dailyShows[] = Show::where('show_date', $date)->inChannelContext()->count();
                $dailyGross[] = (float) Show::where('show_date', $date)
                    ->whereNotNull('gross_revenue')
                    ->inChannelContext()
                    ->sum('gross_revenue');
                $dailyEstimatedNet[] = (float) Show::where('show_date', $date)
                    ->whereNotNull('whatnot_net')
                    ->inChannelContext()
                    ->sum('whatnot_net');
                $dailyCompleted[] = (float) Show::where('show_date', $date)
                    ->whereNotNull('completed_earnings')
                    ->inChannelContext()
                    ->sum('completed_earnings');
                $dailyLedgerNet[] = (float) ($ledgerQuery($dayStart, $dayEnd)?->sum('amount') ?? 0);
            }

            return [
                $weekShows,
                $weekGross,
                $weekEstimatedNet,
                $weekCompleted,
                $weekLedgerNet,
                $pendingReview,
                $draftPayoutTotal,
                $dailyShows,
                $dailyGross,
                $dailyEstimatedNet,
                $dailyCompleted,
                $dailyLedgerNet,
                $priorWeekShows,
                $priorWeekGross,
                $priorWeekEstimatedNet,
                $priorWeekCompleted,
                $priorWeekLedgerNet,
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

            Stat::make('Completed Earnings', '$' . number_format($weekCompleted, 2))
                ->description('Whatnot Completed Earnings' . $this->trendSuffix($weekCompleted, $priorWeekCompleted))
                ->descriptionIcon($this->trendIcon($weekCompleted, $priorWeekCompleted))
                ->chart($dailyCompleted)
                ->icon('heroicon-o-check-circle')
                ->color($this->trendColor($weekCompleted, $priorWeekCompleted, 'success')),

            Stat::make('Ledger Net', '$' . number_format($weekLedgerNet, 2))
                ->description('Signed Whatnot ledger activity' . $this->trendSuffix($weekLedgerNet, $priorWeekLedgerNet))
                ->descriptionIcon($this->trendIcon($weekLedgerNet, $priorWeekLedgerNet))
                ->chart($dailyLedgerNet)
                ->icon('heroicon-o-document-currency-dollar')
                ->color($this->trendColor($weekLedgerNet, $priorWeekLedgerNet, 'info')),

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

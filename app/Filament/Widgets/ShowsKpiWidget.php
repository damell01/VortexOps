<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTrend;
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
    protected int | string | array $columnSpan = 'full';

    protected int | array | null $columns = [
        'default' => 2,
        'md'      => 4,
        'xl'      => 4,
    ];

    public static function canView(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) && AdminModules::isEnabled('streams');
    }

    protected function getStats(): array
    {
        $cacheKey = 'widget:shows_kpi:v6:' . (ChannelContext::currentId() ?? 'all');

        [
            $weekShows,
            $weekGross,
            $weekNet,
            $dailyShows,
            $dailyGross,
            $dailyNet,
            $priorWeekShows,
            $priorWeekGross,
            $priorWeekNet,
            $weekHours,
            $priorWeekHours,
        ] = Cache::remember($cacheKey, 120, function () {
            $weekStart = now()->startOfWeek()->startOfDay();
            $weekEnd   = now()->endOfWeek()->endOfDay();
            $priorWeekStart = now()->subWeek()->startOfWeek()->startOfDay();
            $priorWeekEnd   = now()->subWeek()->endOfDay();

            $window = Show::query()
                ->inChannelContext()
                ->whereBetween('show_date', [$priorWeekStart, $weekEnd])
                ->selectRaw('DATE(show_date) as day')
                ->selectRaw('COUNT(*) as shows_count')
                ->selectRaw('COALESCE(SUM(gross_revenue), 0) as gross_total')
                ->selectRaw('COALESCE(SUM(completed_earnings), 0) as net_total')
                ->selectRaw('COALESCE(SUM(show_duration), 0) as duration_total')
                ->groupByRaw('DATE(show_date)')
                ->get()
                ->keyBy(fn ($row) => (string) $row->day);

            $sumRange = function ($start, $end, string $field) use ($window): float {
                return (float) $window->filter(fn ($row, $day) => $day >= $start->toDateString() && $day <= $end->toDateString())->sum($field);
            };

            $weekShows = (int) $sumRange($weekStart, $weekEnd, 'shows_count');
            $weekGross = $sumRange($weekStart, $weekEnd, 'gross_total');
            $weekNet = $sumRange($weekStart, $weekEnd, 'net_total');
            $weekHours = $sumRange($weekStart, $weekEnd, 'duration_total') / 60;
            $priorWeekShows = (int) $sumRange($priorWeekStart, $priorWeekEnd, 'shows_count');
            $priorWeekGross = $sumRange($priorWeekStart, $priorWeekEnd, 'gross_total');
            $priorWeekNet = $sumRange($priorWeekStart, $priorWeekEnd, 'net_total');
            $priorWeekHours = $sumRange($priorWeekStart, $priorWeekEnd, 'duration_total') / 60;

            $dailyShows = [];
            $dailyGross = [];
            $dailyNet = [];
            for ($i = 6; $i >= 0; $i--) {
                $row = $window->get(now()->subDays($i)->toDateString());
                $dailyShows[] = (int) ($row->shows_count ?? 0);
                $dailyGross[] = (float) ($row->gross_total ?? 0);
                $dailyNet[] = (float) ($row->net_total ?? 0);
            }

            return [
                $weekShows,
                $weekGross,
                $weekNet,
                $dailyShows,
                $dailyGross,
                $dailyNet,
                $priorWeekShows,
                $priorWeekGross,
                $priorWeekNet,
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

            Stat::make('Completed Earnings', '$' . number_format($weekNet, 2))
                ->description('Whatnot Completed Earnings' . $this->trendSuffix($weekNet, $priorWeekNet))
                ->descriptionIcon($this->trendIcon($weekNet, $priorWeekNet))
                ->chart($dailyNet)
                ->icon('heroicon-o-banknotes')
                ->color($this->trendColor($weekNet, $priorWeekNet, 'success')),
        ];
    }
}

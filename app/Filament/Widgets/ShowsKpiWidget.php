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
        $cacheKey = 'widget:shows_kpi:v5:' . (ChannelContext::currentId() ?? 'all');

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

            $weekQuery = fn () => Show::whereBetween('show_date', [$weekStart, $weekEnd])->inChannelContext();
            $priorWeekQuery = fn () => Show::whereBetween('show_date', [$priorWeekStart, $priorWeekEnd])->inChannelContext();

            $weekShows = $weekQuery()->count();
            $weekGross = (float) $weekQuery()->whereNotNull('gross_revenue')->sum('gross_revenue');
            $weekNet = (float) $weekQuery()->whereNotNull('whatnot_net')->sum('whatnot_net');

            $priorWeekShows = $priorWeekQuery()->count();
            $priorWeekGross = (float) $priorWeekQuery()->whereNotNull('gross_revenue')->sum('gross_revenue');
            $priorWeekNet = (float) $priorWeekQuery()->whereNotNull('whatnot_net')->sum('whatnot_net');

            $weekHours = (float) $weekQuery()->sum('show_duration') / 60;
            $priorWeekHours = (float) $priorWeekQuery()->sum('show_duration') / 60;

            $dailyShows = [];
            $dailyGross = [];
            $dailyNet = [];

            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $dailyShows[] = Show::where('show_date', $date)->inChannelContext()->count();
                $dailyGross[] = (float) Show::where('show_date', $date)->whereNotNull('gross_revenue')->inChannelContext()->sum('gross_revenue');
                $dailyNet[] = (float) Show::where('show_date', $date)->whereNotNull('whatnot_net')->inChannelContext()->sum('whatnot_net');
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

            Stat::make('Net Revenue', '$' . number_format($weekNet, 2))
                ->description('Whatnot Total Estimated Earnings' . $this->trendSuffix($weekNet, $priorWeekNet))
                ->descriptionIcon($this->trendIcon($weekNet, $priorWeekNet))
                ->chart($dailyNet)
                ->icon('heroicon-o-banknotes')
                ->color($this->trendColor($weekNet, $priorWeekNet, 'success')),
        ];
    }
}

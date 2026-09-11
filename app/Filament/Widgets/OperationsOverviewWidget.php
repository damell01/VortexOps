<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTrend;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\WhatnotShowOrder;
use App\Support\ChannelContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class OperationsOverviewWidget extends BaseWidget
{
    use HasTrend;

    protected static bool $isLazy = true;
    protected static ?int $sort = 1;

    protected int | array | null $columns = [
        'default' => 1,
        'md'      => 3,
        'xl'      => 3,
    ];

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    }

    protected function getStats(): array
    {
        $cacheKey = 'widget:ops_overview:' . (ChannelContext::currentId() ?? 'all');

        [$pendingLogs, $monthGross, $monthOrders, $priorMonthGross, $priorMonthOrders, $monthlyGross] =
            Cache::flexible($cacheKey, [120, 300], function () {
                $mStart = now()->startOfMonth();
                $mEnd   = now()->endOfMonth();
                $pStart = now()->subMonthNoOverflow()->startOfMonth();
                $pEnd   = now()->subMonthNoOverflow();

                $pendingLogs = StreamerLogEntry::where('status', 'pending')->inChannelContext()->count();

                $monthGross = (float) Show::whereBetween('show_date', [$mStart->toDateString(), $mEnd->copy()->endOfDay()->toDateTimeString()])
                    ->inChannelContext()
                    ->sum('gross_revenue');
                $priorMonthGross = (float) Show::whereBetween('show_date', [$pStart->toDateString(), $pEnd->copy()->endOfDay()->toDateTimeString()])
                    ->inChannelContext()
                    ->sum('gross_revenue');

                $monthOrders = WhatnotShowOrder::whereBetween('show_date', [$mStart->toDateString(), $mEnd->copy()->endOfDay()->toDateTimeString()])
                    ->inChannelContext()
                    ->count();
                $priorMonthOrders = WhatnotShowOrder::whereBetween('show_date', [$pStart->toDateString(), $pEnd->copy()->endOfDay()->toDateTimeString()])
                    ->inChannelContext()
                    ->count();

                $monthlyGross = [];
                for ($i = 5; $i >= 0; $i--) {
                    $ms = now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString();
                    $me = now()->subMonthsNoOverflow($i)->endOfMonth()->endOfDay()->toDateTimeString();
                    $monthlyGross[] = (float) Show::whereBetween('show_date', [$ms, $me])->inChannelContext()->sum('gross_revenue');
                }

                return [$pendingLogs, $monthGross, $monthOrders, $priorMonthGross, $priorMonthOrders, $monthlyGross];
            });

        return [
            Stat::make('Streamer Logs', number_format($pendingLogs))
                ->description($pendingLogs > 0 ? 'Awaiting streamer review' : 'All caught up')
                ->icon('heroicon-o-clipboard-document-list')
                ->color($pendingLogs > 0 ? 'warning' : 'gray'),

            Stat::make('Gross Revenue · ' . now()->format('M'), '$' . number_format($monthGross, 2))
                ->description('Across all shows this month' . $this->trendSuffix($monthGross, $priorMonthGross, 'last month'))
                ->descriptionIcon($this->trendIcon($monthGross, $priorMonthGross))
                ->chart($monthlyGross)
                ->icon('heroicon-o-banknotes')
                ->color($this->trendColor($monthGross, $priorMonthGross, 'primary')),

            Stat::make('Orders · ' . now()->format('M'), number_format($monthOrders))
                ->description('Imported order lines this month' . $this->trendSuffix($monthOrders, $priorMonthOrders, 'last month'))
                ->descriptionIcon($this->trendIcon($monthOrders, $priorMonthOrders))
                ->icon('heroicon-o-shopping-cart')
                ->color($this->trendColor($monthOrders, $priorMonthOrders, 'info')),
        ];
    }
}

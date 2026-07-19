<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTrend;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use App\Support\ChannelContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OperationsOverviewWidget extends BaseWidget
{
    use HasTrend;

    protected static bool $isLazy = true;
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    }

    protected function getStats(): array
    {
        // flexible() serves the cached value for 120s, then serves a slightly
        // stale value (up to 300s) while refreshing in the background — so an
        // expiring key never makes concurrent dashboard loads all recompute at once.
        $cacheKey = 'widget:ops_overview:' . (ChannelContext::currentId() ?? 'all');

        [$pendingLogs, $ledgerNet, $monthGross, $monthOrders, $priorLedgerNet, $priorMonthGross, $priorMonthOrders, $monthlyGross] =
            Cache::flexible($cacheKey, [120, 300], function () {
                $mStart = now()->startOfMonth();
                $mEnd   = now()->endOfMonth();
                // Fair month-over-month comparison: same day-of-month span last
                // month, not last month's full total vs. this month's partial total.
                $pStart = now()->subMonthNoOverflow()->startOfMonth();
                $pEnd   = now()->subMonthNoOverflow();

                $pendingLogs = StreamerLogEntry::where('status', 'pending')->inChannelContext()->count();

                // Ledger table may not be migrated yet on every environment.
                $hasLedger = Schema::hasTable('whatnot_ledger_entries');
                $ledgerNet = $hasLedger
                    ? (float) WhatnotLedgerEntry::whereBetween('created_date', [$mStart, $mEnd])->inChannelContext()->sum('amount')
                    : 0.0;
                $priorLedgerNet = $hasLedger
                    ? (float) WhatnotLedgerEntry::whereBetween('created_date', [$pStart, $pEnd])->inChannelContext()->sum('amount')
                    : 0.0;

                $monthGross = (float) Show::whereBetween('show_date', [$mStart->toDateString(), $mEnd->toDateString()])
                    ->inChannelContext()
                    ->sum('gross_revenue');
                $priorMonthGross = (float) Show::whereBetween('show_date', [$pStart->toDateString(), $pEnd->toDateString()])
                    ->inChannelContext()
                    ->sum('gross_revenue');

                $monthOrders = WhatnotShowOrder::whereBetween('show_date', [$mStart->toDateString(), $mEnd->toDateString()])
                    ->inChannelContext()
                    ->count();
                $priorMonthOrders = WhatnotShowOrder::whereBetween('show_date', [$pStart->toDateString(), $pEnd->toDateString()])
                    ->inChannelContext()
                    ->count();

                // Trailing 6 months of gross revenue, oldest first, for the sparkline.
                $monthlyGross = [];
                for ($i = 5; $i >= 0; $i--) {
                    $ms = now()->subMonthsNoOverflow($i)->startOfMonth()->toDateString();
                    $me = now()->subMonthsNoOverflow($i)->endOfMonth()->toDateString();
                    $monthlyGross[] = (float) Show::whereBetween('show_date', [$ms, $me])->inChannelContext()->sum('gross_revenue');
                }

                return [$pendingLogs, $ledgerNet, $monthGross, $monthOrders, $priorLedgerNet, $priorMonthGross, $priorMonthOrders, $monthlyGross];
            });

        return [
            Stat::make('Streamer Logs', number_format($pendingLogs))
                ->description($pendingLogs > 0 ? 'Awaiting streamer review' : 'All caught up')
                ->icon('heroicon-o-clipboard-document-list')
                ->color($pendingLogs > 0 ? 'warning' : 'gray'),

            Stat::make('Ledger Net · ' . now()->format('M'), '$' . number_format($ledgerNet, 2))
                ->description('Whatnot ledger, this month' . $this->trendSuffix($ledgerNet, $priorLedgerNet, 'last month'))
                ->descriptionIcon($this->trendIcon($ledgerNet, $priorLedgerNet))
                ->icon('heroicon-o-document-currency-dollar')
                ->color($ledgerNet >= 0 ? 'success' : 'danger'),

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

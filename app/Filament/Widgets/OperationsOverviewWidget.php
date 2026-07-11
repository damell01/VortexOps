<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OperationsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    }

    protected function getStats(): array
    {
        [$pendingLogs, $ledgerNet, $monthGross, $monthOrders] =
            Cache::remember('widget:ops_overview', 120, function () {
                $mStart = now()->startOfMonth();
                $mEnd   = now()->endOfMonth();

                $pendingLogs = StreamerLogEntry::where('status', 'pending')->count();

                // Ledger table may not be migrated yet on every environment.
                $ledgerNet = Schema::hasTable('whatnot_ledger_entries')
                    ? (float) WhatnotLedgerEntry::whereBetween('created_date', [$mStart, $mEnd])->sum('amount')
                    : 0.0;

                $monthGross = (float) Show::whereBetween('show_date', [$mStart->toDateString(), $mEnd->toDateString()])
                    ->sum('gross_revenue');

                $monthOrders = WhatnotShowOrder::whereBetween('show_date', [$mStart->toDateString(), $mEnd->toDateString()])->count();

                return [$pendingLogs, $ledgerNet, $monthGross, $monthOrders];
            });

        return [
            Stat::make('Pending Streamer Logs', number_format($pendingLogs))
                ->description($pendingLogs > 0 ? 'Awaiting streamer review' : 'All caught up')
                ->icon('heroicon-o-clipboard-document-list')
                ->color($pendingLogs > 0 ? 'warning' : 'gray'),

            Stat::make('Ledger Net · ' . now()->format('M'), '$' . number_format($ledgerNet, 2))
                ->description('Whatnot ledger, this month')
                ->icon('heroicon-o-document-currency-dollar')
                ->color($ledgerNet >= 0 ? 'success' : 'danger'),

            Stat::make('Gross Revenue · ' . now()->format('M'), '$' . number_format($monthGross, 2))
                ->description('Across all shows this month')
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make('Orders · ' . now()->format('M'), number_format($monthOrders))
                ->description('Imported order lines this month')
                ->icon('heroicon-o-shopping-cart')
                ->color('info'),
        ];
    }
}

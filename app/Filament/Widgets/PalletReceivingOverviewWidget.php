<?php

namespace App\Filament\Widgets;

use App\Models\Pallet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PalletReceivingOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stats = Cache::remember('widget:pallet_receiving_overview', 60, function (): array {
            $staged = Pallet::query()->where('status', 'staged')->count();
            $receiving = Pallet::query()->where('status', 'receiving')->count();
            $complete = Pallet::query()->whereIn('status', ['received', 'processed'])->count();

            $activeExpected = (int) \Illuminate\Support\Facades\DB::table('pallet_lines')
                ->join('pallets', 'pallets.id', '=', 'pallet_lines.pallet_id')
                ->whereIn('pallets.status', ['staged', 'receiving'])
                ->sum('pallet_lines.case_count');

            $activeReceived = (int) \Illuminate\Support\Facades\DB::table('inventory_cases')
                ->join('pallet_lines', 'pallet_lines.id', '=', 'inventory_cases.pallet_line_id')
                ->join('pallets', 'pallets.id', '=', 'pallet_lines.pallet_id')
                ->whereIn('pallets.status', ['staged', 'receiving'])
                ->where('inventory_cases.status', '!=', 'expected')
                ->count();

            $receivedThisMonth = Pallet::query()
                ->whereIn('status', ['received', 'processed'])
                ->whereBetween('received_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->count();

            return compact('staged', 'receiving', 'complete', 'activeExpected', 'activeReceived', 'receivedThisMonth');
        });

        $progress = $stats['activeExpected'] > 0
            ? (int) round(($stats['activeReceived'] / $stats['activeExpected']) * 100)
            : 0;

        return [
            Stat::make('Waiting to Receive', number_format($stats['staged']))
                ->description('Staged vendor pallets')
                ->icon('heroicon-o-clock')
                ->color($stats['staged'] > 0 ? 'warning' : 'gray'),

            Stat::make('Receiving Now', number_format($stats['receiving']))
                ->description($stats['receiving'] > 0 ? 'Pallets actively being checked in' : 'No pallet currently in progress')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color($stats['receiving'] > 0 ? 'primary' : 'gray'),

            Stat::make('Active Receipt Progress', $stats['activeReceived'] . ' / ' . $stats['activeExpected'])
                ->description($progress . '% of active expected packages received')
                ->icon('heroicon-o-archive-box')
                ->color($progress >= 100 && $stats['activeExpected'] > 0 ? 'success' : 'info'),

            Stat::make('Received / Complete', number_format($stats['complete']))
                ->description(number_format($stats['receivedThisMonth']) . ' completed this month')
                ->icon('heroicon-o-check-badge')
                ->color($stats['complete'] > 0 ? 'success' : 'gray'),
        ];
    }
}

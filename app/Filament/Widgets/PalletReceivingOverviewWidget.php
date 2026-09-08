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
            $received = Pallet::query()->where('status', 'received')->count();
            $processed = Pallet::query()->where('status', 'processed')->count();

            $activeExpected = (int) Pallet::query()
                ->whereIn('status', ['staged', 'receiving'])
                ->withSum('lines as expected_cases_sum', 'case_count')
                ->get()
                ->sum(fn (Pallet $pallet) => (int) ($pallet->expected_cases_sum ?? 0));

            $activeReceived = (int) Pallet::query()
                ->whereIn('status', ['staged', 'receiving'])
                ->withCount(['cases as received_cases_count' => fn ($query) => $query->where('inventory_cases.status', '!=', 'expected')])
                ->get()
                ->sum(fn (Pallet $pallet) => (int) ($pallet->received_cases_count ?? 0));

            return compact('staged', 'receiving', 'received', 'processed', 'activeExpected', 'activeReceived');
        });

        $progress = $stats['activeExpected'] > 0
            ? (int) round(($stats['activeReceived'] / $stats['activeExpected']) * 100)
            : 0;

        return [
            Stat::make('Waiting to Arrive', number_format($stats['staged']))
                ->description('Staged vendor shipments')
                ->icon('heroicon-o-clock')
                ->color($stats['staged'] > 0 ? 'warning' : 'gray'),

            Stat::make('Receiving Now', number_format($stats['receiving']))
                ->description($stats['receiving'] > 0 ? 'Pallets actively being checked in' : 'No pallet currently in progress')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color($stats['receiving'] > 0 ? 'primary' : 'gray'),

            Stat::make('Active Case Progress', $stats['activeReceived'] . ' / ' . $stats['activeExpected'])
                ->description($progress . '% of active expected cases received')
                ->icon('heroicon-o-archive-box')
                ->color($progress >= 100 && $stats['activeExpected'] > 0 ? 'success' : 'info'),

            Stat::make('Ready to Process', number_format($stats['received']))
                ->description($stats['processed'] . ' pallet(s) already completed')
                ->icon('heroicon-o-check-badge')
                ->color($stats['received'] > 0 ? 'success' : 'gray'),
        ];
    }
}

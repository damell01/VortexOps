<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Streamer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class InventoryOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected int | array | null $columns = [
        'md' => 2,
        'xl' => 4,
    ];

    protected function getStats(): array
    {
        [$totalQty, $totalItems, $lowStockCount, $activeLocations, $activeStreamers] =
            Cache::remember('widget:inventory_overview', 300, function () {
                return [
                    InventoryStock::sum('quantity'),
                    InventoryItem::where('is_active', true)->count(),
                    InventoryItem::whereNotNull('reorder_level')
                        ->where('is_active', true)
                        ->whereExists(fn ($q) => $q->selectRaw('1')
                            ->from('inventory_stock')
                            ->whereColumn('inventory_stock.inventory_item_id', 'inventory_items.id')
                            ->groupBy('inventory_stock.inventory_item_id')
                            ->havingRaw('SUM(quantity) <= inventory_items.reorder_level'))
                        ->count(),
                    InventoryLocation::where('status', 'active')->count(),
                    Streamer::where('status', 'active')->count(),
                ];
            });

        return [
            Stat::make('Total Units in Stock', number_format($totalQty, 0))
                ->description('Across all locations')
                ->icon('heroicon-o-archive-box')
                ->color('success'),

            Stat::make('Active SKUs', $totalItems)
                ->description('Tracked inventory items')
                ->icon('heroicon-o-tag')
                ->color('primary'),

            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description($lowStockCount > 0 ? 'Items at or below reorder level' : 'All items well stocked')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),

            Stat::make('Active Locations', $activeLocations)
                ->description("{$activeStreamers} active streamer(s)")
                ->icon('heroicon-o-map-pin')
                ->color('info'),
        ];
    }
}

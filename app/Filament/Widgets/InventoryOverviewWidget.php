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
    protected static bool $isLazy = true;
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        [$totalQty, $totalItems, $lowStockCount, $activeLocations, $activeStreamers, $inventoryValue] =
            Cache::remember('widget:inventory_overview', 300, function () {
                $inventoryValue = \Illuminate\Support\Facades\DB::table('inventory_stock')
                    ->join('products', 'inventory_stock.inventory_item_id', '=', 'products.id')
                    ->whereNotNull('products.average_cost')
                    ->where('products.average_cost', '>', 0)
                    ->selectRaw('SUM(inventory_stock.quantity * products.average_cost) as total_value')
                    ->value('total_value') ?? 0;

                return [
                    InventoryStock::sum('quantity'),
                    InventoryItem::where('is_active', true)->count(),
                    InventoryItem::whereNotNull('reorder_level')
                        ->where('is_active', true)
                        ->whereExists(fn ($q) => $q->selectRaw('1')
                            ->from('inventory_stock')
                            ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                            ->groupBy('inventory_stock.inventory_item_id')
                            ->havingRaw('SUM(quantity) <= products.reorder_level'))
                        ->count(),
                    InventoryLocation::where('status', 'active')->count(),
                    Streamer::where('status', 'active')->count(),
                    (float) $inventoryValue,
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

            Stat::make('Inventory Value (WAC)', '$' . number_format($inventoryValue, 2))
                ->description('Total value at weighted average cost')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning'),
        ];
    }
}

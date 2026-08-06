<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\InventoryItem;

class StreamerInventoryWidget extends Widget
{
    protected string $view = 'filament.widgets.streamer-inventory-widget';

    protected function getViewData(): array
    {
        $user = auth()->user();
        $streamer = $user?->streamer;

        if (!$streamer) {
            return [];
        }

        $inventoryLocations = $streamer->inventoryLocations()->pluck('id');

        $totalItems = InventoryItem::whereHas('stock', fn ($q) =>
            $q->whereIn('inventory_location_id', $inventoryLocations)
        )->where('is_active', true)->count();

        $totalQuantity = InventoryItem::whereHas('stock', fn ($q) =>
            $q->whereIn('inventory_location_id', $inventoryLocations)
        )->where('is_active', true)->withSum('stock', 'quantity')->get()->sum('stock_sum_quantity');

        $lowStockCount = InventoryItem::whereHas('stock', fn ($q) =>
            $q->whereIn('inventory_location_id', $inventoryLocations)
                ->whereRaw('quantity <= COALESCE(inventory_items.reorder_level, 0)')
        )->where('is_active', true)->count();

        $locationCount = $inventoryLocations->count();

        return [
            'totalItems' => $totalItems,
            'totalQuantity' => $totalQuantity,
            'lowStockCount' => $lowStockCount,
            'locationCount' => $locationCount,
        ];
    }
}

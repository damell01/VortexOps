<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\InventoryItem;
use App\Models\InventoryStock;

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

        $totalQuantity = (float) InventoryStock::query()
            ->whereIn('inventory_location_id', $inventoryLocations)
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->sum('quantity');

        $lowStockCount = InventoryItem::whereHas('stock', fn ($q) =>
            $q->whereIn('inventory_location_id', $inventoryLocations)
                ->whereRaw('quantity <= COALESCE(products.reorder_level, 0)')
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

<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\InventoryItem;
use App\Models\Shipment;

class FulfillmentInventoryWidget extends Widget
{
    protected string $view = 'filament.widgets.fulfillment-inventory-widget';

    protected function getViewData(): array
    {
        $lowStockCount = InventoryItem::whereHas('stock', fn ($q) =>
            $q->whereRaw('quantity <= COALESCE(reorder_level, 0)')
        )->where('is_active', true)->count();

        $readyToShip = Shipment::where('status', 'Ready to Ship')->count();
        $inTransit = Shipment::where('status', 'Shipped')->count();
        $delivered = Shipment::where('status', 'Delivered')->count();

        $totalItems = InventoryItem::where('is_active', true)->count();

        return [
            'lowStockCount' => $lowStockCount,
            'readyToShip' => $readyToShip,
            'inTransit' => $inTransit,
            'delivered' => $delivered,
            'totalItems' => $totalItems,
        ];
    }
}

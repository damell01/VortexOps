<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\InventoryItem;
use App\Models\Shipment;

class FulfillmentInventoryWidget extends Widget
{
    protected string $view = 'filament.widgets.fulfillment-inventory-widget';
    protected static bool $isLazy = true;

    protected function getViewData(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('widget:fulfillment_inventory', 60, function (): array {
            $shipmentStats = Shipment::query()
                ->selectRaw("SUM(CASE WHEN status = 'Ready to Ship' THEN 1 ELSE 0 END) as ready_to_ship")
                ->selectRaw("SUM(CASE WHEN status = 'Shipped' THEN 1 ELSE 0 END) as in_transit")
                ->selectRaw("SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered")
                ->first();

            $itemStats = InventoryItem::query()
                ->where('is_active', true)
                ->selectRaw('COUNT(*) as total_items')
                ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM inventory_stock s WHERE s.inventory_item_id = products.id AND s.quantity <= COALESCE(products.reorder_level, 0)) THEN 1 ELSE 0 END) as low_stock")
                ->first();

            return [
                'lowStockCount' => (int) ($itemStats->low_stock ?? 0),
                'readyToShip' => (int) ($shipmentStats->ready_to_ship ?? 0),
                'inTransit' => (int) ($shipmentStats->in_transit ?? 0),
                'delivered' => (int) ($shipmentStats->delivered ?? 0),
                'totalItems' => (int) ($itemStats->total_items ?? 0),
            ];
        });
    }
}

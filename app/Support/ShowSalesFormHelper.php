<?php

namespace App\Support;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Show;

class ShowSalesFormHelper
{
    /**
     * @return array<int, string>
     */
    public static function inventoryItemOptions(): array
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (InventoryItem $item): array {
                $totalOnHand = (float) $item->stock()->sum('quantity');
                $unitCost = (float) ($item->average_unit_cost ?: $item->landed_unit_cost ?: $item->unit_cost);

                return [
                    $item->id => sprintf(
                        '%s (%s) • %s on hand • $%s cost',
                        $item->name,
                        $item->sku,
                        self::formatQuantity($totalOnHand),
                        number_format($unitCost, 2)
                    ),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function locationOptionsForItem(?int $itemId): array
    {
        if (! $itemId) {
            return InventoryLocation::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return InventoryStock::query()
            ->with('location:id,name')
            ->where('inventory_item_id', $itemId)
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->get()
            ->mapWithKeys(function (InventoryStock $stock): array {
                $locationName = $stock->location?->name ?? 'Unknown location';

                return [
                    $stock->inventory_location_id => sprintf(
                        '%s (%s available)',
                        $locationName,
                        self::formatQuantity((float) $stock->quantity)
                    ),
                ];
            })
            ->all();
    }

    public static function stockSummaryForItem(?int $itemId): string
    {
        if (! $itemId) {
            return 'Choose an inventory item to see available stock by location.';
        }

        $stocks = InventoryStock::query()
            ->with('location:id,name')
            ->where('inventory_item_id', $itemId)
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->get();

        if ($stocks->isEmpty()) {
            return 'No on-hand stock was found for this item yet.';
        }

        return $stocks
            ->map(fn (InventoryStock $stock): string => sprintf(
                '%s: %s',
                $stock->location?->name ?? 'Unknown location',
                self::formatQuantity((float) $stock->quantity)
            ))
            ->implode(' • ');
    }

    public static function bestLocationIdForItem(?int $itemId): ?int
    {
        if (! $itemId) {
            return null;
        }

        return InventoryStock::query()
            ->where('inventory_item_id', $itemId)
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->value('inventory_location_id');
    }

    public static function bestLocationHintForItem(?int $itemId): string
    {
        if (! $itemId) {
            return 'Locations show live on-hand quantity for the selected item.';
        }

        $stock = InventoryStock::query()
            ->with('location:id,name')
            ->where('inventory_item_id', $itemId)
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->first();

        if (! $stock) {
            return 'No stocked location is available yet for this item.';
        }

        return sprintf(
            'Best match: %s with %s available.',
            $stock->location?->name ?? 'Unknown location',
            self::formatQuantity((float) $stock->quantity)
        );
    }

    public static function mappedItemsPreview(?Show $show): string
    {
        if (! $show) {
            return 'Save the show first to preview its sold-item worksheet.';
        }

        $request = $show->loadMissing('latestDeductionRequest.lines.inventoryItem', 'latestDeductionRequest.lines.location')
            ->latestDeductionRequest;

        if (! $request || $request->lines->isEmpty()) {
            return 'No sold items have been entered yet.';
        }

        return $request->lines
            ->map(function ($line): string {
                $item = $line->inventoryItem?->name ?? 'Unknown item';
                $location = $line->location?->name ?? 'Unknown location';

                return sprintf(
                    '%s x %s from %s',
                    $item,
                    self::formatQuantity((float) $line->quantity_approved),
                    $location
                );
            })
            ->implode("\n");
    }

    public static function stockImpactPreview(?Show $show): string
    {
        if (! $show) {
            return 'Save the show first to preview stock impact.';
        }

        $request = $show->loadMissing('latestDeductionRequest.lines.inventoryItem', 'latestDeductionRequest.lines.location')
            ->latestDeductionRequest;

        if (! $request || $request->lines->isEmpty()) {
            return 'Once sold items are entered, this panel will show the likely inventory locations and remaining stock checks.';
        }

        return $request->lines
            ->map(function ($line): string {
                $remaining = InventoryStock::query()
                    ->where('inventory_item_id', $line->inventory_item_id)
                    ->where('inventory_location_id', $line->inventory_location_id)
                    ->value('quantity');

                $item = $line->inventoryItem?->name ?? 'Unknown item';
                $location = $line->location?->name ?? 'Unknown location';
                $remainingQty = (float) ($remaining ?? 0);
                $afterApproval = max($remainingQty - (float) $line->quantity_approved, 0);

                return sprintf(
                    '%s at %s: %s on hand now, about %s after approval',
                    $item,
                    $location,
                    self::formatQuantity($remainingQty),
                    self::formatQuantity($afterApproval)
                );
            })
            ->implode("\n");
    }

    private static function formatQuantity(float $quantity): string
    {
        if ((int) $quantity == $quantity) {
            return number_format($quantity, 0);
        }

        return number_format($quantity, 2);
    }
}

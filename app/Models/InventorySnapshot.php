<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventorySnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'total_value',
        'total_items',
        'total_quantity',
        'average_margin',
        'location_breakdown',
        'item_type_breakdown',
        'streamer_breakdown',
        'slow_moving_items',
        'stock_outs',
    ];

    protected $casts = [
        'snapshot_date' => 'datetime',
        'total_value' => 'decimal:2',
        'average_margin' => 'decimal:2',
        'location_breakdown' => 'json',
        'item_type_breakdown' => 'json',
        'streamer_breakdown' => 'json',
        'slow_moving_items' => 'json',
        'stock_outs' => 'json',
    ];

    /**
     * Generate a new inventory snapshot with all current metrics.
     */
    public static function generateCurrent(): self
    {
        $stocks = InventoryStock::with(['item', 'location'])->get();

        // Which items have moved recently, in one grouped query.
        //
        // The loop below asked this per stock row — a count(*) over
        // inventory_movements for every row on the shelf — so the inventory
        // report ran 57 queries over 40 products and 97 over 80, growing one
        // for one with the catalogue. A warehouse of five thousand meant five
        // thousand round trips to draw a page.
        $movedRecently = InventoryMovement::query()
            ->whereIn('inventory_item_id', $stocks->pluck('inventory_item_id')->filter()->unique())
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct()
            ->pluck('inventory_item_id')
            ->flip();

        $totalValue = 0;
        $totalItems = 0;
        $totalQuantity = 0;
        $locationBreakdown = [];
        $itemTypeBreakdown = [];
        $streamerBreakdown = [];
        $slowMovingItems = [];
        $stockOuts = [];

        foreach ($stocks as $stock) {
            $itemValue = $stock->quantity * ($stock->item->average_cost ?? 0);
            $totalValue += $itemValue;
            $totalItems++;
            $totalQuantity += (int) $stock->quantity;

            // Location breakdown
            $locId = $stock->location_id;
            if (! isset($locationBreakdown[$locId])) {
                $locationBreakdown[$locId] = [
                    'name' => $stock->location->name,
                    'value' => 0,
                    'quantity' => 0,
                ];
            }
            $locationBreakdown[$locId]['value'] += $itemValue;
            $locationBreakdown[$locId]['quantity'] += (int) $stock->quantity;

            // Item type breakdown
            $type = $stock->item->type ?? 'Unknown';
            if (! isset($itemTypeBreakdown[$type])) {
                $itemTypeBreakdown[$type] = [
                    'value' => 0,
                    'quantity' => 0,
                ];
            }
            $itemTypeBreakdown[$type]['value'] += $itemValue;
            $itemTypeBreakdown[$type]['quantity'] += (int) $stock->quantity;

            // Streamer breakdown
            if ($stock->location->streamer_id) {
                $stId = $stock->location->streamer_id;
                if (! isset($streamerBreakdown[$stId])) {
                    $streamerBreakdown[$stId] = [
                        'name' => $stock->location->streamer?->name ?? 'Unknown',
                        'value' => 0,
                        'quantity' => 0,
                    ];
                }
                $streamerBreakdown[$stId]['value'] += $itemValue;
                $streamerBreakdown[$stId]['quantity'] += (int) $stock->quantity;
            }

            // Slow-moving items (no movement in last 30 days)
            $hasMoved = isset($movedRecently[$stock->inventory_item_id]);

            if (! $hasMoved && $stock->quantity > 0) {
                $slowMovingItems[] = [
                    'item_id' => $stock->item_id,
                    'name' => $stock->item->name,
                    'quantity' => (int) $stock->quantity,
                    'value' => $itemValue,
                ];
            }

            // Stock outs
            if ($stock->quantity == 0) {
                $stockOuts[] = [
                    'item_id' => $stock->item_id,
                    'name' => $stock->item->name,
                    'sku' => $stock->item->sku,
                ];
            }
        }

        return self::create([
            'snapshot_date' => now(),
            'total_value' => $totalValue,
            'total_items' => $totalItems,
            'total_quantity' => $totalQuantity,
            'average_margin' => self::calculateAverageMargin(),
            'location_breakdown' => $locationBreakdown,
            'item_type_breakdown' => $itemTypeBreakdown,
            'streamer_breakdown' => $streamerBreakdown,
            'slow_moving_items' => array_slice($slowMovingItems, 0, 10),
            'stock_outs' => array_slice($stockOuts, 0, 10),
        ]);
    }

    private static function calculateAverageMargin(): ?float
    {
        return null;
    }
}

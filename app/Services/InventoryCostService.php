<?php

namespace App\Services;

use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\PalletLine;
use App\Models\PalletLineCost;
use Decimal\Decimal;

/**
 * Manages weighted average cost (WAC) calculations and cost tracking
 * for inventory items across multiple vendors and receipt dates.
 */
class InventoryCostService
{
    /**
     * Track a cost change and correct the item's average cost from it.
     *
     * The actual cost math lives in PalletCorrectionService::correctUnitCost()
     * — this used to keep its own separate full-recompute-from-PalletLine-
     * history implementation, which used a different quantity basis
     * (expected case counts, not what's actually still on hand) and could
     * disagree with what the same correction did when made from the pallet
     * screen instead of here. One implementation, two entry points.
     */
    public function recordCostChange(
        PalletLine $line,
        float $newCost,
        ?string $notes = null,
        ?int $userId = null
    ): PalletLineCost {
        $previousCost = $line->unit_cost;

        $costRecord = PalletLineCost::create([
            'pallet_line_id' => $line->id,
            'inventory_item_id' => $line->inventory_item_id,
            'unit_cost' => $newCost,
            'previous_unit_cost' => $previousCost,
            'notes' => $notes,
            'updated_by' => $userId,
        ]);

        app(PalletCorrectionService::class)->correctUnitCost($line, $newCost);

        return $costRecord;
    }

    /**
     * Cost of what is on hand right now, by vendor: one row per wave (lot)
     * that still has units on the shelf, valued at what that wave cost.
     *
     * Waves that have sold out are not part of it — if the first wave ($100)
     * sold out and the second ($50) is on the shelf, this says $50, the same
     * as the item's average cost. (It used to add up every pallet line ever
     * received, so a long-gone wave kept pulling the figure.)
     */
    public function getCostBreakdown(Product $item): array
    {
        $lots = \App\Models\InventoryLot::query()
            ->where('product_id', $item->id)
            ->where('status', \App\Models\InventoryLot::STATUS_ACTIVE)
            ->where('remaining_quantity', '>', 0)
            ->with(['palletLine:id,pallet_id,line_number', 'palletLine.pallet:id,reference,received_date,vendor_id', 'palletLine.pallet.vendor:id,name'])
            ->orderBy('received_at')->orderBy('id')
            ->get();

        $breakdown = [];
        foreach ($lots as $lot) {
            $pallet = $lot->palletLine?->pallet;
            $vendor = $pallet?->vendor;
            $vendorKey = $vendor ? "{$vendor->id}:{$vendor->name}" : ($lot->source === \App\Models\InventoryLot::SOURCE_SYNTHETIC ? 'earlier' : 'unknown');
            $breakdown[$vendorKey] ??= [
                'vendor_id' => $vendor?->id,
                'vendor_name' => $vendor?->name ?? ($vendorKey === 'earlier' ? 'Earlier stock' : 'Unknown'),
                'receipts' => [],
                'total_qty' => 0,
                'weighted_total_cost' => 0,
                'average_cost' => 0,
            ];
            $qty = (float) $lot->remaining_quantity;
            $unitCost = (float) $lot->unit_cost;
            $breakdown[$vendorKey]['receipts'][] = [
                'pallet_reference' => $pallet?->reference,
                'received_date' => $pallet?->received_date ?? $lot->received_at,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $qty * $unitCost,
                'line_number' => $lot->palletLine?->line_number,
            ];
            $breakdown[$vendorKey]['total_qty'] += $qty;
            $breakdown[$vendorKey]['weighted_total_cost'] += $qty * $unitCost;
        }

        foreach ($breakdown as $key => $data) {
            if ($data['total_qty'] > 0) {
                $breakdown[$key]['average_cost'] = $data['weighted_total_cost'] / $data['total_qty'];
            }
        }

        return $breakdown;
    }

    /**
     * Compare cost trends for an item across time.
     */
    public function getCostTrend(Product $item, int $limit = 10): array
    {
        $lines = PalletLine::where('inventory_item_id', $item->id)
            ->whereHas('pallet')
            ->with('pallet.vendor')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $lines
            ->filter(fn (PalletLine $line) => $line->pallet !== null)
            ->map(function (PalletLine $line) {
                $pallet = $line->pallet;

                return [
                    'date' => $pallet?->received_date,
                    'vendor' => $pallet?->vendor?->name ?? 'Unknown',
                    'unit_cost' => (float) $line->unit_cost,
                    'quantity' => (float) $line->case_count * (float) $line->quantity_per_case,
                    'total_cost' => (float) $line->case_count * (float) $line->quantity_per_case * (float) $line->unit_cost,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Estimate total inventory value using average costs.
     *
     * costBasis() falls back to the list unit cost when there is no receiving
     * history yet, same as the Edit/View item pages. Reading the raw
     * average_cost column here (as this used to) meant an item priced but
     * never received showed $0.00 of value on every screen that calls this,
     * while the item's own pages showed a real number for the same item.
     */
    public function calculateInventoryValue(Product $item): float
    {
        return $item->totalQuantity() * (float) ($item->costBasis() ?? 0);
    }

    /**
     * Get all items with prices significantly different from average.
     */
    public function findPricingAnomalies(float $percentageThreshold = 20): array
    {
        $items = InventoryItem::where('is_active', true)->get();
        $anomalies = [];

        foreach ($items as $item) {
            $breakdown = $this->getCostBreakdown($item);
            if (empty($breakdown)) {
                continue;
            }

            $costs = array_column($breakdown, 'average_cost');
            $minCost = min($costs);
            $maxCost = max($costs);
            $avgCost = (float) $item->average_cost;

            if ($minCost > 0) {
                $variance = (($maxCost - $minCost) / $minCost) * 100;
                if ($variance > $percentageThreshold) {
                    $anomalies[] = [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'sku' => $item->sku,
                        'average_cost' => $avgCost,
                        'min_cost' => $minCost,
                        'max_cost' => $maxCost,
                        'variance_pct' => round($variance, 2),
                        'vendor_count' => count($breakdown),
                    ];
                }
            }
        }

        return $anomalies;
    }
}

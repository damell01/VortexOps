<?php

namespace App\Services;

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
     * Calculate and update weighted average cost when receiving inventory.
     *
     * Formula: WAC = ((existing_qty × existing_avg_cost) + (incoming_qty × incoming_cost)) / total_qty
     */
    public function updateAverageCostFromReceipt(
        InventoryItem $item,
        float $incomingQuantity,
        float $incomingCost,
        ?int $vendorId = null
    ): void {
        $existingQty = (float) $item->totalQuantity();
        $existingCost = (float) ($item->average_cost ?? 0);

        if ($incomingQuantity <= 0) {
            return;
        }

        $newTotalQty = $existingQty + $incomingQuantity;
        $newAverageCost = $newTotalQty > 0
            ? (($existingQty * $existingCost) + ($incomingQuantity * $incomingCost)) / $newTotalQty
            : $incomingCost;

        $item->update([
            'average_cost' => round($newAverageCost, 4),
        ]);
    }

    /**
     * Track cost changes for audit trail and reporting.
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

        // Update the line itself
        $line->update(['unit_cost' => $newCost]);

        // Update item's average cost if mapped
        if ($line->inventory_item_id) {
            $this->updateItemAverageFromAllReceipts($line->inventoryItem);
        }

        return $costRecord;
    }

    /**
     * Recalculate average cost based on all historical receipts for an item.
     * Useful after cost adjustments or corrections.
     */
    public function updateItemAverageFromAllReceipts(InventoryItem $item): void
    {
        // Get all received pallet lines for this item
        $receipts = PalletLine::where('inventory_item_id', $item->id)
            ->whereHas('pallet', fn ($q) => $q->whereIn('status', ['received', 'processed']))
            ->with('pallet')
            ->get();

        if ($receipts->isEmpty()) {
            return;
        }

        $totalCost = 0;
        $totalQty = 0;

        foreach ($receipts as $line) {
            $qty = (float) $line->case_count * (float) $line->quantity_per_case;
            $cost = (float) ($line->unit_cost ?? 0);
            $totalCost += $qty * $cost;
            $totalQty += $qty;
        }

        if ($totalQty > 0) {
            $newAverageCost = $totalCost / $totalQty;
            $item->update([
                'average_cost' => round($newAverageCost, 4),
            ]);
        }
    }

    /**
     * Get cost breakdown for an item across all vendors/receipts.
     */
    public function getCostBreakdown(InventoryItem $item): array
    {
        $lines = PalletLine::where('inventory_item_id', $item->id)
            ->with(['pallet.vendor', 'pallet'])
            ->get();

        $breakdown = [];
        foreach ($lines as $line) {
            $vendor = $line->pallet?->vendor;
            $vendorKey = $vendor ? "{$vendor->id}:{$vendor->name}" : 'unknown';

            if (!isset($breakdown[$vendorKey])) {
                $breakdown[$vendorKey] = [
                    'vendor_id' => $vendor?->id,
                    'vendor_name' => $vendor?->name ?? 'Unknown',
                    'receipts' => [],
                    'total_qty' => 0,
                    'weighted_total_cost' => 0,
                    'average_cost' => 0,
                ];
            }

            $qty = (float) $line->case_count * (float) $line->quantity_per_case;
            $unitCost = (float) ($line->unit_cost ?? 0);
            $totalCost = $qty * $unitCost;

            $breakdown[$vendorKey]['receipts'][] = [
                'pallet_reference' => $line->pallet->reference,
                'received_date' => $line->pallet->received_date,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'line_number' => $line->line_number,
            ];

            $breakdown[$vendorKey]['total_qty'] += $qty;
            $breakdown[$vendorKey]['weighted_total_cost'] += $totalCost;
        }

        // Calculate vendor-specific average costs
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
    public function getCostTrend(InventoryItem $item, int $limit = 10): array
    {
        $lines = PalletLine::where('inventory_item_id', $item->id)
            ->with('pallet')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $lines->map(fn ($line) => [
            'date' => $line->pallet->received_date,
            'vendor' => $line->pallet->vendor?->name ?? 'Unknown',
            'unit_cost' => (float) $line->unit_cost,
            'quantity' => (float) $line->case_count * (float) $line->quantity_per_case,
            'total_cost' => (float) $line->case_count * (float) $line->quantity_per_case * (float) $line->unit_cost,
        ])->toArray();
    }

    /**
     * Estimate total inventory value using average costs.
     */
    public function calculateInventoryValue(InventoryItem $item): float
    {
        $totalQty = $item->totalQuantity();
        $avgCost = (float) ($item->average_cost ?? 0);

        return $totalQty * $avgCost;
    }

    /**
     * Get all items with prices significantly different from average.
     * Useful for finding pricing anomalies or data entry errors.
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

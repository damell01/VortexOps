<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\PalletLine;
use App\Models\Product;

/**
 * Single source of truth for FIFO lot costing.
 *
 * Every place stock arrives at a known cost opens a lot here; every place
 * stock genuinely leaves (sold, given away, damaged beyond use — not just
 * moved to another location) consumes the oldest lots first. This replaces
 * three different "recompute the average" implementations that used to
 * disagree with each other (a lifetime-cumulative WAC, and two separate
 * full-recomputes from PalletLine history using different quantity bases).
 *
 * products.average_cost stays the same persisted column every existing
 * reader already knows — this only changes what feeds it: the weighted
 * average of what a product's currently on-hand stock actually cost, not
 * everything ever bought.
 */
class InventoryLotService
{
    /**
     * Open a lot for stock arriving at a known cost.
     *
     * A qty or cost of zero (or less) creates no lot — the same "skip cost
     * math on an unknown/free receipt" convention every intake path already
     * used, since a costless lot would only corrupt the average. The
     * caller's own bookkeeping (e.g. total_units_received) is untouched by
     * this call; that stays a separate, lifetime-volume concern.
     */
    public function open(Product $item, float $qty, float $unitCost, array $attributes = []): ?InventoryLot
    {
        if ($qty <= 0 || $unitCost <= 0) {
            return null;
        }

        $lot = InventoryLot::create(array_merge([
            'product_id'         => $item->id,
            'quantity'           => $qty,
            'unit_cost'          => $unitCost,
            'remaining_quantity' => $qty,
            'source'             => InventoryLot::SOURCE_RECEIVED,
            'status'             => InventoryLot::STATUS_ACTIVE,
            'received_at'        => now(),
        ], $attributes));

        $this->recompute($item);

        return $lot;
    }

    /**
     * Deplete the oldest active lots first for stock that has genuinely
     * left — a sale, a giveaway, damage, a physical-count shrinkage.
     *
     * Capped at whatever the lots actually cover: a gap between
     * InventoryStock and lot coverage (stock received before this system
     * existed, or before a backfill has run) is a data-quality fact to
     * reconcile later, never a reason to block a real operation.
     */
    public function consume(Product $item, float $qty): void
    {
        if ($qty <= 0) {
            return;
        }

        $remaining = $qty;

        // Locked and re-queried here rather than through Product::activeLotsFifo()
        // so concurrent depletions (a show sale and a physical count landing at
        // the same instant) can't both read the same remaining_quantity and
        // over-consume the lot between them.
        $lots = InventoryLot::where('product_id', $item->id)
            ->where('status', InventoryLot::STATUS_ACTIVE)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $remaining -= $lot->deduct($remaining);
        }

        $this->recompute($item);
    }

    /**
     * Recompute and persist average_cost from what's currently on hand.
     *
     * Left untouched when every lot is depleted (sold out) rather than reset
     * to 0 — Product::costBasis() promises "the cost to measure a sale price
     * against," and the last real price paid is a more honest answer than
     * zero until new stock arrives and opens a fresh lot.
     */
    public function recompute(Product $item): void
    {
        $totals = InventoryLot::where('product_id', $item->id)
            ->where('status', InventoryLot::STATUS_ACTIVE)
            ->where('remaining_quantity', '>', 0)
            ->selectRaw('SUM(remaining_quantity * unit_cost) as val, SUM(remaining_quantity) as qty')
            ->first();

        if ($totals && (float) $totals->qty > 0) {
            $item->forceFill([
                'average_cost' => round((float) $totals->val / (float) $totals->qty, 4),
            ])->save();
        }
    }

    /**
     * Undo a receipt's contribution to cost when its pallet is archived.
     */
    public function releaseForPalletLine(PalletLine $line): void
    {
        $productIds = InventoryLot::where('pallet_line_id', $line->id)->pluck('product_id')->unique();

        InventoryLot::where('pallet_line_id', $line->id)->delete();

        foreach ($productIds as $productId) {
            $product = Product::find($productId);

            if ($product) {
                $this->recompute($product);
            }
        }
    }
}

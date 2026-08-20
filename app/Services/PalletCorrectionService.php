<?php

namespace App\Services;

use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryItem;
use App\Models\PalletLine;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fixing what a pallet brought in, after it has been received.
 *
 * A pallet is typed from a packing slip and scanned in a warehouse, so it is
 * wrong sometimes: a cost mistyped, a vendor's wording nobody uses, a miscount,
 * a line put in the wrong place. Those corrections have to happen somewhere,
 * and the tempting place is straight on the row — set the number to what it
 * should have been and move on.
 *
 * That is exactly what must not happen for quantity. Stock is a ledger: the
 * total is the sum of what moved, and a receipt rewritten in place leaves the
 * pallet claiming one thing and the movement history another, with no record of
 * which is the correction. So a quantity change here is an adjustment — the
 * same one the stock screen would write, with a reason naming the pallet.
 *
 * Cost is different: it is a fact about a purchase, not a movement, so
 * correcting it means the original figure was wrong rather than that something
 * happened. It is rewritten, and the product's weighted average is recomputed
 * from what the change actually implies.
 */
class PalletCorrectionService
{
    /**
     * Rename the product a line brought in.
     *
     * The line keeps what the slip said. That is a record of the paperwork and
     * changing it would lose the only trace of why the product was called what
     * it was.
     */
    public function renameItem(PalletLine $line, string $name): Product
    {
        $item = $line->inventoryItem;

        if (! $item) {
            throw new RuntimeException('That line is not linked to an inventory item yet.');
        }

        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('An item needs a name.');
        }

        $item->update(['name' => $name]);

        return $item->fresh();
    }

    /**
     * Correct what this line's units cost.
     *
     * The product's weighted average is rebuilt by removing the old figure's
     * contribution and adding the new one, rather than by averaging the two —
     * which would leave the mistake permanently baked into the number at a
     * smaller weight. Units already sold have gone out at the old cost and
     * cannot be revisited from here; this fixes the valuation going forward,
     * which is what a cost correction can honestly claim to do.
     */
    public function correctUnitCost(PalletLine $line, float $unitCost): PalletLine
    {
        if ($unitCost < 0) {
            throw new RuntimeException('A cost cannot be negative.');
        }

        return DB::transaction(function () use ($line, $unitCost) {
            $item = $line->inventoryItem;
            $old  = (float) $line->unit_cost;

            $line->update(['unit_cost' => $unitCost]);

            if (! $item || $old === $unitCost) {
                return $line->fresh();
            }

            $units = $line->cases()->where('status', '!=', 'expected')->count()
                * (float) $line->quantity_per_case;

            if ($units <= 0) {
                return $line->fresh();
            }

            $fresh   = Product::lockForUpdate()->findOrFail($item->id);
            $total   = (float) $fresh->total_units_received;
            $average = (float) $fresh->average_cost;

            // Back the wrong figure out of the average, then put the right one
            // in at the same weight.
            $value = ($total * $average) - ($units * $old) + ($units * $unitCost);

            $fresh->update([
                'average_cost' => $total > 0 ? round(max(0, $value / $total), 4) : $unitCost,
                'unit_cost'    => $unitCost,
            ]);

            return $line->fresh();
        });
    }

    /**
     * The same product, typed as InventoryItem.
     *
     * InventoryItem is the back-compat alias for Product and the stock services
     * type-hint it, while pallet line relations return the base class. Resolving
     * here keeps that seam in one place instead of at every call site.
     */
    private function stockItem(Product $product): InventoryItem
    {
        return InventoryItem::findOrFail($product->id);
    }

    /**
     * Correct how many units of this line are actually in stock.
     *
     * Written as an adjustment rather than by editing the receipt, so the
     * ledger still adds up and the correction is visible as a correction. The
     * reason names the pallet, because six months later "why did this drop by
     * two" is the only question anyone will be asking.
     */
    public function correctQuantity(PalletLine $line, float $newQuantity, ?string $reason = null): InventoryMovement
    {
        if ($newQuantity < 0) {
            throw new RuntimeException('A quantity cannot be negative.');
        }

        $item     = $line->inventoryItem;
        $location = $line->location;

        if (! $item || ! $location) {
            throw new RuntimeException('That line is not linked to an item and location yet.');
        }

        $pallet = $line->pallet;

        return app(InventoryService::class)->adjustStock(
            $this->stockItem($item),
            $location,
            $newQuantity,
            $reason ?: 'Corrected while reviewing ' . ($pallet?->displayName() ?? 'a pallet'),
        );
    }

    /**
     * Move what this line brought in to a different location.
     *
     * A transfer, not a rewrite of the line: the stock is somewhere and is
     * being moved somewhere else, which is a thing that happened. The line's
     * own location follows so the pallet keeps describing where its goods went.
     */
    public function moveToLocation(PalletLine $line, InventoryLocation $destination, ?string $reason = null): ?InventoryMovement
    {
        $item = $line->inventoryItem;
        $from = $line->location;

        if (! $item || ! $from) {
            throw new RuntimeException('That line is not linked to an item and location yet.');
        }

        if ($from->id === $destination->id) {
            return null;
        }

        return DB::transaction(function () use ($line, $item, $from, $destination, $reason) {
            $units = (float) (InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $from->id)
                ->value('quantity') ?? 0);

            $lineUnits = $line->cases()->where('status', '!=', 'expected')->count()
                * (float) $line->quantity_per_case;

            // Never move more than is actually sitting there. Another pallet
            // may have taken some of it, and a transfer that overdraws the
            // source is how a location ends up with negative stock.
            $moving = min($units, $lineUnits);

            $line->update(['inventory_location_id' => $destination->id]);

            if ($moving <= 0) {
                return null;
            }

            return app(InventoryService::class)->transferStock(
                $this->stockItem($item),
                $from,
                $destination,
                $moving,
                $reason ?: 'Moved while reviewing ' . ($line->pallet?->displayName() ?? 'a pallet'),
            );
        });
    }

    /**
     * What a pallet actually brought in, line by line.
     *
     * Reads from the lines rather than from stock, because stock is the running
     * total for a product across every pallet and this question is about one of
     * them. An item received twice on the same pallet appears once per line,
     * which is right: they were separate receipts at possibly separate costs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function itemsFrom(\App\Models\Pallet $pallet): array
    {
        $pallet->loadMissing(['lines.inventoryItem', 'lines.location', 'lines.cases']);

        return $pallet->lines->map(function (PalletLine $line) {
            $receivedCases = $line->cases->where('status', '!=', 'expected')->count();
            $perCase       = (float) $line->quantity_per_case;
            $item          = $line->inventoryItem;

            $inStock = $item && $line->inventory_location_id
                ? (float) (InventoryStock::where('inventory_item_id', $item->id)
                    ->where('inventory_location_id', $line->inventory_location_id)
                    ->value('quantity') ?? 0)
                : 0.0;

            return [
                'line_id'         => $line->id,
                'line_number'     => (int) $line->line_number,
                'item_id'         => $item?->id,
                'name'            => $item?->name ?? $line->description,
                'staged_as'       => $line->description,
                'sku'             => $item?->sku,
                'barcode'         => $item?->barcode,
                'expected_cases'  => (int) $line->case_count,
                'received_cases'  => $receivedCases,
                'per_case'        => $perCase,
                'units'           => $receivedCases * $perCase,
                'in_stock'        => $inStock,
                'unit_cost'       => (float) $line->unit_cost,
                'line_total'      => $receivedCases * $perCase * (float) $line->unit_cost,
                'location_id'     => $line->inventory_location_id,
                'location'        => $line->location?->name,
                'received_at'     => $line->cases
                    ->where('status', '!=', 'expected')
                    ->max('received_at'),
                'complete'        => $receivedCases >= (int) $line->case_count,
                'linked'          => $item !== null,
            ];
        })->sortBy('line_number')->values()->all();
    }
}

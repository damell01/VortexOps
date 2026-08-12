<?php

namespace App\Services;

use App\Models\InventoryCase;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReceivingService
{
    /**
     * Map a pallet line to an existing inventory item (or create a new one).
     * Call this during the manifest review step before receiving cases.
     */
    public function mapLine(PalletLine $line, Product $item, InventoryLocation $location): void
    {
        $line->update([
            'inventory_item_id'    => $item->id,
            'inventory_location_id' => $location->id,
        ]);
    }

    /**
     * Create an InventoryItem on the fly and map it to a pallet line.
     */
    public function createAndMapItem(PalletLine $line, array $itemData, InventoryLocation $location): Product
    {
        $item = Product::create(array_merge($itemData, [
            'unit_cost'    => $line->unit_cost > 0 ? (float) $line->unit_cost : 0,
            'average_cost' => $line->unit_cost > 0 ? (float) $line->unit_cost : 0,
            'is_active'    => true,
        ]));

        $this->mapLine($line, $item, $location);

        return $item;
    }

    /**
     * Receive all expected cases for the pallet line whose item matches the
     * given barcode or SKU. Used by the scanner's Receive Pallet mode.
     * Transitions pending items to received status.
     *
     * @throws RuntimeException if no matching line is found, line is unmapped,
     *                          or all cases for that line are already received.
     */
    public function receiveLineByItemCode(Pallet $pallet, string $code): array
    {
        $pallet->load(['lines.inventoryItem', 'lines.cases']);

        // Product::findByScan() also checks product_identities, so a SKU with
        // several barcodes registered (e.g. different vendor packaging for a
        // restock) resolves to the same line here — this used to only compare
        // against the item's single barcode/sku column directly.
        $scannedItem = Product::findByScan(trim($code));

        $line = $scannedItem
            ? $pallet->lines->first(fn (PalletLine $line) => $line->inventory_item_id === $scannedItem->id)
            : null;

        if (! $line) {
            throw new RuntimeException("No line in this pallet matches \"{$code}\". Check the item's SKU or barcode.");
        }

        if (! $line->isFullyMapped()) {
            throw new RuntimeException("Line #{$line->line_number} ({$line->inventoryItem?->name}) is not yet mapped to an item and location — map it in the pallet detail before scanning.");
        }

        $expectedCount = $line->cases->where('status', 'expected')->count();
        if ($expectedCount === 0) {
            // Check whether cases have ever been generated (may need stub generation)
            $totalCases = (int) $line->case_count;
            if ($totalCases > 0 && $line->cases->count() === 0) {
                // No stubs generated yet — receiveAllCasesForLine will generate + receive them
            } else {
                throw new RuntimeException("All cases for {$line->inventoryItem?->name} (line #{$line->line_number}) are already received.");
            }
        }

        $received = $this->receiveAllCasesForLine($line);

        // Update pallet line status from pending to received
        $line->update(['line_status' => 'received']);

        return [
            'line_id'        => $line->id,
            'line_number'    => $line->line_number,
            'item_name'      => $line->inventoryItem->name,
            'cases_received' => $received,
        ];
    }

    /**
     * Receive a single case by barcode. Looks up the case, marks it received,
     * updates stock, and recalculates average cost.
     *
     * @throws RuntimeException if barcode not found or case already received.
     */
    public function receiveCaseByBarcode(string $barcode): InventoryCase
    {
        $case = InventoryCase::findByBarcode($barcode);

        if (! $case) {
            throw new RuntimeException("No case found with barcode: {$barcode}");
        }

        if ($case->status !== 'expected') {
            throw new RuntimeException("Case {$barcode} has already been received.");
        }

        return $this->receiveCase($case);
    }

    /**
     * Receive a specific case, crediting its contents to the line's mapped location.
     *
     * @throws RuntimeException if line is not mapped to an item and location.
     */
    public function receiveCase(InventoryCase $case): InventoryCase
    {
        $line = $case->palletLine()->with(['inventoryItem', 'location'])->firstOrFail();

        if (! $line->inventory_item_id || ! $line->inventory_location_id) {
            throw new RuntimeException("Pallet line #{$line->line_number} is not yet mapped to an item and location. Map it before receiving.");
        }

        $item     = $line->inventoryItem;
        $location = $line->location;
        $qty      = (float) $line->quantity_per_case;
        $unitCost = (float) $line->unit_cost;

        return DB::transaction(function () use ($case, $item, $location, $qty, $unitCost, $line) {
            $case->update([
                'status'            => 'received',
                'quantity_received' => $qty,
                'received_at'       => now(),
                'received_by'       => Auth::id(),
            ]);

            $this->creditStock($item, $location, $qty, $unitCost, $case, $line);

            return $case->fresh();
        });
    }

    /**
     * Bulk-receive all unmapped/uncreated cases for a pallet line.
     * Creates the expected cases if they don't exist yet, then receives them all
     * in a single transaction (one stock update + one WAC recalculation instead
     * of one-per-case).
     *
     * @throws RuntimeException if line is not mapped.
     */
    public function receiveAllCasesForLine(PalletLine $line, float $allocatedShippingCost = 0): int
    {
        if (! $line->inventory_item_id || ! $line->inventory_location_id) {
            throw new RuntimeException("Line #{$line->line_number} must be mapped to an item and location before bulk-receiving.");
        }

        $expectedCases = $line->cases()->where('status', 'expected')->get();

        if ($expectedCases->isEmpty()) {
            $this->generateExpectedCases($line);
            $expectedCases = $line->cases()->where('status', 'expected')->get();
        }

        if ($expectedCases->isEmpty()) {
            return 0;
        }

        return $this->receiveCaseBatch($line, $expectedCases, $allocatedShippingCost);
    }

    /**
     * Receive a collection of cases belonging to the same line in one transaction.
     * Avoids N separate transactions, N PalletLine reloads, and N WAC updates.
     */
    private function receiveCaseBatch(PalletLine $line, \Illuminate\Support\Collection $cases, float $allocatedShippingCost = 0): int
    {
        $line->loadMissing(['inventoryItem', 'location']);

        $item     = $line->inventoryItem;
        $location = $line->location;
        $qty      = (float) $line->quantity_per_case;
        $unitCost = (float) $line->unit_cost;
        $count    = $cases->count();
        $now      = now();
        $userId   = Auth::id();
        $totalQty = $qty * $count;

        // Allocate shipping cost per unit
        $shippingCostPerUnit = $totalQty > 0 ? $allocatedShippingCost / $totalQty : 0;
        $totalUnitCost = $unitCost + $shippingCostPerUnit;

        return DB::transaction(function () use ($cases, $item, $location, $qty, $unitCost, $line, $now, $userId, $count, $totalQty, $allocatedShippingCost, $shippingCostPerUnit, $totalUnitCost) {
            // Bulk-mark all cases received
            InventoryCase::whereIn('id', $cases->pluck('id'))
                ->update([
                    'status'            => 'received',
                    'quantity_received' => $qty,
                    'received_at'       => $now,
                    'received_by'       => $userId,
                ]);

            // Single stock increment for the whole batch
            $stock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
                ['quantity' => 0]
            );
            $stock->increment('quantity', $totalQty);

            // Bulk-insert movement records (movements are the audit trail)
            InventoryMovement::insert($cases->map(fn ($case) => [
                'inventory_item_id' => $item->id,
                'from_location_id'  => null,
                'to_location_id'    => $location->id,
                'quantity'          => $qty,
                'movement_type'     => 'opening',
                'reason'            => "Received via pallet #{$line->pallet_id}, line #{$line->line_number}" . ($allocatedShippingCost > 0 ? " (shipping: \${$allocatedShippingCost})" : ""),
                'reference_type'    => 'inventory_case',
                'reference_id'      => $case->id,
                'created_by'        => $userId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ])->toArray());

            // Single WAC recalculation for the entire batch, including allocated shipping cost
            $this->recalculateAverageCost($item, $totalQty, $totalUnitCost);

            return $count;
        });
    }

    /**
     * Receive an entire pallet at once (all mapped lines, all cases).
     * All lines are processed inside a single outer transaction so a mid-pallet
     * failure does not leave partial stock committed.
     * Updates all line statuses to 'received'.
     *
     * @throws RuntimeException if any line is unmapped.
     */
    public function receivePallet(Pallet $pallet): array
    {
        $pallet->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);

        $unmapped = $pallet->lines->filter(fn ($l) => ! $l->isFullyMapped());
        if ($unmapped->isNotEmpty()) {
            $lineNums = $unmapped->pluck('line_number')->join(', ');
            throw new RuntimeException("Lines {$lineNums} are not fully mapped. Map all lines before bulk-receiving the pallet.");
        }

        return DB::transaction(function () use ($pallet) {
            $received = 0;

            // Allocate shipping cost across lines based on their quantities
            $totalLineQuantity = (float) $pallet->lines->sum(fn ($l) => (float) $l->quantity_per_case * (int) $l->case_count);
            $shippingCost = (float) ($pallet->shipping_cost ?? 0);

            foreach ($pallet->lines as $line) {
                $lineQuantity = (float) $line->quantity_per_case * (int) $line->case_count;
                $allocatedShipping = $totalLineQuantity > 0 ? ($shippingCost * $lineQuantity / $totalLineQuantity) : 0;

                $received += $this->receiveAllCasesForLine($line, $allocatedShipping);
                // Update line status to received
                $line->update(['line_status' => 'received']);
            }

            $pallet->update(['status' => 'received']);

            return ['cases_received' => $received, 'lines_processed' => $pallet->lines->count()];
        });
    }

    /**
     * Generate the expected InventoryCase stubs for a pallet line using a single
     * bulk insert instead of one INSERT per case.
     */
    public function generateExpectedCases(PalletLine $line): void
    {
        $existing = $line->cases()->count();
        $needed   = (int) $line->case_count - $existing;

        if ($needed <= 0) {
            return;
        }

        $now  = now()->toDateTimeString();
        $rows = [];
        for ($i = 0; $i < $needed; $i++) {
            $rows[] = [
                'pallet_line_id' => $line->id,
                'barcode'        => null,
                'status'         => 'expected',
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        InventoryCase::insert($rows);
    }

    /**
     * Credit stock and recalculate weighted average cost.
     */
    private function creditStock(Product $item, InventoryLocation $location, float $qty, float $unitCost, InventoryCase $case, PalletLine $line): void
    {
        try {
            $stock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
                ['quantity' => 0]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // Concurrent receive beat us to the INSERT; re-fetch the now-existing row
            $stock = InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $location->id)
                ->firstOrFail();
        }

        $stock->increment('quantity', $qty);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'from_location_id'  => null,
            'to_location_id'    => $location->id,
            'quantity'          => $qty,
            'movement_type'     => 'opening',
            'reason'            => "Received via pallet #{$line->pallet_id}, line #{$line->line_number}",
            'reference_type'    => 'inventory_case',
            'reference_id'      => $case->id,
            'created_by'        => Auth::id(),
        ]);

        $this->recalculateAverageCost($item, $qty, $unitCost);
    }

    /**
     * Weighted average cost: (existing_qty * existing_avg + new_qty * new_cost) / total_qty
     * If unit_cost is 0, skip the cost update (free samples, etc.).
     */
    public function recalculateAverageCost(Product $item, float $incomingQty, float $incomingUnitCost): void
    {
        if ($incomingUnitCost <= 0) {
            // Use atomic increment and sync the in-memory model from the return value
            DB::table('products')
                ->where('id', $item->id)
                ->increment('total_units_received', $incomingQty);
            $item->total_units_received = (float) $item->total_units_received + $incomingQty;
            return;
        }

        // Lock the row so concurrent receipts (barcode scan + batch receive) don't race on WAC.
        // This must be called from within an existing DB::transaction (receiveCaseBatch already provides one).
        $fresh = Product::lockForUpdate()->findOrFail($item->id);

        $existingQty = (float) $fresh->total_units_received;
        $existingAvg = (float) $fresh->average_cost;
        $totalQty    = $existingQty + $incomingQty;

        $newAvg = $totalQty > 0
            ? (($existingQty * $existingAvg) + ($incomingQty * $incomingUnitCost)) / $totalQty
            : $incomingUnitCost;

        $fresh->update([
            'average_cost'         => round($newAvg, 4),
            'total_units_received' => $totalQty,
        ]);

        // Sync in-memory model so callers that chain multiple lines see fresh values
        $item->average_cost         = $fresh->average_cost;
        $item->total_units_received = $fresh->total_units_received;
    }
}

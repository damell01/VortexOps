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
     * What finishing this pallet would mean, without doing any of it.
     *
     * Receiving is the point where money and quantities become permanent — it
     * moves stock and rewrites what every unit of that product is valued at —
     * so it is worth being able to look at first. This answers the questions
     * you would otherwise only find out afterwards: what actually turned up
     * against what was expected, what is still outstanding, what cannot be
     * received at all, and what each item will cost once the shipping and fees
     * are spread over it.
     *
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     blockers: array<int, string>,
     *     totals: array<string, float|int>,
     *     can_finish: bool
     * }
     */
    public function reviewPallet(Pallet $pallet): array
    {
        $pallet->loadMissing(['lines.inventoryItem', 'lines.location', 'lines.cases']);

        $extras = $pallet->landedCostExtras();

        // The same basis receivePallet() allocates on, so the projection below
        // matches what actually happens rather than approximating it.
        $totalExpectedUnits = (float) $pallet->lines->sum(
            fn (PalletLine $l) => (float) $l->quantity_per_case * (int) $l->case_count
        );

        $lines    = [];
        $blockers = [];

        $confirmedUnits = 0.0;
        $expectedUnits  = 0.0;
        $goods          = 0.0;

        foreach ($pallet->lines as $line) {
            $expectedCases  = (int) $line->case_count;
            $confirmedCases = $line->cases->where('status', '!=', 'expected')->count();
            $perCase        = (float) $line->quantity_per_case;

            $lineExpectedUnits  = $perCase * $expectedCases;
            $lineConfirmedUnits = $perCase * $confirmedCases;

            $expectedUnits  += $lineExpectedUnits;
            $confirmedUnits += $lineConfirmedUnits;
            $goods          += $lineExpectedUnits * (float) $line->unit_cost;

            if (! $line->isFullyMapped()) {
                $blockers[] = "\"{$line->description}\" is not mapped to an item and location.";
            }

            $lines[] = [
                'id'              => $line->getKey(),
                'name'            => $line->inventoryItem?->name ?? $line->description,
                'sku'             => $line->inventoryItem?->sku,
                'location'        => $line->location?->name,
                'expected_cases'  => $expectedCases,
                'confirmed_cases' => $confirmedCases,
                'variance_cases'  => $confirmedCases - $expectedCases,
                'units'           => $lineConfirmedUnits,
                'unit_cost'       => (float) $line->unit_cost,
                'mapped'          => $line->isFullyMapped(),
                // What this line's units will actually be valued at: the
                // invoice price plus its share of shipping and fees.
                'landed_unit_cost' => (float) $line->unit_cost + ($totalExpectedUnits > 0
                    ? $extras / $totalExpectedUnits
                    : 0),
                'projected_average_cost' => $this->projectAverageCost(
                    $line->inventoryItem,
                    $lineConfirmedUnits,
                    (float) $line->unit_cost + ($totalExpectedUnits > 0 ? $extras / $totalExpectedUnits : 0),
                ),
            ];
        }

        if ($pallet->lines->isEmpty()) {
            $blockers[] = 'Nothing has been staged on this pallet yet.';
        }

        return [
            'lines'    => $lines,
            'blockers' => $blockers,
            'totals'   => [
                'expected_units'  => $expectedUnits,
                'confirmed_units' => $confirmedUnits,
                'short_units'     => max(0, $expectedUnits - $confirmedUnits),
                'goods'           => round($goods, 2),
                'extras'          => round($extras, 2),
                'landed'          => round($goods + $extras, 2),
            ],
            'can_finish' => $blockers === [],
        ];
    }

    /**
     * Close a pallet with only what was actually scanned in.
     *
     * The counterpart to receivePallet(), which takes in every expected case
     * whether or not it turned up. When a delivery is short, that would credit
     * stock that does not exist — so this closes the pallet and leaves the
     * outstanding cases expected, which is the honest record of a part
     * delivery.
     *
     * @return array{received_cases: int, outstanding_cases: int}
     */
    public function closePalletShort(Pallet $pallet): array
    {
        $pallet->loadMissing('lines.cases');

        $received    = 0;
        $outstanding = 0;

        foreach ($pallet->lines as $line) {
            $in  = $line->cases->where('status', '!=', 'expected')->count();
            $out = $line->cases->where('status', 'expected')->count();

            $received    += $in;
            $outstanding += $out;

            // A line that fully arrived is done; one that did not stays
            // pending, so what is missing is still visible afterwards.
            $line->update(['line_status' => $out === 0 && $in > 0 ? 'received' : 'pending']);
        }

        $pallet->update(['status' => 'received']);

        return ['received_cases' => $received, 'outstanding_cases' => $outstanding];
    }

    /**
     * The weighted average an item would carry after taking this much in.
     *
     * Mirrors recalculateAverageCost() rather than calling it, because the
     * whole point here is to show the number without writing it.
     */
    private function projectAverageCost(?Product $item, float $incomingQty, float $incomingUnitCost): ?float
    {
        if (! $item || $incomingQty <= 0 || $incomingUnitCost <= 0) {
            return null;
        }

        $existingQty = (float) $item->total_units_received;
        $existingAvg = (float) $item->average_cost;
        $totalQty    = $existingQty + $incomingQty;

        if ($totalQty <= 0) {
            return null;
        }

        return round(
            (($existingQty * $existingAvg) + ($incomingQty * $incomingUnitCost)) / $totalQty,
            4,
        );
    }

    /**
     * Scan an item code and confirm one case of it off a staged pallet.
     *
     * The difference from receiveLineByItemCode() is the unit of work: that one
     * takes the whole line at once, which is right when you already know the
     * shipment is complete. This takes a single case, so scanning is a running
     * count against what was expected — three of five cases confirmed, two
     * still outstanding — which is what makes a part-delivered pallet
     * describable rather than all-or-nothing.
     *
     * @return array{line: PalletLine, item: string, received: int, expected: int, complete: bool}
     *
     * @throws RuntimeException if nothing on this pallet matches, the line is
     *                          unmapped, or every case is already in.
     */
    public function receiveOneCaseByItemCode(Pallet $pallet, string $code): array
    {
        $pallet->load(['lines.inventoryItem', 'lines.cases']);

        // findByScan() also searches product_identities, so a SKU registered
        // under several barcodes still resolves to its line.
        $scanned = Product::findByScan(trim($code));

        $line = $scanned
            ? $pallet->lines->first(fn (PalletLine $l) => $l->inventory_item_id === $scanned->id)
            : null;

        if (! $line) {
            throw new RuntimeException("Nothing on this pallet matches \"{$code}\".");
        }

        if (! $line->isFullyMapped()) {
            throw new RuntimeException("\"{$line->description}\" is not mapped to an item and location yet.");
        }

        if ($line->cases()->count() === 0) {
            $this->generateExpectedCases($line);
        }

        $case = $line->cases()->where('status', 'expected')->first();

        if (! $case) {
            throw new RuntimeException("All {$line->case_count} cases of \"{$line->inventoryItem?->name}\" are already received.");
        }

        $this->receiveCase($case);

        $received = $line->fresh()->receivedCases();
        $expected = (int) $line->case_count;

        if ($received >= $expected) {
            $line->update(['line_status' => 'received']);
        }

        return [
            'line'     => $line->fresh(),
            'item'     => $line->inventoryItem?->name ?? $line->description,
            'received' => $received,
            'expected' => $expected,
            'complete' => $received >= $expected,
        ];
    }

    /**
     * Point a staged line at real inventory by scanning it.
     *
     * Staging is done off the paperwork, so a line is usually just a name —
     * the product may not exist yet, and typing one up before the pallet has
     * even arrived is work done twice. When the box is in hand its barcode
     * settles it: an existing product claims the line, and an unrecognised
     * code becomes a new product carrying that code, named from what was
     * staged. Either way the line is mapped and ready to receive.
     *
     * The location has to be supplied because nothing on a staged line implies
     * one, and receiving without it would credit stock nowhere.
     *
     * @return array{line: PalletLine, item: Product, created: bool}
     */
    public function linkLineByScan(PalletLine $line, string $code, InventoryLocation $location): array
    {
        $code = trim($code);

        if ($code === '') {
            throw new RuntimeException('Scan or type a code first.');
        }

        if ($line->inventory_item_id) {
            throw new RuntimeException("\"{$line->description}\" is already linked to " . ($line->inventoryItem?->name ?? 'an item') . '.');
        }

        $existing = Product::findByScan($code);

        if ($existing) {
            // A code already on the pallet under another line would make every
            // later scan ambiguous, so it is refused rather than silently
            // pointing two lines at one product.
            $clash = $line->pallet->lines()
                ->where('id', '!=', $line->id)
                ->where('inventory_item_id', $existing->id)
                ->exists();

            if ($clash) {
                throw new RuntimeException("{$existing->name} is already on another line of this pallet.");
            }

            $this->mapLine($line, $existing, $location);

            return ['line' => $line->fresh(), 'item' => $existing, 'created' => false];
        }

        $product = Product::create([
            'name'         => $line->description,
            'barcode'      => $code,
            // What was recorded at staging, when somebody was looking at it.
            'is_container' => (bool) $line->is_container,
            'unit_cost'    => (float) ($line->unit_cost ?? 0),
            'is_active'    => true,
        ]);

        $this->mapLine($line, $product, $location);

        return ['line' => $line->fresh(), 'item' => $product, 'created' => true];
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
    /**
     * @param float $allocatedExtraCost This line's share of the pallet's
     *        shipping and payment fees, spread over the units it brings in.
     */
    public function receiveAllCasesForLine(PalletLine $line, float $allocatedExtraCost = 0): int
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

        return $this->receiveCaseBatch($line, $expectedCases, $allocatedExtraCost);
    }

    /**
     * Receive a collection of cases belonging to the same line in one transaction.
     * Avoids N separate transactions, N PalletLine reloads, and N WAC updates.
     */
    private function receiveCaseBatch(PalletLine $line, \Illuminate\Support\Collection $cases, float $allocatedExtraCost = 0): int
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

        // Landed cost: the invoice price plus this unit's share of shipping
        // and payment fees. This is what the weighted average is built from.
        $extraCostPerUnit = $totalQty > 0 ? $allocatedExtraCost / $totalQty : 0;
        $totalUnitCost    = $unitCost + $extraCostPerUnit;

        return DB::transaction(function () use ($cases, $item, $location, $qty, $unitCost, $line, $now, $userId, $count, $totalQty, $allocatedExtraCost, $extraCostPerUnit, $totalUnitCost) {
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
                'reason'            => "Received via pallet #{$line->pallet_id}, line #{$line->line_number}" . ($allocatedExtraCost > 0 ? " (shipping + fees: $" . number_format($allocatedExtraCost, 2) . ")" : ""),
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

            // Shipping and payment fees are costs of getting the stock here
            // rather than costs of any one line, so they are spread across the
            // units received. By quantity, not by line: ten cheap units and one
            // expensive one shipped in the same box cost the same to ship.
            $totalLineQuantity = (float) $pallet->lines->sum(fn ($l) => (float) $l->quantity_per_case * (int) $l->case_count);
            $extraCost = $pallet->landedCostExtras();

            foreach ($pallet->lines as $line) {
                $lineQuantity = (float) $line->quantity_per_case * (int) $line->case_count;
                $allocatedExtra = $totalLineQuantity > 0 ? ($extraCost * $lineQuantity / $totalLineQuantity) : 0;

                $received += $this->receiveAllCasesForLine($line, $allocatedExtra);
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

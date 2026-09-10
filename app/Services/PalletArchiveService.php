<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class PalletArchiveService
{
    /** Preview the inventory impact before a completed pallet is archived. */
    public function preview(Pallet $pallet): array
    {
        $pallet->loadMissing(['lines.inventoryItem', 'lines.location', 'lines.cases']);

        $effects = [];
        $deleteCandidates = [];
        $retained = [];

        foreach ($pallet->lines as $line) {
            if (! $line->inventory_item_id) continue;

            $qty = $this->receivedQuantityForLine($line);
            if ($qty <= 0) continue;

            $locationId = $line->inventory_location_id;
            $available = 0.0;

            if ($locationId) {
                $stock = InventoryStock::query()
                    ->where('inventory_item_id', $line->inventory_item_id)
                    ->where('inventory_location_id', $locationId)
                    ->first();
                $available = max(0.0, (float) ($stock?->quantity ?? 0));
            }

            $reverseQty = min($qty, $available);
            $alreadyMovedQty = max(0.0, $qty - $reverseQty);

            $effects[] = [
                'line_id' => $line->id,
                'product_id' => $line->inventory_item_id,
                'item' => $line->inventoryItem?->name ?? $line->description,
                'location_id' => $locationId,
                'location' => $line->location?->name ?? 'Unknown location',
                'quantity' => $qty,
                'reverse_quantity' => $reverseQty,
                'already_moved_quantity' => $alreadyMovedQty,
            ];
        }

        foreach (collect($effects)->groupBy('product_id') as $productId => $rows) {
            $product = Product::find($productId);
            if (! $product) continue;

            $qtyFromPallet = (float) $rows->sum('quantity');
            if ($this->productCanBeArchivedWithPallet($product, $pallet, $qtyFromPallet)) {
                $deleteCandidates[] = ['id' => $product->id, 'name' => $product->name];
            } else {
                $retained[] = ['id' => $product->id, 'name' => $product->name];
            }
        }

        return [
            'effects' => $effects,
            'blockers' => [],
            'delete_candidates' => $deleteCandidates,
            'retained_products' => $retained,
            'can_archive' => true,
        ];
    }

    public function previewText(Pallet $pallet): string
    {
        $preview = $this->preview($pallet);
        $receivedQty = (float) collect($preview['effects'])->sum('quantity');
        $reverseQty = (float) collect($preview['effects'])->sum('reverse_quantity');
        $movedQty = (float) collect($preview['effects'])->sum('already_moved_quantity');
        $lines = count($preview['effects']);

        $text = "This will delete {$pallet->displayName()}. "
            . number_format($reverseQty, 2) . " inventory units still at the receiving location will be reversed across {$lines} line(s).";

        if ($movedQty > 0) {
            $text .= ' ' . number_format($movedQty, 2)
                . ' unit(s) from this receipt have already moved, sold, or been used. Those downstream inventory changes will be left untouched so deleting the pallet does not corrupt current stock.';
        }

        if ($receivedQty <= 0) {
            $text .= ' This pallet did not add any traceable inventory.';
        }

        if ($preview['delete_candidates'] !== []) {
            $names = collect($preview['delete_candidates'])->pluck('name')->join(', ');
            $text .= " Items created only for this pallet that are still safe to remove will also be archived: {$names}.";
        } else {
            $text .= ' Linked/existing inventory products will remain.';
        }

        $text .= ' This is undoable from Archived Pallets.';

        return $text;
    }

    /**
     * Archive a pallet even when part of the received stock has already moved.
     * Only stock that is still physically available at the original receiving
     * location is reversed. Downstream moves/sales are intentionally preserved.
     */
    public function archive(Pallet $pallet): array
    {
        $preview = $this->preview($pallet);

        return DB::transaction(function () use ($pallet, $preview) {
            foreach ($preview['effects'] as $effect) {
                $reverseQty = (float) ($effect['reverse_quantity'] ?? 0);

                if ($reverseQty > 0 && $effect['location_id']) {
                    $stock = InventoryStock::query()
                        ->where('inventory_item_id', $effect['product_id'])
                        ->where('inventory_location_id', $effect['location_id'])
                        ->lockForUpdate()
                        ->first();

                    $availableNow = max(0.0, (float) ($stock?->quantity ?? 0));
                    $actualReverse = min($reverseQty, $availableNow);

                    if ($actualReverse > 0 && $stock) {
                        $stock->decrement('quantity', $actualReverse);

                        InventoryMovement::create([
                            'inventory_item_id' => $effect['product_id'],
                            'from_location_id' => $effect['location_id'],
                            'to_location_id' => null,
                            'quantity' => $actualReverse,
                            'movement_type' => 'adjustment',
                            'reason' => "Deleted pallet #{$pallet->id} ({$pallet->displayName()}) — remaining receipt stock reversed",
                            'reference_type' => 'pallet_archive',
                            'reference_id' => $pallet->id,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                Product::query()->whereKey($effect['product_id'])->update([
                    'total_units_received' => DB::raw('GREATEST(0, total_units_received - ' . (float) $effect['quantity'] . ')'),
                ]);
            }

            $productIds = collect($preview['effects'])->pluck('product_id')->unique();
            $pallet->delete();

            foreach ($preview['delete_candidates'] as $candidate) {
                Product::find($candidate['id'])?->delete();
            }

            foreach ($productIds as $productId) {
                $this->recalculateReceiptCost((int) $productId);
            }

            return $preview;
        });
    }

    public function restore(Pallet $pallet): void
    {
        if (! $pallet->trashed()) return;

        DB::transaction(function () use ($pallet) {
            $pallet->restore();
            $pallet->load(['lines.inventoryItem' => fn ($q) => $q->withTrashed(), 'lines.location', 'lines.cases']);

            $archiveReversals = InventoryMovement::query()
                ->where('reference_type', 'pallet_archive')
                ->where('reference_id', $pallet->id)
                ->get()
                ->groupBy(fn ($movement) => $movement->inventory_item_id . ':' . ($movement->from_location_id ?? 0));

            $productIds = [];
            foreach ($pallet->lines as $line) {
                if (! $line->inventory_item_id) continue;

                $product = Product::withTrashed()->find($line->inventory_item_id);
                if (! $product) continue;
                if ($product->trashed()) $product->restore();

                $receivedQty = $this->receivedQuantityForLine($line);
                if ($receivedQty > 0) {
                    $product->increment('total_units_received', $receivedQty);
                }

                if ($line->inventory_location_id) {
                    $key = $product->id . ':' . $line->inventory_location_id;
                    $reversedQty = (float) ($archiveReversals->get($key)?->sum('quantity') ?? 0);

                    if ($reversedQty > 0) {
                        $stock = InventoryStock::firstOrCreate(
                            ['inventory_item_id' => $product->id, 'inventory_location_id' => $line->inventory_location_id],
                            ['quantity' => 0]
                        );
                        $stock->increment('quantity', $reversedQty);

                        InventoryMovement::create([
                            'inventory_item_id' => $product->id,
                            'from_location_id' => null,
                            'to_location_id' => $line->inventory_location_id,
                            'quantity' => $reversedQty,
                            'movement_type' => 'adjustment',
                            'reason' => "Restored pallet #{$pallet->id} ({$pallet->displayName()}) — reversed receipt stock restored",
                            'reference_type' => 'pallet_restore',
                            'reference_id' => $pallet->id,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                $productIds[] = $product->id;
            }

            foreach (array_unique($productIds) as $productId) {
                $this->recalculateReceiptCost((int) $productId);
            }
        });
    }

    private function receivedQuantityForLine($line): float
    {
        $received = $line->cases->where('status', '!=', 'expected');
        if ($received->isEmpty()) return 0.0;

        $explicit = (float) $received->sum(fn ($case) => (float) ($case->quantity_received ?? 0));
        return $explicit > 0 ? $explicit : (float) $received->count() * (float) $line->quantity_per_case;
    }

    private function productCanBeArchivedWithPallet(Product $product, Pallet $pallet, float $qtyFromPallet): bool
    {
        if ($product->palletLines()->where('pallet_id', '!=', $pallet->id)->whereHas('pallet')->exists()) return false;
        if ($product->orders()->exists()) return false;

        $onHand = (float) $product->stock()->sum('quantity');
        if (abs($onHand - $qtyFromPallet) > 0.00001) return false;

        $hasOtherOperationalMovement = $product->movements()
            ->where(function ($q) use ($pallet) {
                $q->where('reason', 'not like', "Received via pallet #{$pallet->id}%")->orWhereNull('reason');
            })
            ->exists();

        return ! $hasOtherOperationalMovement;
    }

    private function recalculateReceiptCost(int $productId): void
    {
        $product = Product::withTrashed()->find($productId);
        if (! $product) return;

        $receipts = $product->palletLines()
            ->whereHas('pallet', fn ($q) => $q->whereIn('status', ['received', 'processed']))
            ->get();

        if ($receipts->isEmpty()) {
            if (! $product->trashed()) {
                $product->forceFill(['average_cost' => (float) ($product->unit_cost ?? 0)])->save();
            }
            return;
        }

        $qty = 0.0;
        $cost = 0.0;
        foreach ($receipts as $line) {
            $lineQty = $line->totalQuantityExpected();
            $qty += $lineQty;
            $cost += $lineQty * (float) $line->unit_cost;
        }

        if ($qty > 0 && ! $product->trashed()) {
            $product->forceFill(['average_cost' => round($cost / $qty, 4)])->save();
        }
    }
}

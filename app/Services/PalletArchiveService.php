<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PalletArchiveService
{
    /** Preview the inventory impact before a completed pallet is archived. */
    public function preview(Pallet $pallet): array
    {
        $pallet->loadMissing(['lines.inventoryItem', 'lines.location', 'lines.cases']);

        $effects = [];
        $blockers = [];
        $deleteCandidates = [];
        $retained = [];

        foreach ($pallet->lines as $line) {
            if (! $line->inventory_item_id) continue;

            $qty = $this->receivedQuantityForLine($line);
            if ($qty <= 0) continue;

            $locationId = $line->inventory_location_id;
            if (! $locationId) {
                $blockers[] = "{$line->description}: received stock has no inventory location.";
                continue;
            }

            $stock = InventoryStock::query()
                ->where('inventory_item_id', $line->inventory_item_id)
                ->where('inventory_location_id', $locationId)
                ->first();
            $available = (float) ($stock?->quantity ?? 0);

            if ($available + 0.00001 < $qty) {
                $blockers[] = ($line->inventoryItem?->name ?? $line->description)
                    . ': only ' . number_format($available, 2)
                    . ' remains at ' . ($line->location?->name ?? 'the receiving location')
                    . ', but this pallet added ' . number_format($qty, 2)
                    . '. Some of that stock has already moved or been used.';
            }

            $effects[] = [
                'line_id' => $line->id,
                'product_id' => $line->inventory_item_id,
                'item' => $line->inventoryItem?->name ?? $line->description,
                'location_id' => $locationId,
                'location' => $line->location?->name ?? 'Unknown location',
                'quantity' => $qty,
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
            'blockers' => array_values(array_unique($blockers)),
            'delete_candidates' => $deleteCandidates,
            'retained_products' => $retained,
            'can_archive' => $blockers === [],
        ];
    }

    public function previewText(Pallet $pallet): string
    {
        $preview = $this->preview($pallet);
        $qty = collect($preview['effects'])->sum('quantity');
        $lines = count($preview['effects']);

        $text = "This will archive {$pallet->displayName()} and reverse "
            . number_format($qty, 2) . " inventory units across {$lines} received line(s).";

        if ($preview['delete_candidates'] !== []) {
            $names = collect($preview['delete_candidates'])->pluck('name')->join(', ');
            $text .= " Items created only for this pallet that will also be archived: {$names}.";
        } else {
            $text .= ' No inventory products will be deleted; linked/existing products will remain.';
        }

        if ($preview['retained_products'] !== []) {
            $names = collect($preview['retained_products'])->pluck('name')->join(', ');
            $text .= " Existing/shared products kept: {$names}.";
        }

        if ($preview['blockers'] !== []) {
            $text .= " Cannot archive yet: " . implode(' ', $preview['blockers']);
        } else {
            $text .= ' This is undoable from Archived Pallets.';
        }

        return $text;
    }

    /** Archive a pallet and reverse only inventory that can still be traced to it. */
    public function archive(Pallet $pallet): array
    {
        $preview = $this->preview($pallet);
        if (! $preview['can_archive']) {
            throw new RuntimeException(implode(' ', $preview['blockers']));
        }

        return DB::transaction(function () use ($pallet, $preview) {
            foreach ($preview['effects'] as $effect) {
                $stock = InventoryStock::query()
                    ->where('inventory_item_id', $effect['product_id'])
                    ->where('inventory_location_id', $effect['location_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $stock || (float) $stock->quantity + 0.00001 < (float) $effect['quantity']) {
                    throw new RuntimeException("Inventory changed while archiving {$effect['item']}. Refresh and try again.");
                }

                $stock->decrement('quantity', (float) $effect['quantity']);

                Product::query()->whereKey($effect['product_id'])->update([
                    'total_units_received' => DB::raw('GREATEST(0, total_units_received - ' . (float) $effect['quantity'] . ')'),
                ]);

                InventoryMovement::create([
                    'inventory_item_id' => $effect['product_id'],
                    'from_location_id' => $effect['location_id'],
                    'to_location_id' => null,
                    'quantity' => (float) $effect['quantity'],
                    'movement_type' => 'adjustment',
                    'reason' => "Archived pallet #{$pallet->id} ({$pallet->displayName()}) — receipt reversed",
                    'reference_type' => 'pallet_archive',
                    'reference_id' => $pallet->id,
                    'created_by' => auth()->id(),
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

            $productIds = [];
            foreach ($pallet->lines as $line) {
                if (! $line->inventory_item_id || ! $line->inventory_location_id) continue;

                $product = Product::withTrashed()->find($line->inventory_item_id);
                if (! $product) continue;
                if ($product->trashed()) $product->restore();

                $qty = $this->receivedQuantityForLine($line);
                if ($qty <= 0) continue;

                $stock = InventoryStock::firstOrCreate(
                    ['inventory_item_id' => $product->id, 'inventory_location_id' => $line->inventory_location_id],
                    ['quantity' => 0]
                );
                $stock->increment('quantity', $qty);
                $product->increment('total_units_received', $qty);

                InventoryMovement::create([
                    'inventory_item_id' => $product->id,
                    'from_location_id' => null,
                    'to_location_id' => $line->inventory_location_id,
                    'quantity' => $qty,
                    'movement_type' => 'adjustment',
                    'reason' => "Restored pallet #{$pallet->id} ({$pallet->displayName()}) — receipt restored",
                    'reference_type' => 'pallet_restore',
                    'reference_id' => $pallet->id,
                    'created_by' => auth()->id(),
                ]);
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

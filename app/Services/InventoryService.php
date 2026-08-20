<?php

namespace App\Services;

use App\Jobs\SendLowStockNotification;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private NotificationRouter $notificationRouter) {}
    /**
     * Movement types that represent goods arriving at a known cost, and so
     * should be recorded with that cost and folded into the weighted average.
     *
     * An adjustment is a correction and a return comes back at whatever was
     * already recorded, so neither belongs here. A breakdown does: opening a
     * case turns one costed thing into several, and the cost has to land on
     * them or it leaves the books entirely.
     */
    private const COSTED_INTAKE = ['opening', 'breakdown'];

    public function addStock(
        InventoryItem $item,
        InventoryLocation $location,
        float $quantity,
        string $movementType = 'opening',
        ?string $reason = null,
        ?float $unitCost = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $quantity, $movementType, $reason, $unitCost) {
            $stock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
                ['quantity' => 0]
            );

            $before = (float) $stock->quantity;
            $stock->increment('quantity', $quantity);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'from_location_id' => null,
                'to_location_id' => $location->id,
                'quantity' => $quantity,
                // The level at this location either side of the write. Stored
                // rather than derived so the history is a record of what the
                // stock was, not only of what moved — and so nothing has to
                // work the sign out from which location column happens to be
                // filled in.
                'quantity_before' => $before,
                'quantity_after' => $before + $quantity,
                'unit_cost' => in_array($movementType, self::COSTED_INTAKE, true) ? $unitCost : null,
                'movement_type' => $movementType,
                'reason' => $reason,
                'created_by' => Auth::id(),
            ]);

            // See COSTED_INTAKE: only goods actually arriving at a stated
            // cost re-average the item.
            if (in_array($movementType, self::COSTED_INTAKE, true) && $unitCost !== null && $unitCost > 0) {
                app(ReceivingService::class)->recalculateAverageCost($item, $quantity, $unitCost);
            }

            $this->notifyIfLowStock($item);

            return $movement;
        });
    }

    public function transferStock(
        InventoryItem $item,
        InventoryLocation $from,
        InventoryLocation $to,
        float $quantity,
        ?string $reason = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $from, $to, $quantity, $reason) {
            $fromStock = InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $from->id)
                ->lockForUpdate()
                ->first();

            if (! $fromStock || $fromStock->quantity < $quantity) {
                throw new \RuntimeException("Insufficient stock at {$from->name}. Available: " . ($fromStock?->quantity ?? 0));
            }

            $before = (float) $fromStock->quantity;

            $fromStock->decrement('quantity', $quantity);

            $toStock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $to->id],
                ['quantity' => 0]
            );
            $toStock->increment('quantity', $quantity);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'quantity' => $quantity,
                // The source side. A transfer nets to zero across the item, so
                // the levels that mean anything are the ones at the place the
                // stock left — which is also the side that can go wrong.
                'quantity_before' => $before,
                'quantity_after' => $before - $quantity,
                'movement_type' => 'transfer',
                'reason' => $reason,
                'created_by' => Auth::id(),
            ]);

            $this->notifyIfLowStock($item);

            return $movement;
        });
    }

    /**
     * Set stock at a location to an exact quantity.
     *
     * $movementType exists so a stocktake can still call itself a stocktake.
     * The arithmetic, the locking, the before/after and the direction are the
     * same whatever prompted it — and those are exactly the parts that were
     * being re-implemented, slightly differently and slightly wrong, wherever a
     * screen wrote stock for itself.
     */
    public function adjustStock(
        InventoryItem $item,
        InventoryLocation $location,
        float $newQuantity,
        ?string $reason = null,
        string $movementType = 'adjustment'
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $newQuantity, $reason, $movementType) {
            $stock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
                ['quantity' => 0]
            );

            $diff = $newQuantity - (float) $stock->quantity;

            if ($diff == 0) {
                return new InventoryMovement([
                    'inventory_item_id' => $item->id,
                    'quantity' => 0,
                    'movement_type' => $movementType,
                    'reason' => 'No change — quantity already at ' . $newQuantity,
                    'created_by' => Auth::id(),
                ]);
            }

            $before = (float) $stock->quantity;

            $stock->update(['quantity' => $newQuantity]);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'from_location_id' => $diff < 0 ? $location->id : null,
                'to_location_id' => $diff > 0 ? $location->id : null,
                'quantity' => abs($diff),
                'quantity_before' => $before,
                'quantity_after' => $newQuantity,
                'movement_type' => $movementType,
                'reason' => $reason ?? 'Manual adjustment',
                'created_by' => Auth::id(),
            ]);

            $this->notifyIfLowStock($item);

            return $movement;
        });
    }

    public function markDamaged(
        InventoryItem $item,
        InventoryLocation $from,
        InventoryLocation $damagedLocation,
        float $quantity,
        ?string $reason = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $from, $damagedLocation, $quantity, $reason) {
            $fromStock = InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $from->id)
                ->lockForUpdate()
                ->first();

            if (! $fromStock || $fromStock->quantity < $quantity) {
                throw new \RuntimeException("Insufficient stock at {$from->name}.");
            }

            $fromStock->decrement('quantity', $quantity);

            $damagedStock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $damagedLocation->id],
                ['quantity' => 0]
            );
            $damagedStock->increment('quantity', $quantity);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'from_location_id' => $from->id,
                'to_location_id' => $damagedLocation->id,
                'quantity' => $quantity,
                'movement_type' => 'damaged',
                'reason' => $reason,
                'created_by' => Auth::id(),
            ]);

            Notification::make()
                ->title('Items Marked Damaged')
                ->body(number_format($quantity) . 'x ' . $item->name . ' moved to damaged from ' . $from->name)
                ->danger()
                ->icon('heroicon-o-fire')
                ->sendToDatabase($this->notificationRouter->getRecipients('damaged'));

            $this->notifyIfLowStock($item);

            return $movement;
        });
    }

    public function moveToReturns(
        InventoryItem $item,
        InventoryLocation $from,
        InventoryLocation $returnsLocation,
        float $quantity,
        ?string $reason = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $from, $returnsLocation, $quantity, $reason) {
            $fromStock = InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $from->id)
                ->lockForUpdate()
                ->first();

            if (! $fromStock || $fromStock->quantity < $quantity) {
                throw new \RuntimeException("Insufficient stock at {$from->name}.");
            }

            $fromStock->decrement('quantity', $quantity);

            $returnStock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $returnsLocation->id],
                ['quantity' => 0]
            );
            $returnStock->increment('quantity', $quantity);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'from_location_id' => $from->id,
                'to_location_id' => $returnsLocation->id,
                'quantity' => $quantity,
                'movement_type' => 'return',
                'reason' => $reason,
                'created_by' => Auth::id(),
            ]);

            $this->notifyIfLowStock($item);

            return $movement;
        });
    }

    public function deductStock(
        InventoryItem $item,
        InventoryLocation $location,
        float $quantity,
        ?string $reason = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $quantity, $reason, $referenceId) {
            $stock = InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $stock || $stock->quantity < $quantity) {
                throw new \RuntimeException("Insufficient stock at {$location->name}. Available: " . ($stock?->quantity ?? 0));
            }

            $stock->decrement('quantity', $quantity);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'from_location_id'  => $location->id,
                'to_location_id'    => null,
                'quantity'          => $quantity,
                'movement_type'     => 'sale_deduction',
                'reason'            => $reason ?? 'Show sale deduction',
                'reference_type'    => $referenceId ? 'deduction_request' : null,
                'reference_id'      => $referenceId,
                'created_by'        => Auth::id(),
            ]);

            $this->notifyIfLowStock($item);

            return $movement;
        });
    }

    private function notifyIfLowStock(InventoryItem $item): void
    {
        // Dispatch async so inventory actions return immediately without waiting for DB notification writes.
        SendLowStockNotification::dispatch($item->id)->afterCommit();
    }
}

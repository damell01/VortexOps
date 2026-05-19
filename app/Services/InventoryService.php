<?php

namespace App\Services;

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
    public function addStock(
        InventoryItem $item,
        InventoryLocation $location,
        float $quantity,
        string $movementType = 'opening',
        ?string $reason = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $quantity, $movementType, $reason) {
            $stock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
                ['quantity' => 0]
            );

            $stock->increment('quantity', $quantity);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'from_location_id' => null,
                'to_location_id' => $location->id,
                'quantity' => $quantity,
                'movement_type' => $movementType,
                'reason' => $reason,
                'created_by' => Auth::id(),
            ]);

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
                ->first();

            if (! $fromStock || $fromStock->quantity < $quantity) {
                throw new \RuntimeException("Insufficient stock at {$from->name}. Available: " . ($fromStock?->quantity ?? 0));
            }

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
                'movement_type' => 'transfer',
                'reason' => $reason,
                'created_by' => Auth::id(),
            ]);

            $this->notifyIfLowStock($item);

            return $movement;
        });
    }

    public function adjustStock(
        InventoryItem $item,
        InventoryLocation $location,
        float $newQuantity,
        ?string $reason = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $newQuantity, $reason) {
            $stock = InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
                ['quantity' => 0]
            );

            $diff = $newQuantity - (float) $stock->quantity;

            if ($diff == 0) {
                return new InventoryMovement([
                    'inventory_item_id' => $item->id,
                    'quantity' => 0,
                    'movement_type' => 'adjustment',
                    'reason' => 'No change — quantity already at ' . $newQuantity,
                    'created_by' => Auth::id(),
                ]);
            }

            $stock->update(['quantity' => $newQuantity]);

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'from_location_id' => $diff < 0 ? $location->id : null,
                'to_location_id' => $diff > 0 ? $location->id : null,
                'quantity' => abs($diff),
                'movement_type' => 'adjustment',
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
        $item->refresh();
        if (! $item->isLowStock()) {
            return;
        }

        $qty = $item->totalQuantity();

        Notification::make()
            ->title('Low Stock: ' . $item->name)
            ->body(number_format($qty) . ' units remaining (reorder at ' . number_format((float) $item->reorder_level) . ')')
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->sendToDatabase($this->notificationRouter->getRecipients('low_stock'));
    }
}

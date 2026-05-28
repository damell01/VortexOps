<?php

namespace App\Services;

use App\Jobs\SendLowStockNotification;
use App\Models\InventoryContainer;
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
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $quantity, $movementType, $reason, $referenceType, $referenceId) {
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
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
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
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $from, $to, $quantity, $reason, $referenceType, $referenceId) {
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
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
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

    /**
     * @param  array<string, mixed>  $containerData
     * @param  array<string, mixed>  $costData
     */
    public function receiveIntoContainer(
        InventoryItem $item,
        InventoryLocation $location,
        float $quantity,
        array $containerData,
        array $costData = [],
        ?string $reason = null
    ): InventoryContainer {
        return DB::transaction(function () use ($item, $location, $quantity, $containerData, $costData, $reason) {
            $this->applyItemCosting($item, $costData, $containerData['barcode'] ?? null);

            $container = InventoryContainer::create([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
                'parent_container_id' => $containerData['parent_container_id'] ?? null,
                'container_type' => $containerData['container_type'] ?? 'pallet',
                'label' => $containerData['label'],
                'barcode' => $containerData['barcode'] ?? null,
                'quantity' => $quantity,
                'status' => $containerData['status'] ?? 'active',
                'scanner_ready' => (bool) ($containerData['scanner_ready'] ?? false),
                'scanner_metadata' => $containerData['scanner_metadata'] ?? null,
                'notes' => $containerData['notes'] ?? null,
            ]);

            $this->addStock(
                $item,
                $location,
                $quantity,
                'opening',
                $reason ?? 'Inventory received into ' . $container->label,
                'inventory_container',
                $container->id,
            );

            return $container->refresh();
        });
    }

    /**
     * @param  array<int, array{label: string, barcode?: string|null, quantity: float|int|string, notes?: string|null, scanner_ready?: bool}>  $children
     * @return array<int, InventoryContainer>
     */
    public function breakdownContainer(
        InventoryContainer $parent,
        string $childType,
        array $children,
        ?InventoryLocation $location = null
    ): array {
        return DB::transaction(function () use ($parent, $childType, $children, $location) {
            $parent->refresh();

            $targetLocation = $location ?? $parent->location;

            if (! $parent->item || ! $targetLocation) {
                throw new \RuntimeException('Parent container must have an inventory item and location before breakdown.');
            }

            $breakdownTotal = collect($children)->sum(fn (array $child): float => (float) ($child['quantity'] ?? 0));

            if ($breakdownTotal <= 0) {
                throw new \RuntimeException('Breakdown quantity must be greater than zero.');
            }

            if ($breakdownTotal > (float) $parent->quantity) {
                throw new \RuntimeException("Breakdown exceeds parent quantity. Available: {$parent->quantity}");
            }

            $created = [];

            foreach ($children as $child) {
                $childQuantity = (float) ($child['quantity'] ?? 0);

                if ($childQuantity <= 0) {
                    continue;
                }

                $created[] = InventoryContainer::create([
                    'inventory_item_id' => $parent->inventory_item_id,
                    'inventory_location_id' => $targetLocation->id,
                    'parent_container_id' => $parent->id,
                    'container_type' => $childType,
                    'label' => $child['label'],
                    'barcode' => $child['barcode'] ?? null,
                    'quantity' => $childQuantity,
                    'status' => 'active',
                    'scanner_ready' => (bool) ($child['scanner_ready'] ?? false),
                    'notes' => $child['notes'] ?? null,
                ]);
            }

            $remainingQuantity = max(0, (float) $parent->quantity - $breakdownTotal);

            $parent->update([
                'quantity' => $remainingQuantity,
                'status' => $remainingQuantity <= 0 ? 'broken_down' : 'active',
            ]);

            return $created;
        });
    }

    public function moveContainer(
        InventoryContainer $container,
        InventoryLocation $to,
        ?string $reason = null
    ): InventoryContainer {
        return DB::transaction(function () use ($container, $to, $reason) {
            $container->refresh();

            $from = $container->location;
            $item = $container->item;
            $quantity = (float) $container->quantity;

            if (! $item) {
                throw new \RuntimeException('Container must be linked to an inventory item before it can be moved.');
            }

            if (! $from) {
                throw new \RuntimeException('Container must have a current location before it can be moved.');
            }

            if ($from->is($to)) {
                return $container;
            }

            if ($quantity <= 0) {
                throw new \RuntimeException('Only containers with available quantity can be moved.');
            }

            $this->transferStock(
                $item,
                $from,
                $to,
                $quantity,
                $reason ?? 'Container putaway',
                'inventory_container',
                $container->id,
            );

            $container->update([
                'inventory_location_id' => $to->id,
            ]);

            return $container->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $costData
     */
    private function applyItemCosting(InventoryItem $item, array $costData, ?string $containerBarcode = null): void
    {
        $updates = [];

        foreach ([
            'barcode',
            'seller_unit_cost',
            'shipping_unit_cost',
            'other_unit_fees',
            'average_unit_cost',
            'cost_notes',
        ] as $field) {
            if (array_key_exists($field, $costData) && $costData[$field] !== '' && $costData[$field] !== null) {
                $updates[$field] = $costData[$field];
            }
        }

        if (! array_key_exists('barcode', $updates) && blank($item->barcode) && filled($containerBarcode)) {
            $updates['barcode'] = $containerBarcode;
        }

        if (
            ! array_key_exists('average_unit_cost', $updates) &&
            blank($item->average_unit_cost) &&
            (array_key_exists('seller_unit_cost', $updates) || array_key_exists('shipping_unit_cost', $updates) || array_key_exists('other_unit_fees', $updates))
        ) {
            $updates['average_unit_cost'] =
                (float) ($updates['seller_unit_cost'] ?? $item->seller_unit_cost ?? 0) +
                (float) ($updates['shipping_unit_cost'] ?? $item->shipping_unit_cost ?? 0) +
                (float) ($updates['other_unit_fees'] ?? $item->other_unit_fees ?? 0);
        }

        if ($updates !== []) {
            $item->fill($updates);
            $item->save();
        }
    }

    private function notifyIfLowStock(InventoryItem $item): void
    {
        // Dispatch async so inventory actions return immediately without waiting for DB notification writes.
        SendLowStockNotification::dispatch($item->id)->afterCommit();
    }
}

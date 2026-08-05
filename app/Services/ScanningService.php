<?php

namespace App\Services;

use App\Models\ScanSession;
use App\Models\ScanLog;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\Pallet;
use App\Models\PalletLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centralized scanning service for all mobile workflows.
 * Handles barcode lookup, duplicate detection, logging, and inventory updates.
 */
class ScanningService
{
    private ?ScanSession $session = null;
    private Collection $scannedItems;
    private Collection $duplicates;

    public function __construct(private ?InventoryService $inventoryService = null)
    {
        $this->scannedItems = collect();
        $this->duplicates = collect();
        $this->inventoryService ??= app(InventoryService::class);
    }

    public function createSession(string $type, $contextId = null, string $contextType = null, array $metadata = []): ScanSession
    {
        $this->session = ScanSession::create([
            'user_id' => auth()->id(),
            'type' => $type,
            'context_id' => $contextId,
            'context_type' => $contextType,
            'started_at' => now(),
            'status' => 'active',
            'metadata' => $metadata,
        ]);

        $this->scannedItems = collect();
        $this->duplicates = collect();

        return $this->session;
    }

    public function scanBarcode(string $barcode, string $mode = 'inventory'): array
    {
        if (!$this->session) {
            return $this->error('No active scanning session');
        }

        $barcode = trim($barcode);

        if (empty($barcode)) {
            return $this->error('Barcode cannot be empty');
        }

        $item = Product::findByScan($barcode);

        if (!$item) {
            $this->logScan($barcode, null, 'not_found', "Barcode not found: {$barcode}");
            return $this->error("Barcode not found: {$barcode}");
        }

        // Duplicate detection
        if ($this->isDuplicate($item->id)) {
            $this->duplicates->push([
                'item_id' => $item->id,
                'name' => $item->name,
                'barcode' => $barcode,
                'scanned_at' => now(),
            ]);

            $this->logScan($barcode, $item->id, 'duplicate', 'Duplicate barcode scanned');
            return $this->warning("Duplicate barcode: {$item->name}");
        }

        // Process by mode
        $result = match ($mode) {
            'inventory' => $this->processInventoryScan($item),
            'receiving' => $this->processReceivingScan($item),
            'shipping' => $this->processShippingScan($item),
            'lookup' => $this->processLookupScan($item),
            default => $this->processLookupScan($item),
        };

        if ($result['success']) {
            $this->scannedItems->push($result['item']);
            $this->session->update(['item_count' => $this->scannedItems->count()]);
            $this->logScan($barcode, $item->id, 'success', 'Barcode scanned successfully');
        }

        return $result;
    }

    private function getTotalQuantity(Product $item): float
    {
        return (float) $item->stock()->sum('quantity');
    }

    private function processInventoryScan(Product $item): array
    {
        $totalQty = $this->getTotalQuantity($item);

        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $totalQty,
                'cost' => $item->average_cost,
                'status' => $totalQty > 0 ? 'in_stock' : 'out_of_stock',
            ],
        ];
    }

    private function processReceivingScan(Product $item): array
    {
        $palletId = $this->session->context_id;

        if (!$palletId) {
            return $this->error('No pallet selected for receiving');
        }

        $line = PalletLine::where('pallet_id', $palletId)
            ->where('inventory_item_id', $item->id)
            ->first();

        if (!$line) {
            return $this->warning("Item {$item->name} not on packing slip");
        }

        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'sku' => $item->sku,
                'expected_qty' => $line->expected_quantity,
                'received_qty' => $line->received_quantity ?? 0,
                'remaining' => ($line->expected_quantity - ($line->received_quantity ?? 0)),
                'status' => $line->received_quantity ? 'received' : 'pending',
            ],
        ];
    }

    private function processShippingScan(Product $item): array
    {
        $totalQty = $this->getTotalQuantity($item);

        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $totalQty,
            ],
        ];
    }

    private function processLookupScan(Product $item): array
    {
        $totalQty = $this->getTotalQuantity($item);
        $totalValue = $totalQty * (float) $item->average_cost;

        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $totalQty,
                'cost' => $item->average_cost,
                'value' => $totalValue,
            ],
        ];
    }

    private function isDuplicate(int $itemId): bool
    {
        return $this->scannedItems->contains('id', $itemId);
    }

    private function logScan(string $barcode, ?int $itemId, string $result, string $notes = ''): void
    {
        if ($this->session) {
            ScanLog::create([
                'scan_session_id' => $this->session->id,
                'barcode' => $barcode,
                'inventory_item_id' => $itemId,
                'result' => $result,
                'notes' => $notes,
                'scanned_at' => now(),
            ]);
        }
    }

    private function error(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'type' => 'error',
        ];
    }

    private function warning(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'type' => 'warning',
        ];
    }

    /**
     * Commit scanned items to inventory system, creating lots and movements.
     * Used when saving a receiving session.
     */
    public function commitScansToInventory(?InventoryLocation $location = null): array
    {
        if (!$this->session) {
            return ['success' => false, 'message' => 'No active session'];
        }

        if ($this->scannedItems->isEmpty()) {
            return ['success' => false, 'message' => 'No items to commit'];
        }

        if (!$location) {
            $location = InventoryLocation::where('type', 'receiving')->first()
                ?? InventoryLocation::first();
        }

        if (!$location) {
            return ['success' => false, 'message' => 'No location available'];
        }

        return DB::transaction(function () use ($location) {
            $created = 0;
            $errors = [];

            foreach ($this->scannedItems as $scannedItem) {
                try {
                    $product = Product::find($scannedItem['id']);
                    if (!$product) {
                        $errors[] = "Product {$scannedItem['id']} not found";
                        continue;
                    }

                    // Get unit cost from the session metadata or use the product's average cost
                    $unitCost = $this->session->metadata['unit_cost'] ?? $product->average_cost ?? 0;

                    // Create inventory lot for tracking this batch
                    $lot = InventoryLot::create([
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'remaining_quantity' => 1,
                        'unit_cost' => $unitCost,
                        'source' => InventoryLot::SOURCE_RECEIVED,
                        'status' => InventoryLot::STATUS_ACTIVE,
                        'received_at' => now(),
                        'received_by' => auth()->id(),
                    ]);

                    // Add stock via InventoryService
                    $this->inventoryService->addStock(
                        $product,
                        $location,
                        1,
                        'opening',
                        "Scanned in session {$this->session->id}",
                        $unitCost
                    );

                    $created++;
                } catch (\Exception $e) {
                    $errors[] = "Error for {$scannedItem['name']}: {$e->getMessage()}";
                }
            }

            $this->session->update([
                'item_count' => $created,
                'metadata' => array_merge($this->session->metadata ?? [], [
                    'committed_at' => now()->toIso8601String(),
                    'location_id' => $location->id,
                    'items_created' => $created,
                ]),
            ]);

            return [
                'success' => empty($errors),
                'created' => $created,
                'errors' => $errors,
            ];
        });
    }

    public function endSession(): ScanSession
    {
        if ($this->session) {
            $this->session->update([
                'ended_at' => now(),
                'status' => 'completed',
                'item_count' => $this->scannedItems->count(),
            ]);
        }

        return $this->session;
    }

    public function getScannedItems(): Collection
    {
        return $this->scannedItems;
    }

    public function getDuplicates(): Collection
    {
        return $this->duplicates;
    }

    public function getSession(): ?ScanSession
    {
        return $this->session ?? null;
    }
}

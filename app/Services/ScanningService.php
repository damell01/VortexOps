<?php

namespace App\Services;

use App\Models\ScanSession;
use App\Models\ScanLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Pallet;
use App\Models\PalletLine;
use Illuminate\Database\Eloquent\Collection;

/**
 * Centralized scanning service for all mobile workflows.
 * Handles barcode lookup, duplicate detection, and logging across all scanner modes.
 */
class ScanningService
{
    private ScanSession $session;
    private Collection $scannedItems;
    private Collection $duplicates;

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

        $item = InventoryItem::where('barcode', $barcode)
            ->orWhere('sku', $barcode)
            ->first();

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

    private function processInventoryScan(InventoryItem $item): array
    {
        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity_on_hand,
                'location' => $item->location?->name ?? 'Unknown',
                'cost' => $item->average_cost,
                'status' => $item->quantity_on_hand > 0 ? 'in_stock' : 'out_of_stock',
            ],
        ];
    }

    private function processReceivingScan(InventoryItem $item): array
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

    private function processShippingScan(InventoryItem $item): array
    {
        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity_on_hand,
            ],
        ];
    }

    private function processLookupScan(InventoryItem $item): array
    {
        return [
            'success' => true,
            'item' => [
                'id' => $item->id,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity_on_hand,
                'location' => $item->location?->name ?? 'Unknown',
                'cost' => $item->average_cost,
                'value' => ($item->quantity_on_hand * $item->average_cost),
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

<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Support\AdminModules;
use Filament\Pages\Page;

class InventoryScanner extends Page
{
    protected static ?string $title = 'Inventory Scanner';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-qr-code';
    }

    public static function getNavigationSort(): ?int
    {
        return 99;
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && AdminModules::isEnabled('inventory');
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-scanner';
    }

    // ── State ─────────────────────────────────────────────────────────────────

    public string  $scanInput    = '';
    public ?array  $result       = null;
    public ?string $errorMessage = null;
    public bool    $adjustMode   = false;
    public string  $adjustQty    = '';
    public string  $adjustReason = '';
    public int     $adjustLocationId = 0;

    // ── Actions ───────────────────────────────────────────────────────────────

    public function submitScan(): void
    {
        $code = trim($this->scanInput);
        $this->scanInput    = '';
        $this->errorMessage = null;
        $this->adjustMode   = false;

        if ($code === '') {
            return;
        }

        $item = InventoryItem::findByScan($code);

        if (! $item) {
            $this->result       = null;
            $this->errorMessage = "No inventory item found for \"{$code}\". Check the barcode or SKU on the item record.";
            return;
        }

        $item->load(['stock.location', 'movements' => fn ($q) => $q->with('toLocation')->latest()->limit(5)]);

        $stockByLocation = $item->stock->map(fn ($s) => [
            'location' => $s->location?->name ?? 'Unknown',
            'qty'      => (float) $s->quantity,
            'id'       => $s->id,
            'location_id' => $s->inventory_location_id,
        ])->values()->toArray();

        $recentMovements = $item->movements->map(fn (InventoryMovement $m) => [
            'type'     => $m->movement_type,
            'qty'      => (float) $m->quantity,
            'location' => $m->toLocation?->name ?? '—',
            'date'     => $m->created_at?->diffForHumans(),
            'reason'   => $m->reason,
        ])->toArray();

        $this->result = [
            'id'          => $item->id,
            'name'        => $item->name,
            'sku'         => $item->sku,
            'barcode'     => $item->barcode,
            'category'    => $item->category,
            'avg_cost'    => number_format((float) $item->average_cost, 2),
            'total_qty'   => number_format($item->totalQuantity(), 0),
            'is_low'      => $item->isLowStock(),
            'reorder'     => $item->reorder_level,
            'stock'       => $stockByLocation,
            'movements'   => $recentMovements,
        ];
    }

    public function openAdjust(): void
    {
        $this->adjustMode       = true;
        $this->adjustQty        = '';
        $this->adjustReason     = '';
        $this->adjustLocationId = $this->result['stock'][0]['location_id'] ?? 0;
    }

    public function applyAdjust(): void
    {
        if (! $this->result) {
            return;
        }

        $qty = (float) $this->adjustQty;
        if ($qty == 0) {
            return;
        }

        $item     = InventoryItem::findOrFail($this->result['id']);
        $location = \App\Models\InventoryLocation::findOrFail($this->adjustLocationId);

        $stock = \App\Models\InventoryStock::firstOrCreate(
            ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
            ['quantity' => 0]
        );

        $stock->increment('quantity', $qty);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => $qty > 0 ? 'adjustment_in' : 'adjustment_out',
            'quantity'          => abs($qty),
            'to_location_id'    => $location->id,
            'reason'            => $this->adjustReason ?: 'Manual scanner adjustment',
            'created_by'        => auth()->id(),
        ]);

        $this->adjustMode = false;

        // Re-run scan to refresh result
        $this->scanInput = $item->barcode ?? $item->sku ?? '';
        $this->submitScan();
    }
}

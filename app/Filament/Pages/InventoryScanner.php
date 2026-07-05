<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Services\ReceivingService;
use App\Support\AdminModules;
use Filament\Pages\Page;
use RuntimeException;

class InventoryScanner extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'inventory';
    protected static string $featureSlug = 'inventory_scanner';
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

    public function getView(): string
    {
        return 'filament.pages.inventory-scanner';
    }

    // ── Mode ──────────────────────────────────────────────────────────────────

    /** 'lookup' | 'quickadd' | 'receive' */
    public string $mode = 'lookup';

    // ── Lookup state ──────────────────────────────────────────────────────────

    public string  $scanInput    = '';
    public ?array  $result       = null;
    public ?string $errorMessage = null;
    public bool    $adjustMode   = false;
    public string  $adjustQty    = '';
    public string  $adjustReason = '';
    public int     $adjustLocationId = 0;

    // ── Quick Add state ───────────────────────────────────────────────────────

    public int    $qaLocationId = 0;
    public string $qaQty        = '1';
    public ?array $qaFlash      = null; // ['name', 'qty', 'location']

    // ── Receive Pallet state ──────────────────────────────────────────────────

    public ?int    $rcvPalletId = null;
    public ?array  $rcvProgress = null; // per-line arrays
    public ?string $rcvFlash    = null;
    public ?string $rcvError    = null;

    // ── Boot ──────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $first = InventoryLocation::orderBy('name')->value('id');
        if ($first) {
            $this->qaLocationId = $first;
        }
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getLocationsProperty()
    {
        return InventoryLocation::orderBy('name')->get(['id', 'name', 'type']);
    }

    public function getPalletsProperty()
    {
        return Pallet::orderByRaw("CASE WHEN status != 'received' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get(['id', 'reference', 'status']);
    }

    // ── Mode switching ────────────────────────────────────────────────────────

    public function switchMode(string $mode): void
    {
        $this->mode        = $mode;
        $this->scanInput   = '';
        $this->errorMessage = null;
        $this->result      = null;
        $this->adjustMode  = false;
        $this->qaFlash     = null;
        $this->rcvFlash    = null;
        $this->rcvError    = null;
    }

    // ── Unified scan entry point ──────────────────────────────────────────────

    public function submitScan(): void
    {
        match ($this->mode) {
            'lookup'   => $this->doLookup(),
            'quickadd' => $this->doQuickAdd(),
            'receive'  => $this->doReceive(),
            default    => $this->doLookup(),
        };
    }

    // ── Lookup mode ───────────────────────────────────────────────────────────

    private function doLookup(): void
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

        $this->result = $this->buildResultFromItem($item);
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
        $location = InventoryLocation::findOrFail($this->adjustLocationId);

        $stock = InventoryStock::firstOrCreate(
            ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $qty);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => 'adjustment',
            'quantity'          => $qty,
            'to_location_id'    => $location->id,
            'reason'            => $this->adjustReason ?: 'Manual scanner adjustment',
            'created_by'        => auth()->id(),
        ]);

        $this->adjustMode = false;
        $item->refresh();
        $this->result = $this->buildResultFromItem($item);
    }

    // ── Quick Add mode ────────────────────────────────────────────────────────

    private function doQuickAdd(): void
    {
        $code = trim($this->scanInput);
        $this->scanInput = '';
        $this->qaFlash   = null;

        if ($code === '') {
            return;
        }

        if (! $this->qaLocationId) {
            $this->qaFlash = ['error' => 'Select a destination location before scanning.'];
            return;
        }

        $qty = (float) $this->qaQty;
        if ($qty <= 0) {
            $this->qaFlash = ['error' => 'Quantity must be greater than zero.'];
            return;
        }

        $item = InventoryItem::findByScan($code);
        if (! $item) {
            $this->qaFlash = ['error' => "No item found for \"{$code}\"."];
            return;
        }

        $location = InventoryLocation::find($this->qaLocationId);
        if (! $location) {
            $this->qaFlash = ['error' => 'Selected location no longer exists.'];
            return;
        }

        $stock = InventoryStock::firstOrCreate(
            ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
            ['quantity' => 0]
        );
        $stock->increment('quantity', $qty);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type'     => 'adjustment',
            'quantity'          => $qty,
            'to_location_id'    => $location->id,
            'reason'            => 'Quick Add via scanner',
            'created_by'        => auth()->id(),
        ]);

        $this->qaFlash = [
            'name'     => $item->name,
            'qty'      => $qty,
            'location' => $location->name,
        ];
    }

    // ── Receive Pallet mode ───────────────────────────────────────────────────

    public function updatedRcvPalletId(): void
    {
        $this->rcvFlash   = null;
        $this->rcvError   = null;
        $this->scanInput  = '';

        if ($this->rcvPalletId) {
            $this->refreshPalletProgress();
        } else {
            $this->rcvProgress = null;
        }
    }

    private function doReceive(): void
    {
        $code = trim($this->scanInput);
        $this->scanInput = '';
        $this->rcvFlash  = null;
        $this->rcvError  = null;

        if ($code === '') {
            return;
        }

        if (! $this->rcvPalletId) {
            $this->rcvError = 'Select a pallet first.';
            return;
        }

        $pallet = Pallet::find($this->rcvPalletId);
        if (! $pallet) {
            $this->rcvError = 'Pallet not found.';
            return;
        }

        try {
            $result = app(ReceivingService::class)->receiveLineByItemCode($pallet, $code);
            $this->rcvFlash = "Received {$result['item_name']} — {$result['cases_received']} case(s) added to stock.";
        } catch (RuntimeException $e) {
            $this->rcvError = $e->getMessage();
        }

        $this->refreshPalletProgress();
    }

    private function refreshPalletProgress(): void
    {
        if (! $this->rcvPalletId) {
            $this->rcvProgress = null;
            return;
        }

        $pallet = Pallet::with(['lines.inventoryItem', 'lines.cases', 'vendor'])->find($this->rcvPalletId);
        if (! $pallet) {
            $this->rcvProgress = null;
            return;
        }

        $this->rcvProgress = [
            'reference'    => $pallet->reference,
            'vendor'       => $pallet->vendor?->name ?? '—',
            'total_lines'  => $pallet->lines->count(),
            'done_lines'   => 0,
            'lines'        => [],
        ];

        foreach ($pallet->lines as $line) {
            $totalCases    = (int) $line->case_count;
            $receivedCases = $line->cases->where('status', '!=', 'expected')->count();
            $done          = $totalCases > 0 && $receivedCases >= $totalCases;

            if ($done) {
                $this->rcvProgress['done_lines']++;
            }

            $this->rcvProgress['lines'][] = [
                'line_number'    => $line->line_number,
                'item_name'      => $line->inventoryItem?->name ?? ($line->description ?? 'Unmapped'),
                'sku'            => $line->inventoryItem?->sku,
                'barcode'        => $line->inventoryItem?->barcode,
                'is_mapped'      => $line->isFullyMapped(),
                'total_cases'    => $totalCases,
                'received_cases' => $receivedCases,
                'done'           => $done,
            ];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildResultFromItem(InventoryItem $item): array
    {
        $item->load(['stock.location', 'movements' => fn ($q) => $q->with('toLocation')->latest()->limit(5)]);

        $stockByLocation = $item->stock->map(fn ($s) => [
            'location'    => $s->location?->name ?? 'Unknown',
            'qty'         => (float) $s->quantity,
            'id'          => $s->id,
            'location_id' => $s->inventory_location_id,
        ])->values()->toArray();

        $recentMovements = $item->movements->map(fn (InventoryMovement $m) => [
            'type'     => $m->movement_type,
            'qty'      => (float) $m->quantity,
            'location' => $m->toLocation?->name ?? '—',
            'date'     => $m->created_at?->diffForHumans(),
            'reason'   => $m->reason,
        ])->toArray();

        return [
            'id'        => $item->id,
            'name'      => $item->name,
            'sku'       => $item->sku,
            'barcode'   => $item->barcode,
            'category'  => $item->category,
            'avg_cost'  => number_format((float) $item->average_cost, 2),
            'total_qty' => number_format($item->totalQuantity(), 0),
            'is_low'    => $item->isLowStock(),
            'reorder'   => $item->reorder_level,
            'stock'     => $stockByLocation,
            'movements' => $recentMovements,
        ];
    }
}

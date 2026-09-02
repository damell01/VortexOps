<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\ProductIdentity;
use App\Services\ReceivingService;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use RuntimeException;

class InventoryScanner extends Page
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'inventory';
    protected static ?string $title = 'Inventory Scanner';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-qr-code';
    }

    public static function getNavigationLabel(): string
    {
        return 'Scan Inventory';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
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

    // ── Unmatched-scan → attach barcode state ───────────────────────────────────

    public ?string $unmatchedCode  = null;
    public string  $attachSearch   = '';
    public array   $attachResults  = [];

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
        $this->resetUnmatchedState();
    }

    private function resetUnmatchedState(): void
    {
        $this->unmatchedCode = null;
        $this->attachSearch  = '';
        $this->attachResults = [];
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
        $this->resetUnmatchedState();

        if ($code === '') {
            return;
        }

        $item = InventoryItem::findByScan($code);

        if (! $item) {
            $this->result        = null;
            $this->errorMessage  = "No inventory item found for \"{$code}\".";
            $this->unmatchedCode = $code;
            return;
        }

        $this->result = $this->buildResultFromItem($item);
    }

    // ── Unmatched-scan → attach barcode to an existing item ─────────────────────

    public function updatedAttachSearch(): void
    {
        $search = trim($this->attachSearch);

        if ($search === '') {
            $this->attachResults = [];
            return;
        }

        $this->attachResults = InventoryItem::where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'sku', 'barcode'])
            ->toArray();
    }

    /**
     * Attach the last unmatched scan to an existing item — sets the item's
     * primary barcode if it doesn't have one yet, otherwise records it as an
     * additional scannable identity (ProductIdentity) so both codes keep working.
     */
    public function attachCodeToItem(int $itemId): void
    {
        if (! $this->unmatchedCode) {
            return;
        }

        $item = InventoryItem::find($itemId);
        if (! $item) {
            Notification::make()->danger()->title('Item no longer exists.')->send();
            return;
        }

        try {
            if (blank($item->barcode)) {
                $item->update(['barcode' => $this->unmatchedCode]);
            } else {
                ProductIdentity::firstOrCreate([
                    'product_id' => $item->id,
                    'type'       => ProductIdentity::TYPE_BARCODE,
                    'value'      => $this->unmatchedCode,
                    'vendor_id'  => null,
                ], [
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
            }
        } catch (QueryException $e) {
            Notification::make()
                ->danger()
                ->title('Could not attach barcode')
                ->body('That code may already be linked to a different item.')
                ->send();
            return;
        }

        $code = $this->unmatchedCode;
        $this->resetUnmatchedState();
        $this->errorMessage = null;
        $this->result = $this->buildResultFromItem($item->fresh());

        Notification::make()
            ->success()
            ->title("Barcode \"{$code}\" attached to {$item->name}")
            ->send();
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

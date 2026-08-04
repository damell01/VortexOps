<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\ScannerReceivingSession;
use App\Services\InventoryCostService;
use App\Services\ReceivingService;
use App\Support\AdminModules;
use Filament\Pages\Page;
use RuntimeException;

class InventoryScanner extends Page
{
    use HasModuleAccess;

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
        return 'filament.pages.inventory-scanner-enhanced';
    }

    public function getSubheading(): ?string
    {
        return 'Four modes: Look Up checks stock & costs, Quick Add scans to add stock, Receive Pallet tracks incoming shipments, Stage Pallet pre-stages for receiving. Works with scanners or phone camera.';
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
    public bool    $showCostAdjust = false;
    public ?int    $costAdjustLineId = null;
    public string  $costAdjustNewCost = '';
    public string  $costAdjustReason = '';

    // ── Advanced Pallet Features ──────────────────────────────────────────────

    public bool    $showPalletStaging = false;  // Toggle staging view
    public ?int    $stagingPalletId = null;     // For pre-staging pallets
    public string  $stagingVendorId = '';
    public string  $stagingReference = '';
    public ?int    $currentSessionId = null;    // Active scanner session
    public string  $scanMode = 'barcode';       // barcode|camera
    public bool    $cameraActive = false;
    public array   $scannedCodes = [];          // Track scanned codes in session
    public ?array  $stagingPackingSlip = null;  // Uploaded packing slip file

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

    // ── Pallet Staging (Advanced Feature) ──────────────────────────────────────

    public function stagePallet(): void
    {
        if (!$this->stagingVendorId || !$this->stagingReference) {
            return;
        }

        $pallet = Pallet::create([
            'vendor_id' => $this->stagingVendorId,
            'reference' => $this->stagingReference,
            'status' => 'pending',
            'stage' => Pallet::STAGE_STAGED,
            'staged_at' => now(),
            'created_by' => auth()->id(),
        ]);

        // Handle packing slip upload if provided
        if ($this->stagingPackingSlip) {
            $filePath = $this->stagingPackingSlip['path'] ?? null;
            if ($filePath) {
                \App\Models\PalletPackingSlip::create([
                    'pallet_id' => $pallet->id,
                    'file_path' => $filePath,
                    'original_filename' => $this->stagingPackingSlip['name'] ?? 'unknown',
                    'file_size' => $this->stagingPackingSlip['size'] ?? 0,
                    'mime_type' => $this->stagingPackingSlip['type'] ?? 'application/octet-stream',
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        $this->showPalletStaging = false;
        $this->stagingPalletId = $pallet->id;
        $this->stagingVendorId = '';
        $this->stagingReference = '';
        $this->stagingPackingSlip = null;

        // Start receiving session for this pallet
        $this->startReceivingSession($pallet->id);
    }

    public function startReceivingSession(?int $palletId = null): void
    {
        if (!$palletId && !$this->rcvPalletId) {
            return;
        }

        $palletId = $palletId ?? $this->rcvPalletId;

        // End any existing session
        if ($this->currentSessionId) {
            $session = ScannerReceivingSession::find($this->currentSessionId);
            if ($session?->isActive()) {
                $session->end();
            }
        }

        // Create new session
        $session = ScannerReceivingSession::create([
            'pallet_id' => $palletId,
            'user_id' => auth()->id(),
            'mode' => $this->scanMode,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->currentSessionId = $session->id;
        $this->scannedCodes = [];

        // Update pallet stage
        $pallet = Pallet::find($palletId);
        $pallet->update([
            'stage' => Pallet::STAGE_RECEIVING,
            'receiving_started_at' => now(),
            'status' => 'receiving',
        ]);
    }

    public function endReceivingSession(): void
    {
        if ($this->currentSessionId) {
            $session = ScannerReceivingSession::find($this->currentSessionId);
            if ($session?->isActive()) {
                $session->end();
            }
        }
        $this->currentSessionId = null;
        $this->scannedCodes = [];
    }

    public function openCostAdjust(int $lineId): void
    {
        $line = PalletLine::find($lineId);
        if ($line) {
            $this->costAdjustLineId = $lineId;
            $this->costAdjustNewCost = (string) $line->unit_cost;
            $this->costAdjustReason = '';
            $this->showCostAdjust = true;
        }
    }

    public function applyCostAdjust(): void
    {
        if (!$this->costAdjustLineId) {
            return;
        }

        $line = PalletLine::find($this->costAdjustLineId);
        if (!$line) {
            return;
        }

        $newCost = (float) $this->costAdjustNewCost;
        if ($newCost < 0) {
            return;
        }

        $costService = app(InventoryCostService::class);
        $costService->recordCostChange(
            $line,
            $newCost,
            $this->costAdjustReason ?: 'Cost adjusted during receiving',
            auth()->id()
        );

        $this->showCostAdjust = false;
        $this->costAdjustLineId = null;
        $this->costAdjustNewCost = '';
        $this->costAdjustReason = '';
        $this->refreshPalletProgress();
    }

    private function recordScannedCode(string $code): void
    {
        if ($this->currentSessionId) {
            $this->scannedCodes[] = $code;
            $session = ScannerReceivingSession::find($this->currentSessionId);
            if ($session) {
                $session->update([
                    'items_scanned' => count($this->scannedCodes),
                    'last_activity_at' => now(),
                ]);
            }
        }
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
                'line_id'        => $line->id,
                'line_number'    => $line->line_number,
                'item_name'      => $line->inventoryItem?->name ?? ($line->description ?? 'Unmapped'),
                'sku'            => $line->inventoryItem?->sku,
                'barcode'        => $line->inventoryItem?->barcode,
                'is_mapped'      => $line->isFullyMapped(),
                'total_cases'    => $totalCases,
                'received_cases' => $receivedCases,
                'done'           => $done,
                'unit_cost'      => (float) $line->unit_cost,
            ];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildResultFromItem(InventoryItem $item): array
    {
        $item->load(['stock.location', 'movements' => fn ($q) => $q->with('toLocation')->latest()->limit(5)]);

        $costService = app(InventoryCostService::class);
        $totalQty = $item->totalQuantity();
        $inventoryValue = $costService->calculateInventoryValue($item);

        // Get cost breakdown and check for pricing anomalies
        $costBreakdown = $costService->getCostBreakdown($item);
        $pricingAnomaly = null;
        $vendorCosts = [];

        if (!empty($costBreakdown)) {
            $costs = array_column($costBreakdown, 'average_cost');
            if (!empty($costs)) {
                $minCost = min($costs);
                $maxCost = max($costs);
                if ($minCost > 0) {
                    $variance = (($maxCost - $minCost) / $minCost) * 100;
                    if ($variance > 20) {
                        $pricingAnomaly = [
                            'variance_pct' => round($variance, 1),
                            'min_cost' => number_format($minCost, 2),
                            'max_cost' => number_format($maxCost, 2),
                        ];
                    }
                }
            }

            // Format vendor costs for display
            foreach ($costBreakdown as $key => $data) {
                $vendorCosts[] = [
                    'vendor_name' => $data['vendor_name'],
                    'avg_cost' => number_format((float) $data['average_cost'], 2),
                    'qty' => (int) $data['total_qty'],
                ];
            }
        }

        // Get cost trend (last 10 receipts)
        $costTrend = $costService->getCostTrend($item, 10);
        $formattedCostTrend = array_map(fn ($ct) => [
            'date' => $ct['date']?->format('M d, Y') ?? '—',
            'vendor' => $ct['vendor'],
            'cost' => number_format((float) $ct['unit_cost'], 2),
            'qty' => (int) $ct['quantity'],
        ], $costTrend);

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
            'id'               => $item->id,
            'name'             => $item->name,
            'sku'              => $item->sku,
            'barcode'          => $item->barcode,
            'category'         => $item->category,
            'avg_cost'         => number_format((float) $item->average_cost, 2),
            'total_qty'        => number_format($totalQty, 0),
            'inventory_value'  => number_format($inventoryValue, 2),
            'is_low'           => $item->isLowStock(),
            'reorder'          => $item->reorder_level,
            'pricing_anomaly'  => $pricingAnomaly,
            'vendor_costs'     => $vendorCosts,
            'cost_trend'       => $formattedCostTrend,
            'stock'            => $stockByLocation,
            'movements'        => $recentMovements,
        ];
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\PalletPackingSlip;
use App\Models\ScannerReceivingSession;
use App\Services\InventoryCostService;
use App\Services\PackingSlipAnalyzerService;
use App\Services\ReceivingService;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use RuntimeException;
use App\Support\NavVisibility;

class InventoryScanner extends Page
{
    use HasModuleAccess, WithFileUploads;

    protected static string $moduleSlug  = 'inventory';
    protected static ?string $title = 'Inventory Scanner';

    /**
     * HasModuleAccess defaults to "any authenticated user", which let anyone
     * with a login reach a page that receives stock, adjusts quantities and
     * writes inventory movements. Restrict it to the roles that own stock.
     */
    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

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
        return 1;
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-scanner-enhanced';
    }

    public function getSubheading(): ?string
    {
        return 'Complete inventory workflow: Look up items, quick add stock, stage pallets with pending items, and receive shipments by scanning. Supports packing slips or manual entry.';
    }

    // No shouldRegisterNavigation() override: it checked visibility and then
    // returned true regardless of access, so streamers saw a Scan Inventory
    // link that 403'd on click — passesModuleAccessCheck() below is admin and
    // owner only. The trait derives the link from canAccess() instead.

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
    public string $qaName       = ''; // optional: creates the item when the scan is unknown
    public ?array $qaFlash      = null; // ['name', 'qty', 'location', 'created']

    // ── Receive Pallet state ──────────────────────────────────────────────────

    public ?int    $rcvPalletId = null;
    public ?array  $rcvProgress = null; // per-line arrays
    public ?string $rcvFlash    = null;
    public ?string $rcvError    = null;
    public bool    $showCostAdjust = false;
    public ?int    $costAdjustLineId = null;
    public string  $costAdjustNewCost = '';
    public string  $costAdjustReason = '';
    public ?int    $lastScannedLineId = null; // Track which line was just scanned for UI highlighting

    // ── Cost Warnings ─────────────────────────────────────────────────────────────

    public ?array  $costWarnings = null;

    // ── Advanced Pallet Features ──────────────────────────────────────────────

    public bool    $showPalletStaging = false;  // Toggle staging view
    public ?int    $stagingPalletId = null;     // For pre-staging pallets
    public string  $stagingVendorId = '';
    public string  $stagingReference = '';
    public ?string $stagingExpectedDeliveryDate = null; // Expected delivery date
    public ?int    $currentSessionId = null;    // Active scanner session
    public string  $scanMode = 'barcode';       // barcode|camera
    public bool    $cameraActive = false;
    public array   $scannedCodes = [];          // Track scanned codes in session
    public $stagingPackingSlipFile = null;      // Uploaded packing slip file

    // ── Packing Slip AI Analysis ───────────────────────────────────────────

    public ?array  $packingSlipAnalysis = null; // Analysis results
    public bool    $showPackingSlipAnalysis = false; // Show analysis results modal
    public array   $selectedMatchedItems = []; // Selected matched items to apply
    public array   $selectedSuggestedItems = []; // Selected suggested items to create

    // ── Manual Pallet Line Entry ───────────────────────────────────────────

    public bool    $showManualLineEntry = false; // Show manual item entry form
    public string  $manualItemSearch = '';      // Item search/selection
    public ?int    $manualItemId = null;        // Selected item ID
    public ?int    $manualLocationId = null;    // Selected location ID
    public string  $manualCaseCount = '1';      // Number of cases
    public string  $manualQtyPerCase = '1';     // Units per case
    public string  $manualUnitCost = '';        // Optional unit cost
    public string  $manualPreflightCost = '';   // Optional preflight cost
    public string  $manualDescription = '';     // Custom description
    public ?array  $manualItemOptions = null;   // Search results for item picker

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

    /**
     * Modes this page still offers.
     *
     * Receiving and staging left here for the pallet workflow, where the pallet
     * is the thing being worked on rather than one code at a time. Their code
     * stays — the receive-by-scan path is still what a pallet uses — but this
     * page is for fast lookup and quick add, and four tabs made it look like
     * four different jobs done in the same box.
     *
     * Guarded rather than only untabbed: the mode is public state, so a stale
     * page or a saved session could still ask for one that is no longer here
     * and land on a screen with no way back to it.
     */
    public const MODES = ['lookup', 'quickadd'];

    public function switchMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            $mode = 'lookup';
        }

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

    /**
     * Auto-runs the lookup once the scan input settles (the model is debounced
     * 300ms). Scanners that do not send a trailing Enter previously left the
     * code sitting in the box until Look Up was clicked.
     *
     * Lookup only: it is read-only. Quick Add stays explicit so a half-typed
     * code cannot silently book stock in.
     */
    public function updatedScanInput(): void
    {
        if ($this->mode !== 'lookup') {
            return;
        }

        if (mb_strlen(trim($this->scanInput)) < 3) {
            return;
        }

        $this->submitScan();
    }

    /**
     * A code read by the camera, which arrives as a Livewire event rather than
     * as typing.
     *
     * Receiving is done at the pallet with a phone far more often than at a desk
     * with a scanner gun, so the camera has to reach the same place the keyboard
     * does — and submit on its own, because nobody is holding the phone and a
     * box and also tapping "Receive".
     */
    #[On('barcodeScanned')]
    public function barcodeScanned(string $barcode): void
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return;
        }

        $this->scanInput = $barcode;
        $this->submitScan();
    }

    public function submitScan(): void
    {
        match ($this->mode) {
            'lookup'   => $this->doLookup(),
            'quickadd' => $this->doQuickAdd(),
            'receive'  => $this->doReceive(),
            default    => $this->doLookup(),
        };
    }

    public function clearScanInput(): void
    {
        $this->scanInput = '';
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
            $this->costWarnings = null;
            return;
        }

        $this->result = $this->buildResultFromItem($item);
        $this->costWarnings = $this->generateCostWarnings($item);
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
        $createdItem = false;

        if (! $item) {
            // An unknown barcode used to dead-end here. With a name supplied the
            // item is created on the spot, so receiving something new does not
            // mean leaving the scanner to go and add it first.
            $name = trim($this->qaName);

            if ($name === '') {
                $this->qaFlash = [
                    'error' => "No item found for \"{$code}\". Enter an item name above to create it.",
                ];
                return;
            }

            $item = InventoryItem::create([
                'name'      => $name,
                'barcode'   => $code,
                'sku'       => 'VB' . date('ymd') . strtoupper(\Illuminate\Support\Str::random(4)),
                'is_active' => true,
            ]);

            $createdItem = true;
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
            'reason'            => $createdItem ? 'Quick Add via scanner (new item)' : 'Quick Add via scanner',
            'created_by'        => auth()->id(),
        ]);

        $this->qaFlash = [
            'name'     => $item->name,
            'qty'      => $qty,
            'location' => $location->name,
            'created'  => $createdItem,
        ];

        // Clear the name so the next scan does not silently reuse it.
        $this->qaName = '';
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
        $this->lastScannedLineId = null;

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
            $this->lastScannedLineId = $result['line_id'] ?? null;
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

        // If packing slip uploaded, analyze it first before creating pallet
        if ($this->stagingPackingSlipFile) {
            $this->analyzePackingSlip();
            return;
        }

        // No packing slip - create pallet directly
        $this->createStagedPallet();
    }

    private function analyzePackingSlip(): void
    {
        try {
            $file = $this->stagingPackingSlipFile;
            $filePath = $file->store('packing-slips', 'public');

            $analyzer = app(PackingSlipAnalyzerService::class);
            $analysis = $analyzer->analyzePackingSlip($filePath);

            $this->packingSlipAnalysis = $analysis;
            $this->showPackingSlipAnalysis = true;

            Notification::make()
                ->title('Packing Slip Analyzed')
                ->body("Found {$analysis['total_matched']} matched items and {$analysis['total_suggested']} new items to create.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Analysis Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function confirmPackingSlipAnalysis(): void
    {
        if (!$this->packingSlipAnalysis) {
            return;
        }

        try {
            $createdItemsCount = 0;

            // Create suggested items if selected
            if (!empty($this->selectedSuggestedItems)) {
                $suggestions = array_filter(
                    $this->packingSlipAnalysis['suggested_items'],
                    fn ($item, $idx) => in_array($idx, $this->selectedSuggestedItems),
                    ARRAY_FILTER_USE_BOTH
                );

                if (!empty($suggestions)) {
                    $analyzer = app(PackingSlipAnalyzerService::class);
                    $created = $analyzer->createItemsFromSuggestions(
                        array_values($suggestions),
                        auth()->id()
                    );

                    if (!empty($created)) {
                        $createdItemsCount = count($created);
                        Notification::make()
                            ->title('Items Created')
                            ->body($createdItemsCount . ' new inventory items created from packing slip.')
                            ->success()
                            ->send();
                    }
                }
            }

            // Create pallet with uploaded packing slip
            $pallet = $this->createStagedPallet();

            // Create pending pallet lines from packing slip analysis
            if (!empty($this->selectedMatchedItems) || !empty($this->selectedSuggestedItems)) {
                $pendingLinesCount = $this->createPendingPalletLinesFromAnalysis($pallet);

                if ($pendingLinesCount > 0) {
                    Notification::make()
                        ->title('Pending Items Created')
                        ->body($pendingLinesCount . ' items staged as pending. Scan items to confirm receipt.')
                        ->info()
                        ->send();
                }
            }

            $this->showPackingSlipAnalysis = false;
            $this->packingSlipAnalysis = null;
            $this->selectedSuggestedItems = [];
            $this->selectedMatchedItems = [];
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function createPendingPalletLinesFromAnalysis(Pallet $pallet): int
    {
        $count = 0;

        // Create pending lines from matched items
        if (!empty($this->selectedMatchedItems) && !empty($this->packingSlipAnalysis['matched_items'])) {
            foreach ($this->selectedMatchedItems as $idx) {
                if (!isset($this->packingSlipAnalysis['matched_items'][$idx])) {
                    continue;
                }

                $match = $this->packingSlipAnalysis['matched_items'][$idx];
                PalletLine::create([
                    'pallet_id' => $pallet->id,
                    'line_number' => $pallet->lines()->max('line_number') + 1 ?? 1,
                    'description' => $match['item_name'],
                    'vendor_description' => $match['extracted_name'],
                    'inventory_item_id' => $match['item_id'] ?? null,
                    'case_count' => (int) ($match['extracted_qty'] ?? 1),
                    'quantity_per_case' => 1,
                    'preflight_cost' => (float) ($match['extracted_cost'] ?? 0),
                    'line_status' => 'pending',
                ]);
                $count++;
            }
        }

        // Create pending lines from suggested items (which were created above)
        if (!empty($this->selectedSuggestedItems) && !empty($this->packingSlipAnalysis['suggested_items'])) {
            foreach ($this->selectedSuggestedItems as $idx) {
                if (!isset($this->packingSlipAnalysis['suggested_items'][$idx])) {
                    continue;
                }

                $suggestion = $this->packingSlipAnalysis['suggested_items'][$idx];

                // Find the newly created item by SKU
                $item = InventoryItem::where('sku', $suggestion['sku'] ?? null)->first();
                if (!$item) {
                    continue;
                }

                PalletLine::create([
                    'pallet_id' => $pallet->id,
                    'line_number' => $pallet->lines()->max('line_number') + 1 ?? 1,
                    'description' => $suggestion['name'],
                    'vendor_description' => $suggestion['name'],
                    'inventory_item_id' => $item->id,
                    'case_count' => (int) ($suggestion['qty'] ?? 1),
                    'quantity_per_case' => 1,
                    'preflight_cost' => (float) ($suggestion['unit_cost'] ?? 0),
                    'line_status' => 'pending',
                ]);
                $count++;
            }
        }

        return $count;
    }

    public function cancelPackingSlipAnalysis(): void
    {
        $this->showPackingSlipAnalysis = false;
        $this->packingSlipAnalysis = null;
        $this->selectedSuggestedItems = [];
        $this->selectedMatchedItems = [];
    }

    public function openManualLineEntry(): void
    {
        if (! $this->stagingPalletId) {
            Notification::make()
                ->title('Error')
                ->body('Stage a pallet first before adding items.')
                ->danger()
                ->send();
            return;
        }

        $this->showManualLineEntry = true;
        $this->resetManualLineForm();
        $this->manualLocationId = $this->qaLocationId ?: InventoryLocation::orderBy('name')->value('id');
    }

    public function searchManualItems(): void
    {
        $search = trim($this->manualItemSearch);
        if (strlen($search) < 2) {
            $this->manualItemOptions = null;
            return;
        }

        $this->manualItemOptions = InventoryItem::where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%");
        })
        ->limit(10)
        ->get(['id', 'name', 'sku', 'barcode', 'unit_cost'])
        ->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'unit_cost' => (float) $item->unit_cost,
        ])
        ->toArray();
    }

    public function selectManualItem(int $itemId): void
    {
        $item = InventoryItem::find($itemId);
        if ($item) {
            $this->manualItemId = $item->id;
            $this->manualItemSearch = $item->name;
            $this->manualItemOptions = null;
            if (! $this->manualUnitCost) {
                $this->manualUnitCost = (string) ((float) $item->unit_cost ?: '');
            }
        }
    }

    public function addManualLine(): void
    {
        if (! $this->stagingPalletId || ! $this->manualItemId || ! $this->manualLocationId) {
            Notification::make()
                ->title('Error')
                ->body('Item, location, and pallet are required.')
                ->danger()
                ->send();
            return;
        }

        try {
            $pallet = Pallet::find($this->stagingPalletId);
            $item = InventoryItem::find($this->manualItemId);
            $location = InventoryLocation::find($this->manualLocationId);

            if (! $pallet || ! $item || ! $location) {
                throw new RuntimeException('Pallet, item, or location not found.');
            }

            $caseCount = (int) $this->manualCaseCount ?: 1;
            $qtyPerCase = (float) $this->manualQtyPerCase ?: 1;
            $unitCost = (float) $this->manualUnitCost ?: 0;
            $preflightCost = (float) $this->manualPreflightCost ?: 0;

            PalletLine::create([
                'pallet_id' => $pallet->id,
                'line_number' => $pallet->lines()->max('line_number') + 1 ?? 1,
                'description' => $this->manualDescription ?: $item->name,
                'vendor_description' => $this->manualDescription ?: $item->name,
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
                'case_count' => $caseCount,
                'quantity_per_case' => $qtyPerCase,
                'unit_cost' => $unitCost,
                'preflight_cost' => $preflightCost,
                'line_status' => 'pending',
            ]);

            Notification::make()
                ->title('Item Added')
                ->body($item->name . ' added as pending item.')
                ->success()
                ->send();

            $this->resetManualLineForm();
            $this->showManualLineEntry = false;
            $this->refreshPalletProgress();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cancelManualLineEntry(): void
    {
        $this->showManualLineEntry = false;
        $this->resetManualLineForm();
    }

    #[On('clearScannedHighlight')]
    public function clearScannedHighlight(): void
    {
        $this->lastScannedLineId = null;
    }

    private function resetManualLineForm(): void
    {
        $this->manualItemSearch = '';
        $this->manualItemId = null;
        $this->manualLocationId = $this->qaLocationId ?: InventoryLocation::orderBy('name')->value('id');
        $this->manualCaseCount = '1';
        $this->manualQtyPerCase = '1';
        $this->manualUnitCost = '';
        $this->manualPreflightCost = '';
        $this->manualDescription = '';
        $this->manualItemOptions = null;
    }

    private function createStagedPallet(): Pallet
    {
        $pallet = Pallet::create([
            'vendor_id' => $this->stagingVendorId,
            'reference' => $this->stagingReference,
            'status' => 'pending',
            'stage' => Pallet::STAGE_STAGED,
            'staged_at' => now(),
            'expected_delivery_date' => $this->stagingExpectedDeliveryDate ? \Carbon\Carbon::parse($this->stagingExpectedDeliveryDate) : null,
            'created_by' => auth()->id(),
        ]);

        // Handle packing slip upload if provided
        if ($this->stagingPackingSlipFile) {
            $file = $this->stagingPackingSlipFile;
            $filePath = $file->store('packing-slips', 'public');
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $originalName = $file->getClientOriginalName();

            PalletPackingSlip::create([
                'pallet_id' => $pallet->id,
                'file_path' => $filePath,
                'original_filename' => $originalName,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'uploaded_by' => auth()->id(),
            ]);
        }

        $this->showPalletStaging = false;
        $this->stagingPalletId = $pallet->id;
        $this->stagingVendorId = '';
        $this->stagingReference = '';
        $this->stagingExpectedDeliveryDate = null;
        $this->stagingPackingSlipFile = null;

        Notification::make()
            ->title('Pallet Staged')
            ->body('Ready to start receiving items.')
            ->success()
            ->send();

        // Start receiving session for this pallet
        $this->startReceivingSession($pallet->id);

        return $pallet;
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

    public function exportSessionReport(): void
    {
        if (!$this->currentSessionId) {
            Notification::make()
                ->title('Error')
                ->body('No active session to export')
                ->danger()
                ->send();
            return;
        }

        $session = ScannerReceivingSession::find($this->currentSessionId);
        if (!$session) {
            return;
        }

        try {
            $reportService = app(ReceivingReportService::class);
            $filePath = $reportService->generateSessionReport($session);

            Notification::make()
                ->title('Report Generated')
                ->body('Session report created and available in reports folder')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function exportPalletReport(): void
    {
        if (!$this->rcvPalletId) {
            Notification::make()
                ->title('Error')
                ->body('No pallet selected')
                ->danger()
                ->send();
            return;
        }

        $pallet = Pallet::find($this->rcvPalletId);
        if (!$pallet) {
            return;
        }

        try {
            $reportService = app(ReceivingReportService::class);
            $filePath = $reportService->generatePalletReport($pallet);

            Notification::make()
                ->title('Report Generated')
                ->body('Pallet report created and available in reports folder')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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

    public function bulkReceivePallet(): void
    {
        if (!$this->rcvPalletId) {
            $this->rcvError = 'No pallet selected.';
            return;
        }

        $pallet = Pallet::findOrFail($this->rcvPalletId);
        $lines = $pallet->lines()->get();

        if ($lines->isEmpty()) {
            $this->rcvError = 'This pallet has no line items to receive.';
            return;
        }

        // Check if all lines are mapped
        $unmappedLines = $lines->filter(fn ($l) => !$l->inventory_item_id);
        if ($unmappedLines->isNotEmpty()) {
            $this->rcvError = 'Cannot bulk receive: ' . count($unmappedLines) . ' line(s) are unmapped.';
            return;
        }

        try {
            $receivingService = app(ReceivingService::class);
            $receivingService->receivePallet($pallet);

            $pallet->update([
                'status' => 'received',
                'stage' => Pallet::STAGE_RECEIVED,
                'received_date' => now(),
            ]);

            $this->rcvFlash = 'Pallet fully received! ' . count($lines) . ' line items processed.';
            $this->rcvPalletId = null;
            $this->rcvProgress = null;

            Notification::make()
                ->title('Pallet Received')
                ->body('All ' . count($lines) . ' line items have been received and inventory updated.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            $this->rcvError = 'Bulk receive failed: ' . $e->getMessage();
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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

    public function getPendingItemsProperty()
    {
        if (! $this->rcvPalletId) {
            return [];
        }

        $pallet = Pallet::find($this->rcvPalletId);
        if (! $pallet) {
            return [];
        }

        return $pallet->lines()
            ->where('line_status', 'pending')
            ->with('inventoryItem')
            ->get()
            ->map(fn ($line) => [
                'line_id'             => $line->id,
                'line_number'         => $line->line_number,
                'item_name'           => $line->inventoryItem?->name ?? ($line->description ?? 'Unmapped'),
                'sku'                 => $line->inventoryItem?->sku,
                'barcode'             => $line->inventoryItem?->barcode,
                'description'         => $line->description,
                'vendor_description'  => $line->vendor_description,
                'case_count'          => (int) $line->case_count,
                'quantity_per_case'   => (float) $line->quantity_per_case,
                'preflight_cost'      => (float) ($line->preflight_cost ?? 0),
                'unit_cost'           => (float) $line->unit_cost,
                'is_mapped'           => $line->isFullyMapped(),
            ])
            ->toArray();
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
                'line_status'    => $line->line_status,
            ];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generateCostWarnings(InventoryItem $item): ?array
    {
        $warnings = [];

        // Check if average cost is missing or zero
        $avgCost = (float) ($item->average_cost ?? 0);
        if ($avgCost <= 0 && $item->totalQuantity() > 0) {
            $warnings[] = [
                'type' => 'no_cost',
                'title' => 'Missing Cost Data',
                'message' => 'This item has stock but no cost history. Receive items to calculate average cost.',
                'severity' => 'warning',
            ];
        }

        // Check if total units received is low
        if ((float) $item->total_units_received < 10 && (float) $item->total_units_received > 0) {
            $warnings[] = [
                'type' => 'low_history',
                'title' => 'Limited Cost History',
                'message' => 'Only ' . number_format((float) $item->total_units_received, 0) . ' units received. Average cost may be unreliable.',
                'severity' => 'info',
            ];
        }

        // Check for high cost variance
        $costService = app(InventoryCostService::class);
        $costBreakdown = $costService->getCostBreakdown($item);
        if (!empty($costBreakdown)) {
            $costs = array_column($costBreakdown, 'average_cost');
            if (!empty($costs)) {
                $minCost = min($costs);
                $maxCost = max($costs);
                if ($minCost > 0) {
                    $variance = (($maxCost - $minCost) / $minCost) * 100;
                    if ($variance > 50) {
                        $warnings[] = [
                            'type' => 'high_variance',
                            'title' => 'High Price Variance',
                            'message' => 'Prices vary ' . round($variance, 1) . '% across vendors. Consider cost adjustment.',
                            'severity' => 'alert',
                        ];
                    }
                }
            }
        }

        return !empty($warnings) ? $warnings : null;
    }

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
                            'min_cost' => (float) $minCost,
                            'max_cost' => (float) $maxCost,
                        ];
                    }
                }
            }

            // Format vendor costs for display
            foreach ($costBreakdown as $key => $data) {
                $vendorCosts[] = [
                    'vendor_name' => $data['vendor_name'],
                    'avg_cost' => (float) $data['average_cost'],
                    'qty' => (int) $data['total_qty'],
                ];
            }
        }

        // Get cost trend (last 10 receipts)
        $costTrend = $costService->getCostTrend($item, 10);
        $formattedCostTrend = array_map(function ($ct) {
            $date = $ct['date'];
            if (is_string($date)) {
                $dateStr = \Carbon\Carbon::parse($date)->format('M d, Y');
            } elseif ($date instanceof \Carbon\Carbon) {
                $dateStr = $date->format('M d, Y');
            } else {
                $dateStr = '—';
            }
            return [
                'date' => $dateStr,
                'vendor' => $ct['vendor'],
                'cost' => (float) $ct['unit_cost'],
                'qty' => (int) $ct['quantity'],
            ];
        }, $costTrend);

        $stockByLocation = $item->stock->map(fn ($s) => [
            'location'    => $s->location?->name ?? 'Unknown',
            'qty'         => (float) $s->quantity,
            'id'          => $s->id,
            'location_id' => $s->inventory_location_id,
        ])->values()->toArray();

        $recentMovements = $item->movements->map(fn (InventoryMovement $m) => [
            'type'     => $m->movement_type,
            // Signed. `quantity` is stored as an absolute value, so the list
            // used to print "+" against every row including the reductions —
            // it was testing `qty > 0` on a column that is never negative.
            'qty'      => $m->signedChange(),
            'label'    => $m->changeLabel(),
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
            // Numbers, not display strings. These used to arrive pre-formatted,
            // which every consumer then had to know about: two views ran
            // number_format() over them a second time and blew up the moment a
            // value crossed a thousand and picked up a comma, and the stock
            // badge compared "1,234" against a number as a string. A value that
            // sometimes formats itself is a value nobody can use safely, so the
            // formatting belongs in the view that prints it.
            'avg_cost'         => (float) $item->average_cost,
            'total_qty'        => (float) $totalQty,
            'inventory_value'  => (float) $inventoryValue,
            'is_low'           => $item->isLowStock(),
            'reorder'          => (float) $item->reorder_level,
            'pricing_anomaly'  => $pricingAnomaly,
            'vendor_costs'     => $vendorCosts,
            'cost_trend'       => $formattedCostTrend,
            'stock'            => $stockByLocation,
            'movements'        => $recentMovements,
        ];
    }
}

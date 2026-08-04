<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\ScannerReceivingSession;
use App\Services\InventoryCostService;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

class MobileScannerApp extends Page
{
    use HasModuleAccess, WithFileUploads;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Mobile Scanner';

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
        return 'Mobile Scanner';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function getView(): string
    {
        return 'filament.pages.mobile-scanner-app';
    }

    public function getSubheading(): ?string
    {
        return 'Warehouse scanner optimized for phone camera barcode scanning';
    }

    // ── State ──────────────────────────────────────────────────────────────

    public string $scanInput = '';
    public ?int $activePalletId = null;
    public ?int $activeSessionId = null;
    public ?array $scannedItem = null;
    public bool $showItemDetail = false;
    public array $sessionItems = [];
    public string $mode = 'receive'; // receive | quickadd | lookup

    // Quick Add state
    public ?int $qaLocationId = null;
    public string $qaQty = '1';

    // ── Lifecycle ──────────────────────────────────────────────────────────

    public function mount(): void
    {
        $loc = InventoryLocation::orderBy('name')->first();
        if ($loc) {
            $this->qaLocationId = $loc->id;
        }
    }

    // ── Computed Properties ────────────────────────────────────────────────

    public function getLocationsProperty()
    {
        return InventoryLocation::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    }

    public function getPalletsProperty()
    {
        return Pallet::where('status', '!=', 'processed')
            ->orderByRaw("CASE WHEN status != 'received' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get(['id', 'reference', 'status']);
    }

    public function getActiveSessionProperty(): ?ScannerReceivingSession
    {
        return $this->activeSessionId ? ScannerReceivingSession::find($this->activeSessionId) : null;
    }

    // ── Scanner Actions ────────────────────────────────────────────────────

    public function submitScan(): void
    {
        $code = trim($this->scanInput);
        $this->scanInput = '';

        if ($code === '') {
            return;
        }

        match ($this->mode) {
            'receive' => $this->handleReceiveScan($code),
            'quickadd' => $this->handleQuickAddScan($code),
            'lookup' => $this->handleLookupScan($code),
        };
    }

    private function handleReceiveScan(string $code): void
    {
        if (!$this->activePalletId) {
            Notification::make()
                ->title('Select Pallet First')
                ->body('Choose a pallet to receive items')
                ->warning()
                ->send();
            return;
        }

        $item = InventoryItem::findByScan($code);
        if (!$item) {
            Notification::make()
                ->title('Item Not Found')
                ->body("Barcode/SKU: $code")
                ->danger()
                ->send();
            return;
        }

        // Check if item is on this pallet
        $palletLine = PalletLine::where('pallet_id', $this->activePalletId)
            ->where('inventory_item_id', $item->id)
            ->first();

        if (!$palletLine) {
            Notification::make()
                ->title('Not on Pallet')
                ->body("Item {$item->name} not expected in this pallet")
                ->warning()
                ->send();
            return;
        }

        // Show item details
        $this->scannedItem = [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'cases' => (int) $palletLine->case_count,
            'qty' => (float) $palletLine->quantity,
            'unit_cost' => number_format($palletLine->unit_cost, 2),
            'total_cost' => number_format($palletLine->quantity * $palletLine->unit_cost, 2),
            'avg_cost' => number_format($item->average_cost, 2),
        ];
        $this->showItemDetail = true;

        // Add to session
        if ($this->activeSessionId) {
            $this->sessionItems[] = $this->scannedItem;
            $session = ScannerReceivingSession::find($this->activeSessionId);
            if ($session) {
                $session->increment('items_scanned');
            }
        }

        Notification::make()
            ->title('✓ Item Received')
            ->body("{$item->name} ({$palletLine->quantity} units)")
            ->success()
            ->send();
    }

    private function handleQuickAddScan(string $code): void
    {
        if (!$this->qaLocationId) {
            Notification::make()
                ->title('Select Location First')
                ->body('Choose a location to add stock')
                ->warning()
                ->send();
            return;
        }

        $item = InventoryItem::findByScan($code);
        if (!$item) {
            Notification::make()
                ->title('Item Not Found')
                ->body("Barcode/SKU: $code")
                ->danger()
                ->send();
            return;
        }

        $qty = (float) $this->qaQty;
        $stock = $item->stock()
            ->where('inventory_location_id', $this->qaLocationId)
            ->first();

        if ($stock) {
            $stock->increment('quantity', $qty);
        } else {
            $item->stock()->create([
                'inventory_location_id' => $this->qaLocationId,
                'quantity' => $qty,
            ]);
        }

        Notification::make()
            ->title('✓ Stock Added')
            ->body("{$item->name}: +{$qty} units")
            ->success()
            ->send();
    }

    private function handleLookupScan(string $code): void
    {
        $item = InventoryItem::findByScan($code);
        if (!$item) {
            Notification::make()
                ->title('Item Not Found')
                ->body("Barcode/SKU: $code")
                ->danger()
                ->send();
            return;
        }

        $costService = app(InventoryCostService::class);
        $this->scannedItem = [
            'id' => $item->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'qty' => number_format($item->totalQuantity(), 2),
            'avg_cost' => number_format($item->average_cost, 2),
            'value' => number_format($costService->calculateInventoryValue($item), 2),
            'reorder' => $item->reorder_level,
            'is_low' => $item->isLowStock(),
        ];
        $this->showItemDetail = true;
    }

    public function startReceiving(): void
    {
        if (!$this->activePalletId) {
            return;
        }

        $pallet = Pallet::find($this->activePalletId);
        if (!$pallet) {
            return;
        }

        $session = ScannerReceivingSession::create([
            'pallet_id' => $this->activePalletId,
            'user_id' => auth()->id(),
            'mode' => 'camera',
            'started_at' => now(),
        ]);

        $this->activeSessionId = $session->id;
        $this->sessionItems = [];
        $this->mode = 'receive';

        Notification::make()
            ->title('✓ Session Started')
            ->body("Pallet {$pallet->reference}")
            ->success()
            ->send();
    }

    public function endSession(): void
    {
        if ($this->activeSessionId) {
            $session = ScannerReceivingSession::find($this->activeSessionId);
            if ($session) {
                $session->update(['ended_at' => now()]);
            }
        }

        $this->activeSessionId = null;
        $this->activePalletId = null;
        $this->sessionItems = [];

        Notification::make()
            ->title('✓ Session Ended')
            ->success()
            ->send();
    }

    public function closeItemDetail(): void
    {
        $this->showItemDetail = false;
        $this->scannedItem = null;
    }
}

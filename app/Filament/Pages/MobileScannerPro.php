<?php

namespace App\Filament\Pages;

use App\Models\ScanSession;
use App\Models\Product;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Services\ScanningService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use App\Support\NavVisibility;

class MobileScannerPro extends Page
{
    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-qr-code';
    }

    public static function getNavigationLabel(): string
    {
        return 'Mobile Scanner Pro';
    }

    public static function getNavigationSort(): ?int
    {
        return 0;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }

    public string $mode = 'lookup';
    public bool $sessionActive = false;
    public array $scannedItems = [];
    public array $duplicates = [];
    public int $totalScanned = 0;

    public ?ScanSession $session = null;
    public ?int $selectedPalletId = null;
    public ?int $selectedLocationId = null;

    private ScanningService $scanningService;

    // No __construct() here: Filament's Page has no constructor to call, and
    // building the service at construction runs before the container is ready.
    // Resolved on demand instead.
    protected function scanningService(): ScanningService
    {
        return $this->scanningService ??= app(ScanningService::class);
    }

    public function getPalletsProperty()
    {
        return Pallet::where('status', '!=', 'received')
            ->orderByDesc('created_at')
            ->get(['id', 'reference']);
    }

    public function getLocationsProperty()
    {
        return InventoryLocation::orderBy('name')->get(['id', 'name']);
    }

    public function selectMode(string $mode): void
    {
        $this->mode = $mode;
        $this->reset(['sessionActive', 'scannedItems', 'duplicates', 'totalScanned', 'session']);
    }

    public function startScanning(): void
    {
        $metadata = [
            'mode' => $this->mode,
            'employee' => auth()->user()->name,
        ];

        if ($this->selectedLocationId) {
            $metadata['location_id'] = $this->selectedLocationId;
        }

        if ($this->selectedPalletId) {
            $metadata['pallet_id'] = $this->selectedPalletId;
        }

        $this->session = $this->scanningService()->createSession(
            $this->mode,
            $this->selectedPalletId,
            'Pallet',
            $metadata
        );

        $this->sessionActive = true;
        $this->scannedItems = [];
        $this->duplicates = [];
        $this->totalScanned = 0;
    }

    #[On('barcodeScanned')]
    public function handleBarcodeScan($barcode): void
    {
        if (!$this->session) {
            return;
        }

        $result = $this->scanningService()->scanBarcode($barcode, $this->mode);

        if ($result['success']) {
            $this->scannedItems[] = $result['item'];
            $this->totalScanned++;
            $this->dispatch('scanSuccess');
        } elseif ($result['type'] === 'warning') {
            $this->duplicates[] = [
                'name' => $result['message'],
                'barcode' => $barcode,
                'timestamp' => now()->format('H:i:s'),
            ];
            $this->dispatch('scanWarning');
        } else {
            $this->dispatch('scanError');
        }

        // Auto-scroll to latest item
        $this->dispatch('scrollToLatest');
    }

    public function endSession(): void
    {
        if ($this->session) {
            $this->scanningService()->endSession();
            $this->session->refresh();
        }

        $this->sessionActive = false;
    }

    public function saveSession(): void
    {
        if (!$this->session) {
            return;
        }

        try {
            $location = $this->selectedLocationId
                ? InventoryLocation::find($this->selectedLocationId)
                : InventoryLocation::where('type', 'receiving')->first();

            $result = $this->scanningService()->commitScansToInventory($location);

            if ($result['success']) {
                Notification::make()
                    ->title('Inventory Updated')
                    ->body("Successfully added {$result['created']} items to inventory")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Partial Save')
                    ->body($result['message'] ?? 'Some items failed to save')
                    ->warning()
                    ->send();
            }

            $this->scanningService()->endSession();

            // Redirect to session review/results page
            $this->redirect(route('filament.admin.pages.scan-results', [
                'session_id' => $this->session?->id,
            ]));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updateStats(): void
    {
        if ($this->session) {
            $this->session->refresh();
            $this->totalScanned = $this->session->item_count;
        }
    }

    public function getView(): string
    {
        return 'filament.pages.mobile-scanner-pro';
    }
}

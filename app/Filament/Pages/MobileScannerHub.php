<?php

namespace App\Filament\Pages;

use App\Models\ScanSession;
use App\Models\InventoryItem;
use Filament\Pages\Page;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;

class MobileScannerHub extends Page
{
    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-queue-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Mobile Scanner';
    }

    public static function getNavigationSort(): ?int
    {
        return -1;
    }

    #[Validate('in:inventory,receiving,shipping,lookup')]
    public string $mode = 'lookup';

    public bool $showCamera = false;
    public string $sessionStatus = 'Ready';
    public ?ScanSession $currentSession = null;
    public array $scannedItems = [];
    public array $duplicates = [];
    public int $totalScanned = 0;

    public function mount(): void
    {
        $this->currentSession = null;
    }

    public function selectMode(string $mode): void
    {
        $this->mode = $mode;
        $this->startSession();
    }

    public function startSession(): void
    {
        $this->currentSession = ScanSession::create([
            'user_id' => auth()->id(),
            'type' => $this->mode,
            'started_at' => now(),
            'status' => 'active',
        ]);

        $this->scannedItems = [];
        $this->duplicates = [];
        $this->totalScanned = 0;
        $this->sessionStatus = 'Scanning...';
        $this->showCamera = true;
    }

    #[On('barcodeScanned')]
    public function handleBarcodeScan($barcode): void
    {
        if (!$this->currentSession) {
            $this->sessionStatus = 'No active session';
            return;
        }

        $item = InventoryItem::where('barcode', trim($barcode))->first();

        if (!$item) {
            $this->sessionStatus = "Barcode not found: {$barcode}";
            $this->dispatch('scanError');
            return;
        }

        // Check for duplicate
        $isDuplicate = collect($this->scannedItems)->contains('barcode', $barcode);

        if ($isDuplicate) {
            $this->duplicates[] = [
                'barcode' => $barcode,
                'name' => $item->name,
                'scanned_at' => now()->format('H:i:s'),
            ];
            $this->sessionStatus = "Duplicate: {$item->name}";
            $this->dispatch('scanWarning');
            return;
        }

        // Log the scan
        $this->currentSession->scans()->create([
            'barcode' => trim($barcode),
            'inventory_item_id' => $item->id,
            'result' => 'success',
        ]);

        // Add to list
        $this->scannedItems[] = [
            'barcode' => $barcode,
            'name' => $item->name,
            'sku' => $item->sku,
            'quantity' => $item->quantity_on_hand,
            'location' => $item->locations()->first()?->name ?? 'Unknown',
            'scanned_at' => now()->format('H:i:s'),
        ];

        $this->totalScanned++;
        $this->currentSession->update(['item_count' => $this->totalScanned]);
        $this->sessionStatus = "✓ {$item->name} ({$this->totalScanned} items)";
        $this->dispatch('scanSuccess');
    }

    public function endSession(): void
    {
        if ($this->currentSession) {
            $this->currentSession->update([
                'ended_at' => now(),
                'status' => 'completed',
            ]);
        }

        $this->showCamera = false;
        $this->sessionStatus = 'Session ended';
    }

    public function clearSession(): void
    {
        if ($this->currentSession) {
            $this->currentSession->delete();
        }

        $this->currentSession = null;
        $this->scannedItems = [];
        $this->duplicates = [];
        $this->totalScanned = 0;
        $this->sessionStatus = 'Ready';
        $this->showCamera = false;
    }

    public function getView(): string
    {
        return 'filament.pages.mobile-scanner-hub';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}

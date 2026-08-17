<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;

class QuickAddStock extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    public ?Product $product = null;
    public float $quantity = 1;
    public ?float $unitCost = null;
    public ?int $locationId = null;
    public string $barcode = '';
    public array $recentAdds = [];

    private InventoryService $inventoryService;

    public function mount(): void
    {
        $this->inventoryService = app(InventoryService::class);
    }

    public function getLocationsProperty()
    {
        return InventoryLocation::orderBy('name')->get(['id', 'name', 'type']);
    }

    #[On('barcodeScanned')]
    public function handleBarcodeScan(string $barcode): void
    {
        $this->barcode = trim($barcode);
        $this->scanBarcode();
    }

    public function scanBarcode(): void
    {
        if (empty($this->barcode)) {
            Notification::make()
                ->title('Barcode Required')
                ->body('Please enter or scan a barcode')
                ->warning()
                ->send();
            return;
        }

        $product = Product::findByScan($this->barcode);

        if (!$product) {
            Notification::make()
                ->title('Product Not Found')
                ->body("Barcode '{$this->barcode}' not found in system")
                ->danger()
                ->send();
            $this->barcode = '';
            return;
        }

        $this->product = $product;
        $this->quantity = 1;
        $this->unitCost = $product->average_cost ?? $product->unit_cost;

        Notification::make()
            ->title('Product Found')
            ->body("Scanned: {$product->name}")
            ->success()
            ->send();
    }

    public function incrementQuantity(): void
    {
        $this->quantity += 1;
    }

    public function decrementQuantity(): void
    {
        if ($this->quantity > 1) {
            $this->quantity -= 1;
        }
    }

    public function addStock(): void
    {
        if (!$this->product) {
            Notification::make()
                ->title('No Product Selected')
                ->body('Please scan a barcode first')
                ->warning()
                ->send();
            return;
        }

        if (!$this->locationId) {
            Notification::make()
                ->title('Location Required')
                ->body('Please select a storage location')
                ->warning()
                ->send();
            return;
        }

        try {
            $location = InventoryLocation::findOrFail($this->locationId);

            InventoryLot::create([
                'product_id' => $this->product->id,
                'quantity' => $this->quantity,
                'remaining_quantity' => $this->quantity,
                'unit_cost' => $this->unitCost ?? 0,
                'source' => InventoryLot::SOURCE_ADJUSTMENT,
                'status' => InventoryLot::STATUS_ACTIVE,
                'received_at' => now(),
                'received_by' => auth()->id(),
            ]);

            $this->inventoryService->addStock(
                $this->product,
                $location,
                $this->quantity,
                'opening',
                'Quick add via mobile',
                $this->unitCost
            );

            $this->recentAdds[] = [
                'product_name' => $this->product->name,
                'quantity' => $this->quantity,
                'location' => $location->name,
                'timestamp' => now()->format('H:i:s'),
            ];

            Notification::make()
                ->title('Stock Added')
                ->body("{$this->quantity} units of {$this->product->name} added to {$location->name}")
                ->success()
                ->send();

            $this->product = null;
            $this->barcode = '';
            $this->quantity = 1;
            $this->unitCost = null;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getView(): string
    {
        return 'filament.pages.quick-add-stock';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-plus-circle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Quick Add Stock';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }
}

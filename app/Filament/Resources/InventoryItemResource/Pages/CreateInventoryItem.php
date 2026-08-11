<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;
    protected static ?string $view = 'filament.pages.create-inventory-item';

    // Container scan mode properties
    public bool $containerScanMode = false;
    public ?string $containerName = null;
    public ?string $containerBarcode = null;
    public array $scannedItems = [];
    public string $barcodeInput = '';
    public int $scanStep = 1; // 1: setup, 2: scan, 3: review

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Item created successfully';
    }

    protected function getRedirectUrl(): string
    {
        // Default to edit page if record exists
        if ($this->record?->id) {
            return $this->getResource()::getUrl('edit', ['record' => $this->record]);
        }

        // Fall back to list page if record creation failed
        return $this->getResource()::getUrl('index');
    }

    public function enableContainerScan(): void
    {
        $this->containerScanMode = true;
        $this->scanStep = 1;
        $this->dispatch('focus-container-name');
    }

    public function disableContainerScan(): void
    {
        $this->containerScanMode = false;
        $this->scanStep = 1;
        $this->containerName = null;
        $this->containerBarcode = null;
        $this->scannedItems = [];
    }

    public function startScanning(): void
    {
        if (empty($this->containerName)) {
            Notification::make()
                ->title('Container name required')
                ->warning()
                ->send();
            return;
        }

        $this->scannedItems = [];
        $this->scanStep = 2;
        $this->dispatch('focus-barcode-input');
    }

    #[On('submit-barcode')]
    public function submitBarcode(): void
    {
        $barcode = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if (empty($barcode)) {
            return;
        }

        // Check if already scanned
        if (collect($this->scannedItems)->contains('barcode', $barcode)) {
            Notification::make()
                ->title('Already scanned')
                ->body('This barcode was already added to the list')
                ->warning()
                ->send();
            return;
        }

        // Auto-generate name and SKU
        $itemNumber = count($this->scannedItems) + 1;
        $autoName = "{$this->containerName} - Item {$itemNumber}";
        $autoSku = 'VB' . date('ymd') . strtoupper(Str::random(4));

        $this->scannedItems[] = [
            'id' => Str::uuid()->toString(),
            'name' => $autoName,
            'sku' => $autoSku,
            'barcode' => $barcode,
        ];

        Notification::make()
            ->title("Item {$itemNumber} scanned")
            ->success()
            ->send();

        $this->dispatch('barcode-scanned');
    }

    public function removeScannedItem(string $id): void
    {
        $this->scannedItems = collect($this->scannedItems)
            ->reject(fn ($item) => $item['id'] === $id)
            ->values()
            ->toArray();
    }

    public function finishScanning(): void
    {
        if (empty($this->scannedItems)) {
            Notification::make()
                ->title('No items scanned')
                ->warning()
                ->send();
            return;
        }

        $this->scanStep = 3;
    }

    public function goBackToScanning(): void
    {
        $this->scanStep = 2;
    }

    public function createContainerWithItems(): void
    {
        if (empty($this->scannedItems)) {
            Notification::make()
                ->title('No items to create')
                ->danger()
                ->send();
            return;
        }

        try {
            // Create container item
            $container = InventoryItem::create([
                'name' => $this->containerName,
                'sku' => 'VB' . date('ymd') . strtoupper(Str::random(4)),
                'barcode' => $this->containerBarcode,
                'is_active' => true,
                'is_container' => true,
            ]);

            // Create individual items and relationships
            foreach ($this->scannedItems as $itemData) {
                $item = InventoryItem::create([
                    'name' => $itemData['name'],
                    'sku' => $itemData['sku'],
                    'barcode' => $itemData['barcode'],
                    'is_active' => true,
                    'is_container' => false,
                ]);

                // Create relationship: container contains this item
                $container->childContents()->create([
                    'child_inventory_item_id' => $item->id,
                    'quantity_per_parent' => 1,
                    'unit_type' => 'item',
                ]);
            }

            Notification::make()
                ->title('✓ Container and items created')
                ->body("{$this->containerName} with " . count($this->scannedItems) . " items")
                ->success()
                ->send();

            // Redirect to container view
            $this->redirect(InventoryItemResource::getUrl('view', ['record' => $container]));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error creating container')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function mutateFormDataBeforeSave(array $data): array
    {
        // Remove the initial stock fields from the item data
        $initialLocationId = $data['initial_stock_location_id'] ?? null;
        $initialQuantity = $data['initial_stock_quantity'] ?? null;
        $initialCost = $data['initial_stock_cost'] ?? null;

        unset($data['initial_stock_location_id']);
        unset($data['initial_stock_quantity']);
        unset($data['initial_stock_cost']);

        // Store for use after create
        $this->initialStockData = compact('initialLocationId', 'initialQuantity', 'initialCost');

        return $data;
    }

    protected function afterCreate(): void
    {
        if (!isset($this->initialStockData)) {
            return;
        }

        $data = $this->initialStockData;
        $locationId = $data['initialLocationId'];
        $quantity = $data['initialQuantity'];
        $cost = $data['initialCost'];

        // Only add stock if location and quantity are provided
        if (!$locationId || !$quantity) {
            return;
        }

        try {
            $location = InventoryLocation::findOrFail($locationId);
            $inventory = app(InventoryService::class);

            // Use provided cost or fall back to unit cost
            $unitCost = $cost ?? $this->record->unit_cost;

            $inventory->addStock(
                $this->record,
                $location,
                (float) $quantity,
                'opening',
                'Initial stock added on creation',
                (float) $unitCost,
            );

            Notification::make()
                ->title('Initial stock added')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error adding initial stock')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

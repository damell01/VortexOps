<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

class CreateInventoryItem extends CreateRecord
{
    use HasWizard;

    protected static string $resource = InventoryItemResource::class;

    /**
     * Four steps over the resource's own sections — no second copy of any
     * field, so create and edit can't drift apart.
     *
     * @return array<int, Step>
     */
    public function getSteps(): array
    {
        return [
            Step::make('Item Details')
                ->description('What it is')
                ->icon('heroicon-o-identification')
                ->schema([
                    InventoryItemResource::itemIdentificationSection(),
                    InventoryItemResource::containerSettingsSection(),
                    InventoryItemResource::classificationSection(),
                    InventoryItemResource::pricingSection(),
                ]),

            Step::make('Stock & Location')
                ->description('Where it lives')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    InventoryItemResource::initialStockSection(),
                ]),

            Step::make('Item Settings')
                ->description('Notes and extras')
                ->icon('heroicon-o-adjustments-horizontal')
                ->schema([
                    InventoryItemResource::notesSection(),
                ]),

            Step::make('Review & Save')
                ->description('Check it over')
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Placeholder::make('review')
                        ->label('')
                        ->columnSpanFull()
                        ->content(fn (Get $get) => view('filament.partials.new-item-review', [
                            'name'      => $get('name'),
                            'sku'       => $get('sku'),
                            'barcode'   => $get('barcode'),
                            'category'  => $get('category'),
                            'container' => (bool) $get('is_container'),
                            'unitCost'  => $get('unit_cost'),
                            'reorder'   => $get('reorder_level'),
                            'quantity'  => $get('initial_stock_quantity'),
                            'active'    => (bool) $get('is_active'),
                        ])),
                ]),
        ];
    }

    /** Steps are clickable so a correction doesn't mean walking back. */
    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    // Container scan mode properties
    public bool $containerScanMode = false;
    public ?string $containerName = null;
    public ?string $containerBarcode = null;
    public array $scannedItems = [];
    public string $barcodeInput = '';
    public int $scanStep = 1; // 1: setup, 2: scan, 3: review

    public function getView(): string
    {
        return 'filament.pages.create-inventory-item-wrapper';
    }

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
        $this->processBarcodeValue($barcode);
    }

    public function submitBarcodeWithValue(string $value): void
    {
        $barcode = trim($value);
        $this->barcodeInput = '';
        $this->processBarcodeValue($barcode);
    }

    protected function processBarcodeValue(string $barcode): void
    {
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

    /**
     * The initial-stock fields, read from the live form rather than from $data.
     *
     * Two separate reasons they were never arriving. This class used to define
     * mutateFormDataBeforeSave(), which is the Edit page's hook — CreateRecord
     * calls mutateFormDataBeforeCreate(), so it simply never ran. And the three
     * fields are dehydrated(false), because they are not product columns, so
     * they are absent from $data whichever hook reads it.
     *
     * $this->data is the form's own state and holds them either way, which is
     * why this reads there instead. The stock silently never appeared: the item
     * saved, the notification said so, and the quantity someone typed went
     * nowhere.
     *
     * @return array{location: mixed, quantity: mixed, cost: mixed}
     */
    private function initialStockInput(): array
    {
        return [
            'location' => data_get($this->data, 'initial_stock_location_id'),
            'quantity' => data_get($this->data, 'initial_stock_quantity'),
            'cost'     => data_get($this->data, 'initial_stock_cost'),
        ];
    }

    protected function afterCreate(): void
    {
        ['location' => $locationId, 'quantity' => $quantity, 'cost' => $cost] = $this->initialStockInput();

        // Nothing asked for is not a failure — the section is optional and most
        // items are created before anything of them is in the building.
        if (blank($locationId) || blank($quantity) || (float) $quantity <= 0) {
            return;
        }

        try {
            $location = InventoryLocation::findOrFail($locationId);

            app(InventoryService::class)->addStock(
                $this->record,
                $location,
                (float) $quantity,
                'opening',
                'Initial stock added on creation',
                // Falls back to the item's own cost: opening stock at zero
                // would drag the weighted average down on the first receipt.
                (float) (filled($cost) ? $cost : $this->record->unit_cost),
            );

            Notification::make()
                ->title('Initial stock added')
                ->body(number_format((float) $quantity) . " units at {$location->name}.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Item saved, but the initial stock was not added')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\ProductIdentity;
use App\Models\Vendor;
use App\Support\AdminModules;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;

class CreateProductIdentity extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Create Product Identity';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-barcode';
    }

    public string $scannedBarcode = '';
    public ?int $productId = null;
    public ?int $vendorId = null;
    public string $identityType = 'barcode'; // barcode, upc, vendor_sku, manufacturer_sku
    public string $newProductName = '';
    public string $newProductSku = '';
    public string $unitCost = '';
    public bool $createNewProduct = false;
    public bool $addStock = false;
    public string $stockQuantity = '1';

    public function getView(): string
    {
        return 'filament.pages.create-product-identity';
    }

    public function getSubheading(): ?string
    {
        return 'Scan a product barcode, link to an existing product or create a new batch identity with cost.';
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner())
            && AdminModules::isEnabled('inventory');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'create-product-identity';
    }

    public function submit(): void
    {
        if (!$this->scannedBarcode) {
            Notification::make()
                ->title('Scan barcode first')
                ->body('Please scan a barcode using the camera scanner.')
                ->warning()
                ->send();
            return;
        }

        // Create new product if toggled
        if ($this->createNewProduct) {
            if (!$this->newProductName || !$this->newProductSku) {
                Notification::make()
                    ->title('Product info required')
                    ->body('Enter name and SKU for the new product.')
                    ->warning()
                    ->send();
                return;
            }

            $product = InventoryItem::create([
                'name' => $this->newProductName,
                'sku' => $this->newProductSku,
                'is_active' => true,
                'unit_cost' => $this->unitCost ? (float) $this->unitCost : 0,
                'average_cost' => $this->unitCost ? (float) $this->unitCost : 0,
            ]);
            $this->productId = $product->id;
        } elseif (!$this->productId) {
            Notification::make()
                ->title('Select or create product')
                ->body('Please select an existing product or toggle "Create New Product".')
                ->warning()
                ->send();
            return;
        }

        try {
            $product = InventoryItem::findOrFail($this->productId);

            // Create or update product identity
            $identity = ProductIdentity::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'type' => $this->identityType,
                    'value' => $this->scannedBarcode,
                ],
                [
                    'vendor_id' => $this->vendorId,
                    'times_confirmed' => 1,
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                    'auto_confidence' => 1.0,
                ]
            );

            if ($identity->wasRecentlyCreated) {
                Notification::make()
                    ->title('✓ Identity created')
                    ->body("Barcode {$this->scannedBarcode} linked to {$product->name}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('✓ Identity already exists')
                    ->body("Barcode {$this->scannedBarcode} confirmed for {$product->name}")
                    ->info()
                    ->send();
                $identity->recordConfirmation(auth()->id());
            }

            // Reset form
            $this->reset([
                'scannedBarcode',
                'productId',
                'vendorId',
                'newProductName',
                'newProductSku',
                'unitCost',
                'createNewProduct',
                'addStock',
                'stockQuantity',
            ]);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Barcode Scan')
                ->description('Use the camera to scan a barcode')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('scannedBarcode')
                            ->label('Scanned Barcode')
                            ->readOnly()
                            ->suffixAction(
                                \Filament\Actions\Action::make('open_scanner')
                                    ->icon('heroicon-o-qr-code')
                                    ->tooltip('Open camera scanner')
                                    ->action(fn () => $this->dispatch('open-camera-scanner'))
                            )
                            ->placeholder('Scan or click camera icon...')
                            ->columnSpan(1),
                        Select::make('identityType')
                            ->label('Identity Type')
                            ->options([
                                'barcode' => 'Barcode (UPC/EAN)',
                                'upc' => 'UPC',
                                'vendor_sku' => 'Vendor SKU',
                                'manufacturer_sku' => 'Manufacturer SKU',
                            ])
                            ->default('barcode')
                            ->columnSpan(1),
                    ]),
                ]),

            Section::make('Product Selection')
                ->description('Link to existing or create new product')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('createNewProduct')
                        ->label('Create New Product')
                        ->live()
                        ->helperText('Toggle to create a new product for this batch'),
                    Grid::make(2)->schema([
                        Select::make('productId')
                            ->label('Existing Product')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => InventoryItem::where(fn ($q) => $q
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%"))
                                ->orderBy('name')
                                ->limit(30)
                                ->pluck('name', 'id')
                                ->toArray())
                            ->getOptionLabelUsing(fn ($value) => InventoryItem::find($value)?->name ?? $value)
                            ->visible(fn () => !$this->createNewProduct)
                            ->required(fn () => !$this->createNewProduct),
                        TextInput::make('newProductName')
                            ->label('Product Name')
                            ->visible(fn () => $this->createNewProduct)
                            ->required(fn () => $this->createNewProduct)
                            ->placeholder('e.g., 2024 Topps Chrome Box'),
                        TextInput::make('newProductSku')
                            ->label('SKU')
                            ->visible(fn () => $this->createNewProduct)
                            ->required(fn () => $this->createNewProduct)
                            ->default(fn () => 'VB' . date('ymd') . strtoupper(\Illuminate\Support\Str::random(4)))
                            ->placeholder('Auto-generated'),
                    ]),
                ]),

            Section::make('Cost & Vendor')
                ->description('Unit cost and vendor information')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('unitCost')
                            ->label('Unit Cost ($)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->placeholder('0.00'),
                        Select::make('vendorId')
                            ->label('Vendor (Optional)')
                            ->searchable()
                            ->options(fn () => Vendor::where('status', 'active')->orderBy('name')->pluck('name', 'id')->toArray())
                            ->placeholder('Select vendor...'),
                    ]),
                ]),
        ];
    }
}

<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;

class QuickAddInventoryItem extends Page
{
    protected static string $resource = InventoryItemResource::class;

    public ?array $data = [];

    public int $currentStep = 1;

    public ?InventoryItem $createdItem = null;

    public function getView(): string
    {
        return 'filament.pages.quick-add-inventory-item';
    }

    public function mount(): void
    {
        $this->authorize('create', InventoryItem::class);

        // Auto-select streamer's location if they're a streamer
        $user = auth()->user();
        if ($user?->isStreamer() && !$user->isAdmin() && !$user->isOwner()) {
            $streamer = $user->streamer;
            if ($streamer) {
                $location = $streamer->inventoryLocations()->first();
                if ($location) {
                    $this->data['location_id'] = $location->id;
                }
            }
        }
    }

    public function getFormSchema(): array
    {
        return [
                Section::make('Step 1: Item Details')
                    ->description('Quickly add a new inventory item')
                    ->columnSpanFull()
                    ->visible(fn () => $this->currentStep === 1)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Item Name')
                                ->required()
                                ->placeholder('e.g., 2024 Topps Chrome Box')
                                ->autofocus()
                                ->columnSpan(2)
                                ->live(onBlur: true),
                            TextInput::make('sku')
                                ->label('SKU')
                                ->helperText('Auto-generated — edit to customize')
                                ->columnSpan(1)
                                ->default(fn () => 'VB' . date('ymd') . strtoupper(\Illuminate\Support\Str::random(4)))
                                ->live(onBlur: true),
                            TextInput::make('category')
                                ->label('Category')
                                ->placeholder('Sports Cards, Autographs, etc.')
                                ->columnSpan(1),
                            TextInput::make('unit_cost')
                                ->label('Unit Cost ($)')
                                ->numeric()
                                ->default(0)
                                ->step(0.01)
                                ->prefix('$')
                                ->columnSpan(1),
                            Select::make('preferred_vendor_id')
                                ->label('Vendor (optional)')
                                ->options(fn () => \App\Models\Vendor::activeOptions())
                                ->searchable()
                                ->nullable()
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('Step 2: Add Stock')
                    ->description('How much stock to add immediately (optional)')
                    ->columnSpanFull()
                    ->visible(fn () => $this->currentStep === 2)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('location_id')
                                ->label('Stock Location')
                                ->options(fn () => InventoryLocation::activeOptions())
                                ->searchable()
                                ->columnSpan(2),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->placeholder('0')
                                ->columnSpan(1),
                            TextInput::make('cost')
                                ->label('Cost for Stock ($)')
                                ->numeric()
                                ->step(0.01)
                                ->prefix('$')
                                ->placeholder('Leave blank to use unit cost')
                                ->columnSpan(1)
                                ->helperText('Optional override'),
                        ]),
                    ]),

                Section::make('Step 3: Review & Add')
                    ->description('Review your new inventory item')
                    ->columnSpanFull()
                    ->visible(fn () => $this->currentStep === 3)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Item Name')
                                ->disabled()
                                ->columnSpan(2),
                            TextInput::make('sku')
                                ->label('SKU')
                                ->disabled()
                                ->columnSpan(1),
                            TextInput::make('category')
                                ->label('Category')
                                ->disabled()
                                ->columnSpan(1),
                            TextInput::make('unit_cost')
                                ->label('Unit Cost ($)')
                                ->disabled()
                                ->prefix('$')
                                ->columnSpan(1),
                            Select::make('location_id')
                                ->label('Stock Location')
                                ->options(fn () => InventoryLocation::activeOptions())
                                ->disabled()
                                ->columnSpan(1),
                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->disabled()
                                ->columnSpan(1),
                            TextInput::make('cost')
                                ->label('Stock Cost ($)')
                                ->disabled()
                                ->prefix('$')
                                ->columnSpan(1),
                        ]),
                    ]),
        ];
    }

    public function scanBarcode(): void
    {
        Notification::make()
            ->title('Barcode Scanner')
            ->body('Camera barcode scanner coming soon. For now, enter the barcode manually.')
            ->info()
            ->send();
    }

    #[\Livewire\Attributes\On('next-step')]
    public function nextStep(): void
    {
        if ($this->currentStep < 3) {
            $this->currentStep++;
        }
    }

    #[\Livewire\Attributes\On('previous-step')]
    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    #[\Livewire\Attributes\On('submit-wizard')]
    public function submit(): void
    {
        $validatedData = $this->form->getState();

        try {
            // Create the inventory item
            $item = InventoryItem::create([
                'name' => $validatedData['name'],
                'sku' => $validatedData['sku'],
                'barcode' => $validatedData['barcode'] ?? null,
                'category' => $validatedData['category'] ?? null,
                'unit_cost' => (float) ($validatedData['unit_cost'] ?? 0),
                'average_cost' => (float) ($validatedData['unit_cost'] ?? 0),
                'preferred_vendor_id' => $validatedData['preferred_vendor_id'] ?? null,
                'is_active' => true,
            ]);

            $this->createdItem = $item;

            // Add initial stock if provided
            if ($validatedData['location_id'] && $validatedData['quantity']) {
                $location = InventoryLocation::findOrFail($validatedData['location_id']);
                $inventory = app(InventoryService::class);

                $unitCost = (float) ($validatedData['cost'] ?? $item->unit_cost);

                $inventory->addStock(
                    $item,
                    $location,
                    (float) $validatedData['quantity'],
                    'opening',
                    'Initial stock added via quick add wizard',
                    $unitCost,
                );

                $item->load('stock');
            }

            Notification::make()
                ->title('Item added successfully!')
                ->body($validatedData['quantity'] ? 'Item and stock created' : 'Item created (no stock)')
                ->success()
                ->send();

            // Redirect to edit page
            $this->redirect(InventoryItemResource::getUrl('edit', ['record' => $item]));
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error creating item')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Services\BarcodeService;
use App\Support\AdminModules;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class BarcodePrinter extends Page implements HasForms
{
    use HasModuleAccess, InteractsWithForms;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Barcode Label Printer';
    public string $view = 'filament.pages.barcode-printer';

    public Collection $selectedItems;
    public string $labelSize = '4x6';
    public string $itemsPerSheet = '8';

    public function mount(): void
    {
        $this->selectedItems = collect();
        $this->form->fill();
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-barcode';
    }

    public static function getNavigationGroup(): string|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationLabel(): string
    {
        return 'Barcode Printer';
    }

    public function getSubheading(): ?string
    {
        return 'Generate and print barcode labels for inventory items.';
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make()->columns(2)->schema([
                MultiSelect::make('selectedItems')
                    ->relationship('inventoryItems', 'name')
                    ->label('Select Items')
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),

                Select::make('labelSize')
                    ->label('Label Size')
                    ->options([
                        '4x6' => '4" × 6" (Default)',
                        '3x5' => '3" × 5"',
                        '2x3' => '2" × 3"',
                    ])
                    ->default('4x6'),

                Select::make('itemsPerSheet')
                    ->label('Items Per Sheet')
                    ->options([
                        '4' => '4 labels',
                        '6' => '6 labels',
                        '8' => '8 labels',
                        '12' => '12 labels',
                    ])
                    ->default('8'),
            ]),
        ];
    }

    public function generateLabels(): ?string
    {
        $itemIds = $this->form->getState()['selectedItems'] ?? [];

        if (empty($itemIds)) {
            \Filament\Notifications\Notification::make()
                ->title('No items selected')
                ->body('Please select at least one item to generate labels.')
                ->warning()
                ->send();

            return null;
        }

        $items = InventoryItem::whereIn('id', $itemIds)->get();

        if ($items->isEmpty()) {
            return null;
        }

        $labelSize = $this->form->getState()['labelSize'] ?? '4x6';
        $barcodeService = app(BarcodeService::class);

        return $barcodeService->generatePrintSheet($items->toArray(), $labelSize);
    }
}

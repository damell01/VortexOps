<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Services\BarcodeService;
use App\Support\AdminModules;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
// collect() returns a Support collection; typing this as the Eloquent
// one made every page load a TypeError.
use Illuminate\Support\Collection;
use App\Support\NavVisibility;

class BarcodePrinter extends Page implements HasForms
{
    use HasModuleAccess, InteractsWithForms;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Barcode Label Printer';

    // Form state for the multi-select is an array of ids, not a Collection;
    // the Collection type made Livewire's hydration throw on every load.
    public ?array $selectedItems = [];
    public string $labelSize = '4x6';
    public string $itemsPerSheet = '8';

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

    public function mount(): void
    {
        $this->selectedItems = [];
        $this->form->fill();
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-qr-code';
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

    public function getView(): string
    {
        return 'filament.pages.barcode-printer';
    }

    protected function getFormSchema(): array
    {
        return [
            Grid::make()->columns(2)->schema([
                // ->relationship() needs an Eloquent record to resolve against.
                // This is a Page, so there is none, and Select tried to call
                // hasAttribute() on null. Options are supplied directly.
                MultiSelect::make('selectedItems')
                    ->label('Select Items')
                    ->options(fn () => \App\Models\InventoryItem::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
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

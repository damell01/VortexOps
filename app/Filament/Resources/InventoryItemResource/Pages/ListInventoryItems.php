<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Pages\ImportInventorySheet;
use App\Filament\Pages\InventoryScanner;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\PalletResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;
    protected ?array $statsMemo = null;

    #[Url(as: 'stock')]
    public ?string $stockHealth = null;

    #[Url(as: 'view')]
    public string $viewMode = 'catalog';

    #[Url(as: 'q')]
    public string $catalogSearch = '';

    public ?int $barcodeScanTargetId = null;
    public ?string $barcodeScanTargetName = null;
    public ?int $quickStockScanTargetId = null;
    public ?string $quickStockScanTargetName = null;
    public ?int $selectedStockProductId = null;

    public function getView(): string { return 'filament.resources.inventory-item-resource.pages.list-inventory-items'; }
    public function getTitle(): string { return 'All Inventory'; }
    public function getSubheading(): ?string { return 'Browse inventory visually, check stock fast, import a sheet, or receive inventory without opening each item.'; }
    public function getBreadcrumbs(): array { return []; }

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();
        if (! $query || ! $this->stockHealth) return $query;

        return match ($this->stockHealth) {
            'out' => $query->havingRaw('COALESCE(stock_sum_quantity, 0) <= 0'),
            'low' => $query->whereNotNull('reorder_level')->havingRaw('COALESCE(stock_sum_quantity, 0) > 0 AND stock_sum_quantity <= products.reorder_level'),
            'in' => $query->havingRaw('COALESCE(stock_sum_quantity, 0) > 0 AND (products.reorder_level IS NULL OR stock_sum_quantity > products.reorder_level)'),
            default => $query,
        };
    }

    private function catalogQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = InventoryItemResource::getEloquentQuery()
            ->with(['stock.location'])
            ->orderBy('name');

        if (filled($this->catalogSearch)) {
            $term = '%' . trim($this->catalogSearch) . '%';
            $query->where(function ($search) use ($term) {
                $search->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('barcode', 'like', $term)
                    ->orWhere('upc', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }

        if ($this->stockHealth) {
            $query = match ($this->stockHealth) {
                'out' => $query->havingRaw('COALESCE(stock_sum_quantity, 0) <= 0'),
                'low' => $query->whereNotNull('reorder_level')->havingRaw('COALESCE(stock_sum_quantity, 0) > 0 AND stock_sum_quantity <= products.reorder_level'),
                'in' => $query->havingRaw('COALESCE(stock_sum_quantity, 0) > 0 AND (products.reorder_level IS NULL OR stock_sum_quantity > products.reorder_level)'),
                default => $query,
            };
        }

        return $query;
    }

    #[Computed]
    public function catalogItems(): Collection
    {
        return $this->catalogQuery()->get();
    }

    #[Computed]
    public function catalogTotal(): int
    {
        return $this->catalogItems->count();
    }

    public function filterStock(?string $status): void
    {
        $this->stockHealth = $this->stockHealth === $status ? null : $status;
        $this->resetPage();
        unset($this->catalogItems, $this->catalogTotal);
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['catalog', 'table'], true) ? $mode : 'catalog';
    }

    public function updatedCatalogSearch(): void
    {
        unset($this->catalogItems, $this->catalogTotal);
    }

    public function clearCatalogSearch(): void
    {
        $this->catalogSearch = '';
        unset($this->catalogItems, $this->catalogTotal);
    }

    public function getStats(): array
    {
        if ($this->statsMemo !== null) return $this->statsMemo;

        $items = InventoryItemResource::getEloquentQuery()->get(['products.id', 'products.reorder_level']);
        $total = $items->count();
        $out = 0;
        $low = 0;

        foreach ($items as $item) {
            $onHand = (float) ($item->stock_sum_quantity ?? 0);

            if ($onHand <= 0) {
                $out++;
            } elseif ($item->reorder_level !== null && $onHand <= (float) $item->reorder_level) {
                $low++;
            }
        }

        $in = $total - $out - $low;
        $percentage = fn (int $count): string => $total > 0
            ? number_format(($count / $total) * 100, 1) . '%'
            : '0.0%';

        return $this->statsMemo = [
            ['key' => null, 'label' => 'All Items', 'value' => number_format($total), 'percentage' => $total > 0 ? '100%' : '0%', 'icon' => 'heroicon-o-cube', 'tone' => 'purple'],
            ['key' => 'in', 'label' => 'In Stock', 'value' => number_format($in), 'percentage' => $percentage($in), 'icon' => 'heroicon-o-check-circle', 'tone' => 'green'],
            ['key' => 'low', 'label' => 'Low Stock', 'value' => number_format($low), 'percentage' => $percentage($low), 'icon' => 'heroicon-o-exclamation-circle', 'tone' => 'amber'],
            ['key' => 'out', 'label' => 'Out of Stock', 'value' => number_format($out), 'percentage' => $percentage($out), 'icon' => 'heroicon-o-x-circle', 'tone' => 'red'],
        ];
    }

    public function startBarcodeScan(int $productId): void
    {
        $product = Product::find($productId);
        if (! $product) return;

        $this->barcodeScanTargetId = $product->getKey();
        $this->barcodeScanTargetName = $product->name;
        $this->dispatch('open-camera-scanner', title: 'Scan barcode', helper: $product->name);
    }

    public function saveScannedBarcode(string $barcode): void
    {
        $barcode = trim($barcode);
        $product = $this->barcodeScanTargetId ? Product::find($this->barcodeScanTargetId) : null;
        $this->barcodeScanTargetId = null;
        $name = $this->barcodeScanTargetName;
        $this->barcodeScanTargetName = null;

        if ($barcode === '' || ! $product) return;

        $clash = Product::where('barcode', $barcode)->whereKeyNot($product->getKey())->first();
        if ($clash) {
            Notification::make()
                ->title('That barcode is already in use')
                ->body($barcode . ' is on "' . $clash->name . '". Nothing was changed.')
                ->danger()
                ->send();
            return;
        }

        $previous = $product->barcode;
        $product->forceFill(['barcode' => $barcode])->save();

        Notification::make()
            ->title('Barcode saved')
            ->body(filled($previous) ? $name . ' — replaced ' . $previous . ' with ' . $barcode : $name . ' — ' . $barcode)
            ->success()
            ->send();
    }

    public function openQuickAddStock(int $productId): void
    {
        $record = InventoryItem::find($productId);

        if (! $record || ! InventoryItemResource::canEdit($record)) {
            Notification::make()
                ->title('Unable to add stock')
                ->body('This item is unavailable or you do not have permission to edit it.')
                ->danger()
                ->send();
            return;
        }

        $this->selectedStockProductId = $record->getKey();
        $this->quickStockScanTargetId = $record->getKey();
        $this->quickStockScanTargetName = $record->name;
        $this->mountAction('add_stock');
    }

    public function startQuickStockBarcodeScan(): void
    {
        if (! $this->quickStockScanTargetId) return;

        $this->dispatch(
            'open-camera-scanner',
            title: 'Scan item barcode',
            helper: $this->quickStockScanTargetName ?: 'Verify the item before adding stock',
        );
    }

    public function verifyQuickStockBarcode(string $barcode): void
    {
        $barcode = trim($barcode);
        $product = $this->quickStockScanTargetId ? Product::find($this->quickStockScanTargetId) : null;

        if ($barcode === '' || ! $product) return;

        $matches = in_array($barcode, array_filter([
            trim((string) $product->barcode),
            trim((string) $product->upc),
            trim((string) $product->sku),
        ]), true) || $product->identities()->where('value', $barcode)->exists();

        if (! $matches) {
            $other = Product::query()
                ->where('barcode', $barcode)
                ->orWhere('upc', $barcode)
                ->orWhere('sku', $barcode)
                ->first();

            Notification::make()
                ->title('Barcode does not match this item')
                ->body($other ? 'That scan belongs to "' . $other->name . '".' : 'Scanned ' . $barcode . ', but it is not assigned to ' . $product->name . '.')
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title('Item verified')
            ->body($product->name . ' — enter the quantity counted and save.')
            ->success()
            ->send();
    }

    public function addStockAction(): Action
    {
        return Action::make('add_stock')
            ->label('Add Stock')
            ->icon('heroicon-o-plus-circle')
            ->color('success')
            ->modalWidth('lg')
            ->modalHeading(function (): string {
                $record = $this->selectedStockProductId ? InventoryItem::find($this->selectedStockProductId) : null;
                return 'Add Stock' . ($record ? ' — ' . $record->name : '');
            })
            ->mountUsing(function ($form): void {
                $record = $this->selectedStockProductId ? InventoryItem::find($this->selectedStockProductId) : null;
                $this->quickStockScanTargetId = $record?->getKey();
                $this->quickStockScanTargetName = $record?->name;

                $mainLocationId = InventoryLocation::query()
                    ->where('status', 'active')
                    ->where('type', 'main_storage')
                    ->orderBy('id')
                    ->value('id');

                $mainLocationId ??= InventoryLocation::query()
                    ->where('status', 'active')
                    ->where('name', 'Main Warehouse')
                    ->value('id');

                $mainLocationId ??= InventoryLocation::defaultReceivingId();

                $form->fill([
                    'location_id' => $mainLocationId,
                    'quantity' => 1,
                ]);
            })
            ->form([
                Select::make('location_id')
                    ->label('Location')
                    ->options(fn () => InventoryLocation::activeOptions())
                    ->required()
                    ->searchable()
                    ->helperText('Defaults to Main Warehouse.'),
                TextInput::make('quantity')
                    ->label('Quantity Counted / Added')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->default(1)
                    ->autofocus(),
                TextInput::make('barcode_check')
                    ->label('Barcode Check')
                    ->placeholder('Tap the scan icon to verify this item')
                    ->readOnly()
                    ->dehydrated(false)
                    ->suffixAction(
                        Action::make('scan_quick_stock')
                            ->label('Scan')
                            ->icon('heroicon-o-qr-code')
                            ->color('primary')
                            ->action(fn () => $this->startQuickStockBarcodeScan())
                    ),
                Grid::make(2)->schema([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(fn () => Vendor::activeOptions())
                        ->searchable()
                        ->helperText('Optional'),
                    TextInput::make('unit_cost')
                        ->label('Unit Cost ($)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Optional'),
                ]),
                Textarea::make('reason')
                    ->label('Note / Reason')
                    ->rows(2)
                    ->placeholder('Optional note'),
            ])
            ->action(function (array $data): void {
                $record = InventoryItem::findOrFail((int) $this->selectedStockProductId);
                abort_unless(InventoryItemResource::canEdit($record), 403);

                $location = InventoryLocation::findOrFail((int) $data['location_id']);
                $reason = trim((string) ($data['reason'] ?? ''));

                if (! empty($data['vendor_id'])) {
                    $vendor = Vendor::find($data['vendor_id']);
                    $reason = ($reason !== '' ? $reason . ' — ' : '') . 'From ' . ($vendor?->name ?? 'Unknown Vendor');
                }

                app(InventoryService::class)->addStock(
                    $record,
                    $location,
                    (float) $data['quantity'],
                    'opening',
                    $reason !== '' ? $reason : null,
                    isset($data['unit_cost']) && $data['unit_cost'] !== null && $data['unit_cost'] !== ''
                        ? (float) $data['unit_cost']
                        : null,
                );

                $this->selectedStockProductId = null;
                $this->quickStockScanTargetId = null;
                $this->quickStockScanTargetName = null;
                $this->statsMemo = null;
                unset($this->catalogItems, $this->catalogTotal);

                Notification::make()
                    ->title('Stock added successfully')
                    ->body(number_format((float) $data['quantity']) . ' added to ' . $record->name . ' at ' . $location->name . '.')
                    ->success()
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $canExport = fn () => $user?->isAdmin() || $user?->isOwner();
        $canReceive = fn () => $user?->isAdmin() || $user?->isOwner();
        $canCreate = fn () => ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false) || ($user?->isStreamer() ?? false);

        return [
            Action::make('scan')->label('Quick Scan')->icon('heroicon-o-qr-code')->color('primary')->url(fn () => InventoryScanner::getUrl())->visible($canReceive),
            Action::make('import-sheet')->label('Import Sheet')->icon('heroicon-o-arrow-up-tray')->color('info')->url(fn () => ImportInventorySheet::getUrl())->visible($canReceive),
            Action::make('receive')->label('Receive Shipment')->icon('heroicon-o-inbox-arrow-down')->color('success')->url(fn () => PalletResource::getUrl('index'))->visible($canReceive),
            Action::make('quick-add')->label('Quick Add')->icon('heroicon-o-bolt')->color('gray')->url(fn () => InventoryItemResource::getUrl('quick-add'))->visible($canCreate),
            Action::make('add-item')
                ->label('Add Item')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(fn () => InventoryItemResource::getUrl('create'))
                ->visible($canCreate),
            $this->addStockAction()
                ->extraAttributes(['class' => 'hidden']),
            ActionGroup::make([
                Action::make('view-report')->label('View report')->icon('heroicon-o-eye')->url(route('export.inventory-pdf'))->openUrlInNewTab(),
                Action::make('export-pdf')->label('Download PDF')->icon('heroicon-o-document-arrow-down')->url(route('export.inventory-pdf') . '?download=1')->openUrlInNewTab(),
                Action::make('export-excel')->label('Export to Excel')->icon('heroicon-o-table-cells')->url(route('export.inventory.items'))->openUrlInNewTab(),
            ])->label('More')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray')->visible($canExport),
        ];
    }
}

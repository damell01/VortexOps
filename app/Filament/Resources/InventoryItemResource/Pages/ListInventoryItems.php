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
use Filament\Forms\Components\Toggle;
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

    #[Url(as: 'sort')]
    public string $catalogSort = 'name';

    #[Url(as: 'perPage')]
    public int $catalogPerPage = 50;

    public int $catalogPage = 1;
    public int $catalogVisible = 40;

    public ?int $barcodeScanTargetId = null;
    public ?string $barcodeScanTargetName = null;
    public ?int $quickStockScanTargetId = null;
    public ?string $quickStockScanTargetName = null;
    public ?int $selectedStockProductId = null;

    /** Card-view selection for bulk delete. Ids as strings: that is what checkbox wire:model sends. */
    public array $selectedItems = [];

    /**
     * "Select all" means every item matching the current search and stock
     * filter — not just the cards loaded so far. Resolved from the query when
     * the delete runs, so it covers items the page has never drawn.
     */
    public bool $selectAllMatching = false;

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
            ->with(['stock.location']);

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


        $query = match ($this->catalogSort) {
            'value' => $query->orderByDesc('stock_sum_quantity')->orderBy('name'),
            'qty' => $query->orderByDesc('stock_sum_quantity')->orderBy('name'),
            'newest' => $query->latest('products.created_at'),
            default => $query->orderBy('name'),
        };

        return $query;
    }

    #[Computed]
    public function catalogItems(): Collection
    {
        return $this->catalogQuery()->limit($this->catalogVisible)->get();
    }

    #[Computed]
    public function catalogTotal(): int
    {
        return $this->catalogQuery()->count();
    }

    public function filterStock(?string $status): void
    {
        $this->stockHealth = $this->stockHealth === $status ? null : $status;
        $this->clearSelection();
        $this->resetPage();
        unset($this->catalogItems, $this->catalogTotal);
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['catalog', 'table'], true) ? $mode : 'catalog';
    }

    public function updatedCatalogSearch(): void
    {
        $this->clearSelection();
        $this->catalogVisible = 40;
        unset($this->catalogItems, $this->catalogTotal);
    }

    public function updatedCatalogSort(): void
    {
        $this->catalogPage = 1;
        unset($this->catalogItems, $this->catalogTotal);
    }

    public function loadMoreCatalog(): void
    {
        $this->catalogVisible += 40;
        unset($this->catalogItems);
    }

    public function clearCatalogSearch(): void
    {
        $this->catalogSearch = '';
        $this->clearSelection();
        unset($this->catalogItems, $this->catalogTotal);
    }

    public function getStats(): array
    {
        if ($this->statsMemo !== null) return $this->statsMemo;

        // Keep the stock_sum_quantity alias produced by withSum(). Calling
        // count() on this builder can strip the select that defines that alias,
        // which makes HAVING stock_sum_quantity invalid on MySQL. Hydrate only
        // the two tiny fields these cards need and count in memory instead.
        $items = InventoryItemResource::getEloquentQuery()
            ->get(['products.id', 'products.reorder_level']);
        $total = $items->count();
        $out = $items->filter(fn ($item) => (float) ($item->stock_sum_quantity ?? 0) <= 0)->count();
        $low = $items->filter(function ($item): bool {
            $onHand = (float) ($item->stock_sum_quantity ?? 0);
            return $onHand > 0
                && $item->reorder_level !== null
                && $onHand <= (float) $item->reorder_level;
        })->count();
        $in = max(0, $total - $out - $low);
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
        $this->mountAction('add_stock', ['product' => $record->getKey()]);
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

    public function selectAllMatchingItems(): void
    {
        $this->selectAllMatching = true;
        $this->selectedItems = [];
    }

    public function clearSelection(): void
    {
        $this->selectedItems = [];
        $this->selectAllMatching = false;
    }

    /** @return array<int, int> */
    private function bulkTargetIds(): array
    {
        if ($this->selectAllMatching) {
            // Keep the select withSum() adds (HAVING on stock_sum_quantity
            // needs it) but drop the card eager loads: only keys are needed.
            return $this->catalogQuery()->setEagerLoads([])->get()->modelKeys();
        }

        return array_values(array_filter(array_map('intval', $this->selectedItems)));
    }

    public function bulkSelectionCount(): int
    {
        return $this->selectAllMatching ? $this->catalogTotal : count($this->selectedItems);
    }

    /**
     * Card-view bulk delete: mountAction('bulkDeleteItems'). Same rules as the
     * table's "Delete selected" — items still holding stock are skipped unless
     * the write-off toggle is on, and everything goes through the deleter so
     * the inventory log and restore path are identical.
     */
    public function bulkDeleteItemsAction(): Action
    {
        return Action::make('bulkDeleteItems')
            ->label('Delete selected')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn () => InventoryItemResource::canDeleteAny())
            ->requiresConfirmation()
            ->modalHeading(fn () => 'Delete ' . number_format($this->bulkSelectionCount()) . ' selected ' . ($this->bulkSelectionCount() === 1 ? 'item' : 'items') . '?')
            ->modalDescription('Deleted items can be restored from the "Deleted items" filter in the table view.')
            ->modalSubmitActionLabel('Delete')
            ->schema([
                Toggle::make('write_off')->label('Also delete items that still have stock (write that stock off)')
                    ->helperText('Off: items with stock are skipped. On: their stock is set to 0 and recorded in the inventory log.'),
                Textarea::make('reason')->label('Reason (optional)')->rows(2),
            ])
            ->action(function (array $data): void {
                abort_unless(InventoryItemResource::canDeleteAny(), 403);

                $ids = $this->bulkTargetIds();
                $deleter = app(\App\Services\InventoryItemDeleter::class);
                $deleted = 0; $skipped = 0; $units = 0.0;

                foreach (InventoryItem::query()->whereKey($ids)->lazyById(200) as $record) {
                    try {
                        $units += $deleter->delete($record, (bool) ($data['write_off'] ?? false), $data['reason'] ?? null);
                        $deleted++;
                    } catch (\DomainException) {
                        $skipped++;
                    }
                }

                $this->selectedItems = [];
                $this->selectAllMatching = false;
                $this->statsMemo = null;
                unset($this->catalogItems, $this->catalogTotal);

                Notification::make()
                    ->title($deleted . ' item(s) deleted')
                    ->body(trim(($units > 0 ? rtrim(rtrim(number_format($units, 2), '0'), '.') . ' units written off. ' : '') . ($skipped > 0 ? "{$skipped} skipped — they still hold stock." : '')) ?: null)
                    ->{$skipped > 0 ? 'warning' : 'success'}()
                    ->send();
            });
    }

    /** Card "Delete" button: mountAction('deleteItem', ['product' => id]). */
    public function deleteItemAction(): Action
    {
        return InventoryItemResource::deleteItemAction();
    }

    public function addStockAction(): Action
    {
        return Action::make('add_stock')
            ->label('Add Stock')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->modalWidth('md')
            ->modalHeading('Add Stock')
            ->modalDescription(fn (array $arguments) => InventoryItem::find($arguments['product'] ?? null)?->name)
            ->modalSubmitActionLabel('Add Stock')
            ->mountUsing(function ($form, array $arguments): void {
                $record = InventoryItem::find($arguments['product'] ?? null);
                $this->selectedStockProductId = $record?->getKey();
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
                    'unit_cost' => $record && $record->effectiveCost() > 0 ? number_format($record->effectiveCost(), 2, '.', '') : null,
                    'received_at' => now()->toDateString(),
                    'movement_type' => 'opening',
                ]);
            })
            ->form(InventoryItemResource::addStockFields(fn () => $this->startQuickStockBarcodeScan()))
            ->action(function (array $data, array $arguments): void {
                $record = InventoryItem::findOrFail((int) ($arguments['product'] ?? 0));
                abort_unless(InventoryItemResource::canEdit($record), 403);

                $location = InventoryItemResource::bookAddStock($record, $data);

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

    public function itemHistoryAction(): Action
    {
        return \App\Support\InventoryHistory::action();
    }

    protected function getHeaderActions(): array
    {
        // Keep page-level actions in the purple quick-action strip below.
        // The only Filament action retained here is the hidden modal action
        // used by each product card's Add Stock button.
        return [
            $this->addStockAction()->extraAttributes(['class' => 'hidden']),
        ];
    }
}


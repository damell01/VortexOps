<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Support\AdminModules;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use UnitEnum;

class InventoryCatalog extends Page
{
    protected static ?string $title = 'Inventory Catalog';
    protected static ?string $navigationLabel = 'Catalog';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?int $navigationSort = 2;
    protected static bool $shouldRegisterNavigation = false;

    public string $search = '';
    public string $stock = 'all';

    public function getView(): string
    {
        return 'filament.pages.inventory-catalog';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function canAccess(): bool
    {
        return InventoryItemResource::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'A fast, visual way to browse products, stock and pricing without digging through a table.';
    }

    #[Computed]
    public function items(): Collection
    {
        $query = InventoryItemResource::getEloquentQuery()
            ->where('is_active', true)
            ->with(['stock.location'])
            ->orderBy('name');

        if (filled($this->search)) {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('upc', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('category', 'like', "%{$term}%");
            });
        }

        if ($this->stock === 'in') {
            $query->having('stock_sum_quantity', '>', 0);
        } elseif ($this->stock === 'low') {
            $query->whereNotNull('reorder_level')
                ->havingRaw('stock_sum_quantity > 0 AND stock_sum_quantity <= reorder_level');
        } elseif ($this->stock === 'out') {
            $query->having('stock_sum_quantity', '<=', 0);
        }

        return $query->limit(60)->get();
    }

    public function itemUrl(int $id): string
    {
        return InventoryItemResource::getUrl('view', ['record' => $id]);
    }

    public function tableUrl(): string
    {
        return InventoryItemResource::getUrl('index');
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->stock = 'all';
        unset($this->items);
    }

    public function updatedSearch(): void
    {
        unset($this->items);
    }

    public function updatedStock(): void
    {
        unset($this->items);
    }
}

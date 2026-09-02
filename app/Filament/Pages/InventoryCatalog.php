<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class InventoryCatalog extends Page
{
    protected static ?string $title = 'Inventory Catalog';
    protected static ?string $navigationLabel = 'Catalog';
    protected static ?string $slug = 'inventory-catalog';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    public string $search = '';
    public string $stock = 'all';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function canAccess(): bool
    {
        return InventoryItemResource::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'A visual, mobile-friendly way to browse the inventory you can access.';
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-catalog';
    }

    #[Computed]
    public function items(): Collection
    {
        $query = InventoryItemResource::getEloquentQuery()
            ->with('preferredVendor')
            ->where('is_active', true);

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('upc', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('set_name', 'like', $like)
                    ->orWhere('category', 'like', $like);
            });
        }

        if ($this->stock === 'available') {
            $query->whereHas('stock', fn ($q) => $q->where('quantity', '>', 0));
        } elseif ($this->stock === 'out') {
            $query->whereDoesntHave('stock', fn ($q) => $q->where('quantity', '>', 0));
        }

        $items = $query
            ->orderBy('name')
            ->limit($this->stock === 'low' ? 180 : 72)
            ->get();

        if ($this->stock === 'low') {
            $items = $items
                ->filter(fn (InventoryItem $item) => $item->reorder_level !== null
                    && (float) ($item->stock_sum_quantity ?? 0) > 0
                    && (float) ($item->stock_sum_quantity ?? 0) <= (float) $item->reorder_level)
                ->take(72)
                ->values();
        }

        return $items;
    }

    #[Computed]
    public function counts(): array
    {
        $items = InventoryItemResource::getEloquentQuery()
            ->where('is_active', true)
            ->get();

        $available = $items->filter(fn ($item) => (float) ($item->stock_sum_quantity ?? 0) > 0)->count();
        $low = $items->filter(fn ($item) => $item->reorder_level !== null
            && (float) ($item->stock_sum_quantity ?? 0) > 0
            && (float) ($item->stock_sum_quantity ?? 0) <= (float) $item->reorder_level)->count();

        return [
            'all' => $items->count(),
            'available' => $available,
            'low' => $low,
            'out' => max(0, $items->count() - $available),
        ];
    }

    public function itemUrl(InventoryItem $item): string
    {
        return InventoryItemResource::getUrl('view', ['record' => $item]);
    }
}

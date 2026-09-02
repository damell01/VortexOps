<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Support\ChannelContext;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use UnitEnum;

class InventoryCatalog extends Page
{
    protected static ?string $title = 'Inventory Catalog';
    protected static ?string $navigationLabel = 'Catalog';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static string|UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 1;

    #[Url(as: 'q')]
    public string $search = '';

    public function getView(): string
    {
        return 'filament.pages.inventory-catalog';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && (
            $user->isAdmin()
            || $user->isOwner()
            || $user->isStreamer()
            || $user->isFulfillment()
            || $user->isFulfillmentAdmin()
        ));
    }

    public function getSubheading(): ?string
    {
        return 'A quick visual browse of products, stock, SKU and cost without digging through the full inventory table.';
    }

    public function products()
    {
        $user = auth()->user();
        $locationIds = collect();

        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            $locationIds = $user->streamer?->inventoryLocations()->pluck('id') ?? collect();
        }

        $stockScope = function (Builder $query) use ($user, $locationIds): void {
            if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
                $query->whereIn('inventory_location_id', $locationIds);
                return;
            }

            if (ChannelContext::isScoped()) {
                $query->whereHas('location', fn (Builder $location) =>
                    $location->where('whatnot_channel_id', ChannelContext::currentId())
                );
            }
        };

        $query = Product::query()
            ->where('is_active', true)
            ->withSum(['stock as available_units' => $stockScope], 'quantity')
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhere('upc', 'like', $term)
                        ->orWhere('barcode', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('set_name', 'like', $term)
                        ->orWhere('category', 'like', $term);
                });
            });

        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            $query->whereHas('stock', $stockScope);
        }

        return $query
            ->orderByDesc('available_units')
            ->orderBy('name')
            ->limit(72)
            ->get();
    }

    public function stats(): array
    {
        $products = $this->products();

        return [
            'products' => $products->count(),
            'units' => (float) $products->sum(fn (Product $product) => (float) ($product->available_units ?? 0)),
            'low' => $products->filter(fn (Product $product) => $product->reorder_level !== null
                && (float) ($product->available_units ?? 0) <= (float) $product->reorder_level)->count(),
        ];
    }
}

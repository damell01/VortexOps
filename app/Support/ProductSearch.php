<?php

namespace App\Support;

use App\Models\Product;

/**
 * Small, server-side product search used by async pickers.
 *
 * Keep large product catalogs out of HTML <select> elements. Callers send the
 * text the user typed and receive only a short list of matching products.
 */
class ProductSearch
{
    private const COLUMNS = ['id', 'name', 'sku', 'upc', 'barcode', 'unit_cost', 'average_cost', 'is_container'];

    /**
     * Async type-ahead: nothing until two characters, keeps a compact dropdown
     * from flashing the whole catalog at every keystroke.
     *
     * @return array<int, array{id:int,name:string,sku:?string,upc:?string,barcode:?string,unit_cost:?float,is_container:bool}>
     */
    public static function search(string $term, int $limit = 12): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        return static::baseQuery()
            ->where(function ($query) use ($term): void {
                $like = '%' . $term . '%';
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('upc', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$term, $term . '%'])
            ->orderBy('name')
            ->limit(max(1, min($limit, 25)))
            ->get(self::COLUMNS)
            ->map(fn (Product $product) => static::toArray($product))
            ->all();
    }

    /**
     * Catalog picker: an empty term lists active products alphabetically
     * instead of returning nothing, so opening the picker shows something to
     * scroll through immediately rather than an empty box waiting for input.
     *
     * @return array<int, array{id:int,name:string,sku:?string,upc:?string,barcode:?string,unit_cost:?float,is_container:bool}>
     */
    public static function browse(string $term = '', int $limit = 50): array
    {
        $term = trim($term);
        $query = static::baseQuery();

        if ($term !== '') {
            $like = '%' . $term . '%';
            $query->where(function ($query) use ($term, $like): void {
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('upc', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('description', 'like', $like);
            })->orderByRaw('CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$term, $term . '%']);
        }

        return $query->orderBy('name')
            ->limit(max(1, min($limit, 100)))
            ->get(self::COLUMNS)
            ->map(fn (Product $product) => static::toArray($product))
            ->all();
    }

    private static function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Product::query()->where('is_active', true);
    }

    /** @return array{id:int,name:string,sku:?string,upc:?string,barcode:?string,unit_cost:?float,is_container:bool} */
    private static function toArray(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'upc' => $product->upc,
            'barcode' => $product->barcode,
            'unit_cost' => $product->effectiveCost() > 0 ? $product->effectiveCost() : null,
            'is_container' => (bool) $product->is_container,
        ];
    }
}

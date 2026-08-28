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
    /**
     * @return array<int, array{id:int,name:string,sku:?string,upc:?string,barcode:?string,unit_cost:?float,is_container:bool}>
     */
    public static function search(string $term, int $limit = 12): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        return Product::query()
            ->where('is_active', true)
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
            ->get(['id', 'name', 'sku', 'upc', 'barcode', 'unit_cost', 'average_cost', 'is_container'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'upc' => $product->upc,
                'barcode' => $product->barcode,
                'unit_cost' => $product->effectiveCost() > 0 ? $product->effectiveCost() : null,
                'is_container' => (bool) $product->is_container,
            ])
            ->all();
    }
}

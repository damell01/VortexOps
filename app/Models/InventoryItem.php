<?php

namespace App\Models;

/**
 * Backward-compatibility alias.
 * All new code should use App\Models\Product directly.
 */
class InventoryItem extends Product
{
    /**
     * Keep legacy scanner callers type-safe.
     *
     * Product::findByScan() can resolve an alias through ProductIdentity. That
     * relationship hydrates App\Models\Product, which is not an InventoryItem
     * instance and therefore violates the inherited late-static return type
     * when this compatibility class is the caller. Resolve the identity's
     * product id through this class instead so legacy callers always receive
     * an InventoryItem instance.
     */
    public static function findByScan(string $code): ?static
    {
        $item = static::where('barcode', $code)
            ->orWhere('sku', $code)
            ->orWhere('upc', $code)
            ->first();

        if ($item) {
            return $item;
        }

        $productId = ProductIdentity::where('value', $code)
            ->whereIn('type', ['barcode', 'upc', 'vendor_sku', 'manufacturer_sku'])
            ->value('product_id');

        return $productId ? static::find($productId) : null;
    }
}

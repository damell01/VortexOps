<?php

namespace App\Services;

use App\Models\InventoryCase;
use App\Models\InventoryItemContent;
use App\Models\Product;
use RuntimeException;

class PalletScanningService
{
    /**
     * Scan a barcode and determine what it represents:
     * - A case barcode (from inventory_item_contents)
     * - An individual item barcode
     *
     * Returns metadata about the scan result including:
     * - type: 'case' or 'item'
     * - product: the main product
     * - quantity: for cases, the units contained
     * - childItems: for cases, array of what's inside
     */
    public function scanBarcode(string $barcode): array
    {
        // First, check if this is a case barcode (container barcode)
        $caseContent = InventoryItemContent::findByBarcode($barcode);
        if ($caseContent) {
            return [
                'type'      => 'case',
                'barcode'   => $barcode,
                'parent'    => $caseContent->parentItem,
                'child'     => $caseContent->childItem,
                'quantity'  => $caseContent->quantity_per_parent,
                'unit_type' => $caseContent->unit_type ?? 'container',
                'label'     => $caseContent->getDisplayLabel(),
            ];
        }

        // Otherwise, look up as a regular product by barcode/SKU/UPC
        $product = Product::findByScan($barcode);
        if (!$product) {
            throw new RuntimeException("Barcode '{$barcode}' not found in system");
        }

        // Check if this item is used as a child in any container relationships
        $childOf = $product->parentContents()->with('parentItem')->get();

        return [
            'type'    => 'item',
            'barcode' => $barcode,
            'product' => $product,
            'childOf' => $childOf,
            'label'   => $product->name,
        ];
    }

    /**
     * Get all items contained within a case (for display in UI).
     * If a case barcode scanned, show what items are inside.
     */
    public function getContentsOfCase(InventoryItemContent $caseContent): array
    {
        return [
            'parent_name'     => $caseContent->parentItem->name,
            'parent_sku'      => $caseContent->parentItem->sku,
            'child_name'      => $caseContent->childItem->name,
            'child_sku'       => $caseContent->childItem->sku,
            'quantity_inside' => $caseContent->quantity_per_parent,
            'unit_type'       => $caseContent->unit_type,
        ];
    }
}

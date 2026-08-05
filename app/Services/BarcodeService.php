<?php

namespace App\Services;

use App\Models\InventoryItem;

class BarcodeService
{
    /**
     * Generate SVG barcode (Code128) using pure SVG with data
     * For production, use a library like milon/barcode or picqer/php-barcode-generator
     */
    public function generateSvgBarcode(string $code, int $width = 100, int $height = 40): string
    {
        // Use barcode.tec-it.com for quick SVG generation
        // This is a temporary solution; for production, use a PHP library
        return "https://barcode.tec-it.com/barcode.ashx?data={$code}&code=Code128&width={$width}&height={$height}&unit=Px";
    }

    /**
     * Generate PNG barcode URL (alternative to SVG)
     */
    public function generatePngBarcode(string $code, int $width = 100, int $height = 40): string
    {
        return "https://barcode.tec-it.com/barcode.ashx?data={$code}&code=Code128&width={$width}&height={$height}&unit=Px&file=png";
    }

    /**
     * Generate barcode for an inventory item (uses SKU or ID)
     */
    public function barcodeForItem(InventoryItem $item): string
    {
        // Prefer SKU, fall back to ID
        $code = $item->barcode ?? $item->sku ?? "ITEM-{$item->id}";
        return $this->generateSvgBarcode($code);
    }

    /**
     * Generate printable label HTML for a single item
     */
    public function generateItemLabel(InventoryItem $item, array $options = []): string
    {
        $width = $options['width'] ?? 4; // inches
        $height = $options['height'] ?? 2; // inches
        $barcode = $this->barcodeForItem($item);

        return <<<HTML
        <div class="barcode-label" style="width: {$width}in; height: {$height}in; padding: 0.2in; border: 1px solid #ccc; display: flex; flex-direction: column; justify-content: space-between; font-family: Arial, sans-serif; font-size: 10px;">
            <div>
                <div style="font-weight: bold; font-size: 11px;">{{ $item->name }}</div>
                <div style="font-size: 9px; color: #666;">SKU: {{ $item->sku ?? 'N/A' }}</div>
            </div>
            <div style="text-align: center;">
                <img src="{$barcode}" alt="barcode" style="height: 0.8in; width: auto;" />
                <div style="font-size: 9px; letter-spacing: 2px; margin-top: 2px;">{{ $item->barcode ?? $item->sku ?? $item->id }}</div>
            </div>
        </div>
        HTML;
    }

    /**
     * Generate print sheet with multiple labels (supports 4x6, 3x5, etc.)
     */
    public function generatePrintSheet(array $items, string $labelSize = '4x6'): string
    {
        $labels = array_map(fn ($item) => $this->generateItemLabel($item), $items);

        $css = match ($labelSize) {
            '4x6' => 'width: 4in; height: 6in;',
            '3x5' => 'width: 3in; height: 5in;',
            '2x3' => 'width: 2in; height: 3in;',
            default => 'width: 4in; height: 6in;',
        };

        $labelHtml = implode("\n", $labels);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Barcode Labels</title>
            <style>
                * { margin: 0; padding: 0; }
                body { margin: 0; padding: 10px; }
                .label-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
                .barcode-label { $css; page-break-inside: avoid; }
                @media print {
                    body { margin: 0; padding: 0; }
                    .label-container { gap: 0; grid-template-columns: repeat(2, 1fr); }
                }
            </style>
        </head>
        <body>
            <div class="label-container">
                $labelHtml
            </div>
        </body>
        </html>
        HTML;
    }
}

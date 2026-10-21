<?php

namespace App\Exports;

use App\Models\InventoryItem;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/** Fresh catalog on every download, including new and zero-stock items. */
class InventoryPriceListExport extends DefaultValueBinder implements FromQuery, WithMapping, WithHeadings, WithColumnFormatting, WithCustomValueBinder, ShouldAutoSize
{
    public function query()
    {
        return InventoryItem::query()->select(['id', 'sku', 'name', 'category', 'unit_cost', 'average_cost', 'sale_price', 'is_active', 'created_at'])
            ->withSum('stock', 'quantity')->orderBy('name')->orderBy('id');
    }
    public function headings(): array
    {
        return ['SKU', 'Product', 'Category', 'Unit Cost', 'Cost Basis', 'Sale Price', 'On Hand', 'Status', 'Added On'];
    }
    public function map($item): array
    {
        return [$item->sku, $item->name, $item->category,
            $item->unit_cost === null ? null : (float) $item->unit_cost,
            $item->costBasis(), $item->sale_price === null ? null : (float) $item->sale_price,
            (float) ($item->stock_sum_quantity ?? 0), $item->is_active ? 'Active' : 'Inactive', $item->created_at?->format('Y-m-d')];
    }
    public function columnFormats(): array
    {
        return ['D' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE, 'E' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE, 'F' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE, 'G' => '0.##'];
    }
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) { $cell->setValueExplicit($value, DataType::TYPE_STRING); return true; }
        return parent::bindValue($cell, $value);
    }
}

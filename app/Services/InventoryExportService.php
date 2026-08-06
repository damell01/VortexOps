<?php

namespace App\Services;

use App\Models\InventoryItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class InventoryExportService
{
    public function exportToPdf(?Collection $items = null): \Barryvdh\DomPDF\PDF
    {
        if (!$items) {
            $items = InventoryItem::with('stock.location')
                ->where('is_active', true)
                ->orderBy('sku')
                ->get();
        }

        $data = [
            'items' => $items,
            'exportDate' => now()->format('M d, Y'),
            'exportTime' => now()->format('h:i A'),
            'totalItems' => $items->count(),
            'totalValue' => $items->sum(fn ($item) =>
                ($item->stock->sum('quantity') ?? 0) * ($item->average_cost ?? 0)
            ),
        ];

        return Pdf::loadView('exports.inventory-report', $data)
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);
    }

    public function exportToExcel(?Collection $items = null)
    {
        if (!$items) {
            $items = InventoryItem::with('stock.location')
                ->where('is_active', true)
                ->orderBy('sku')
                ->get();
        }

        return new class($items) {
            protected $items;

            public function __construct($items)
            {
                $this->items = $items;
            }

            public function collection()
            {
                return $this->items->map(fn ($item) => [
                    'SKU' => $item->sku,
                    'Name' => $item->name,
                    'Category' => $item->category,
                    'Unit Cost' => '$' . number_format($item->unit_cost, 2),
                    'Avg Cost' => '$' . number_format($item->average_cost, 2),
                    'Total Stock' => $item->stock->sum('quantity') ?? 0,
                    'Total Value' => '$' . number_format(($item->stock->sum('quantity') ?? 0) * ($item->average_cost ?? 0), 2),
                ]);
            }

            public function headings(): array
            {
                return ['SKU', 'Name', 'Category', 'Unit Cost', 'Avg Cost', 'Total Stock', 'Total Value'];
            }
        };
    }
}

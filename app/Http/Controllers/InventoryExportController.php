<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Services\InventoryExportService;
use Maatwebsite\Excel\Facades\Excel;

class InventoryExportController extends Controller
{
    public function inventory(InventoryExportService $service)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $items = InventoryItem::with('stock.location')
            ->where('is_active', true)
            ->orderBy('sku')
            ->get();

        return Excel::download(
            new class($items) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
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
                        'Category' => $item->category ?? '—',
                        'Unit Cost' => $item->unit_cost,
                        'Avg Cost' => $item->average_cost,
                        'Total Stock' => $item->stock->sum('quantity') ?? 0,
                        'Total Value' => (($item->stock->sum('quantity') ?? 0) * ($item->average_cost ?? 0)),
                        'Locations' => $item->stock->pluck('location.name')->unique()->join(', ') ?: '—',
                    ]);
                }

                public function headings(): array
                {
                    return ['SKU', 'Name', 'Category', 'Unit Cost', 'Avg Cost', 'Total Stock', 'Total Value', 'Locations'];
                }
            },
            'inventory-report-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function pdf(InventoryExportService $service)
    {
        $this->authorize('viewAny', InventoryItem::class);

        $pdf = $service->exportToPdf();
        return $pdf->download('inventory-report-' . now()->format('Y-m-d-His') . '.pdf');
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventorySnapshot;
use App\Models\InventoryStock;
use App\Support\AdminModules;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\Response;

class InventoryReport extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Inventory Report';

    public function getView(): string
    {
        return 'filament.pages.inventory-report';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationGroup(): string|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 15;
    }

    public function getSubheading(): ?string
    {
        return 'View total inventory value, costs, margins, and trends over time.';
    }

    public function getData(): array
    {
        $currentSnapshot = InventorySnapshot::latest('snapshot_date')->first();
        $stocks = InventoryStock::with(['item', 'location'])->get();

        // If no recent snapshot, generate one
        if (! $currentSnapshot || $currentSnapshot->snapshot_date->diffInHours(now()) > 1) {
            $currentSnapshot = InventorySnapshot::generateCurrent();
        }

        // Get historical snapshots for trend (last 30 days)
        $trendData = InventorySnapshot::where('snapshot_date', '>=', now()->subDays(30))
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn ($s) => [
                'date' => $s->snapshot_date->format('M d'),
                'value' => $s->total_value,
            ]);

        // Calculate detailed metrics
        $itemDetails = $stocks->map(function ($stock) {
            $cost = $stock->item->average_cost ?? 0;
            return [
                'id' => $stock->item_id,
                'sku' => $stock->item->sku,
                'name' => $stock->item->name,
                'location' => $stock->location->name,
                'quantity' => $stock->quantity,
                'unit_cost' => $cost,
                'total_value' => $stock->quantity * $cost,
                'reorder_level' => $stock->item->reorder_level ?? 0,
                'is_low_stock' => $stock->quantity <= ($stock->item->reorder_level ?? 0),
            ];
        })->sortByDesc('total_value');

        return [
            'currentSnapshot' => $currentSnapshot,
            'trendData' => $trendData,
            'itemDetails' => $itemDetails,
            'stocks' => $stocks,
        ];
    }

    public function exportPdf(): Response
    {
        $data = $this->getData();

        $pdf = Pdf::loadView('filament.pages.inventory-report-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('enable-local-file-access', true);

        return $pdf->download('inventory-report-' . now()->format('Y-m-d-His') . '.pdf');
    }
}

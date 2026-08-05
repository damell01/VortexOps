<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryLocation;
use App\Models\Vendor;
use App\Support\AdminModules;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InventoryAnalytics extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Analytics';

    protected static ?string $navigationLabel = 'Analytics';

    public function getView(): string
    {
        return 'filament.pages.inventory-analytics';
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
        return 3;
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function getSubheading(): ?string
    {
        return 'Key inventory metrics, health status, and quick actions';
    }

    /**
     * Summary statistics for dashboard cards
     */
    public function getSummary(): array
    {
        $stocks = InventoryStock::with(['item', 'location'])->get();

        $totalValue = $stocks->sum(fn ($s) => $s->quantity * ($s->item->average_cost ?? 0));
        $totalUnits = $stocks->sum('quantity');
        $totalItems = InventoryItem::where('is_active', true)->count();
        $totalLocations = InventoryLocation::where('is_active', true)->count();

        return [
            'total_value' => $totalValue,
            'total_units' => $totalUnits,
            'total_items' => $totalItems,
            'total_locations' => $totalLocations,
            'low_stock_count' => InventoryItem::where('is_active', true)
                ->whereNotNull('reorder_level')
                ->whereExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('inventory_stock')
                        ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                        ->groupBy('inventory_stock.inventory_item_id')
                        ->havingRaw('SUM(quantity) <= products.reorder_level');
                })
                ->count(),
        ];
    }

    /**
     * Low stock items that need attention
     */
    public function getLowStockItems(): array
    {
        return InventoryItem::where('is_active', true)
            ->whereNotNull('reorder_level')
            ->with('stock')
            ->get()
            ->filter(function ($item) {
                $total = $item->stock->sum('quantity');
                return $total <= $item->reorder_level;
            })
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'current' => (int) $item->stock->sum('quantity'),
                'reorder' => $item->reorder_level,
                'status' => $item->stock->sum('quantity') == 0 ? 'out_of_stock' : 'low_stock',
            ])
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Top performing vendors
     */
    public function getTopVendors(): array
    {
        return Vendor::where('is_active', true)
            ->withCount('items')
            ->orderByDesc('items_count')
            ->take(6)
            ->get()
            ->map(fn ($vendor) => [
                'name' => $vendor->name,
                'items_count' => $vendor->items_count,
            ])
            ->toArray();
    }

    /**
     * Location utilization
     */
    public function getLocationHealth(): array
    {
        return InventoryLocation::where('is_active', true)
            ->with('stock')
            ->get()
            ->map(fn ($location) => [
                'name' => $location->name,
                'total_units' => (int) $location->stock->sum('quantity'),
                'unique_items' => $location->stock->count(),
                'value' => $location->stock->sum(fn ($s) => $s->quantity * ($s->item->average_cost ?? 0)),
            ])
            ->sortByDesc('value')
            ->values()
            ->toArray();
    }

    /**
     * Fast movers (high velocity items)
     */
    public function getFastMovers(): array
    {
        return InventoryStock::with(['item', 'location'])
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->get()
            ->sortByDesc(fn ($stock) => $stock->quantity * ($stock->item->average_cost ?? 0))
            ->take(5)
            ->map(fn ($stock) => [
                'name' => $stock->item->name,
                'location' => $stock->location->name,
                'quantity' => $stock->quantity,
                'value' => $stock->quantity * ($stock->item->average_cost ?? 0),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Dead stock (no movement, low value)
     */
    public function getDeadStock(): array
    {
        return InventoryItem::where('is_active', true)
            ->with('stock')
            ->get()
            ->filter(function ($item) {
                $total = $item->stock->sum('quantity');
                $value = $total * ($item->average_cost ?? 0);
                return $total > 0 && $value < 100 && $item->average_cost > 0;
            })
            ->sortBy(fn ($item) => $item->stock->sum(fn ($s) => $s->quantity * ($item->average_cost ?? 0)))
            ->take(5)
            ->map(fn ($item) => [
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => (int) $item->stock->sum('quantity'),
                'value' => $item->stock->sum(fn ($s) => $s->quantity * ($item->average_cost ?? 0)),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Export consolidated analytics as PDF
     */
    public function exportAnalyticsPdf(): Response
    {
        $data = [
            'title' => 'Inventory Analytics Summary',
            'date' => now()->format('F j, Y'),
            'time' => now()->format('H:i'),
            'summary' => $this->getSummary(),
            'lowStock' => $this->getLowStockItems(),
            'topVendors' => $this->getTopVendors(),
            'locations' => $this->getLocationHealth(),
            'fastMovers' => $this->getFastMovers(),
            'deadStock' => $this->getDeadStock(),
        ];

        $pdf = Pdf::loadView('filament.pages.inventory-analytics-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('enable-local-file-access', true)
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 5)
            ->setOption('margin-right', 5);

        return $pdf->download('analytics-summary-' . now()->format('Y-m-d-His') . '.pdf');
    }
}

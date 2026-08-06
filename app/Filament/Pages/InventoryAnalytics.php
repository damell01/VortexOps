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
     * Recursively sanitize UTF-8 in arrays/strings to prevent JSON encoding errors
     */
    protected function sanitizeUtf8($data): mixed
    {
        if (is_string($data)) {
            // Remove invalid UTF-8 characters
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }
        if (is_array($data)) {
            return array_map(fn ($item) => $this->sanitizeUtf8($item), $data);
        }
        if (is_object($data)) {
            // Handle Eloquent collections and other objects
            if (method_exists($data, 'toArray')) {
                return $this->sanitizeUtf8($data->toArray());
            }
            if ($data instanceof \Illuminate\Support\Collection) {
                return $this->sanitizeUtf8($data->toArray());
            }
        }
        return $data;
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
        $totalLocations = InventoryLocation::where('status', 'active')->count();

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
        $items = InventoryItem::where('is_active', true)
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

        return $this->sanitizeUtf8($items);
    }

    /**
     * Top performing vendors
     */
    public function getTopVendors(): array
    {
        $vendors = Vendor::where('status', 'active')
            ->withCount('inventoryItems')
            ->orderByDesc('inventory_items_count')
            ->take(6)
            ->get()
            ->map(fn ($vendor) => [
                'name' => $vendor->name,
                'items_count' => $vendor->inventory_items_count,
            ])
            ->toArray();

        return $this->sanitizeUtf8($vendors);
    }

    /**
     * Location utilization
     */
    public function getLocationHealth(): array
    {
        $locations = InventoryLocation::where('status', 'active')
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

        return $this->sanitizeUtf8($locations);
    }

    /**
     * Fast movers (high velocity items)
     */
    public function getFastMovers(): array
    {
        $items = InventoryStock::with(['item', 'location'])
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

        return $this->sanitizeUtf8($items);
    }

    /**
     * Dead stock (no movement, low value)
     */
    public function getDeadStock(): array
    {
        $items = InventoryItem::where('is_active', true)
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

        return $this->sanitizeUtf8($items);
    }

    /**
     * Export consolidated analytics as PDF
     */
    public function exportAnalyticsPdf(): Response
    {
        try {
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

            // Sanitize all data to ensure proper UTF-8 encoding
            $data = $this->sanitizeUtf8($data);

            // Ensure all strings are valid UTF-8
            array_walk_recursive($data, function (&$value) {
                if (is_string($value)) {
                    $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                }
            });

            $pdf = Pdf::loadView('filament.pages.inventory-analytics-pdf', $data)
                ->setPaper('a4', 'landscape')
                ->setOption('enable-local-file-access', true)
                ->setOption('margin-top', 10)
                ->setOption('margin-bottom', 10)
                ->setOption('margin-left', 5)
                ->setOption('margin-right', 5);

            return $pdf->download('analytics-summary-' . now()->format('Y-m-d-His') . '.pdf');
        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title('Export Error')
                ->body('Failed to generate PDF: ' . $e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}

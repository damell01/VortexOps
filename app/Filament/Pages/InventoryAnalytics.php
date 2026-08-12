<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryLocation;
use App\Models\Vendor;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class InventoryAnalytics extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Analytics';

    protected static ?string $navigationLabel = 'Analytics';

    public function getView(): string
    {
        return 'filament.pages.inventory-analytics-mobile';
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
        $user = auth()->user();
        return $user?->isAdmin() || $user?->isOwner() || $user?->isStreamer() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Key inventory metrics, health status, and quick actions';
    }

    /**
     * Get inventory location IDs accessible to current user
     * Streamers only see their own locations, admins see all
     */
    protected function getAccessibleLocationIds()
    {
        $user = auth()->user();

        // Admins and owners see all locations
        if ($user?->isAdmin() || $user?->isOwner()) {
            return InventoryLocation::where('status', 'active')->pluck('id');
        }

        // Streamers only see their own locations
        if ($user?->isStreamer()) {
            $streamer = $user->streamer;
            return $streamer ? $streamer->inventoryLocations()->pluck('id') : collect();
        }

        return collect();
    }

    /**
     * Recursively sanitize UTF-8 in arrays/strings to prevent JSON encoding errors
     */
    protected function sanitizeUtf8($data): mixed
    {
        if (is_string($data)) {
            // Remove invalid UTF-8 characters and encode properly
            if (!mb_check_encoding($data, 'UTF-8')) {
                $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            }
            // Additional cleanup: remove control characters and invalid sequences
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data);
            return $data;
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
        $locationIds = $this->getAccessibleLocationIds();
        $stocks = InventoryStock::with(['item', 'location'])
            ->whereIn('inventory_location_id', $locationIds)
            ->get();

        $totalValue = $stocks->sum(fn ($s) => $s->quantity * ($s->item->average_cost ?? 0));
        $totalUnits = $stocks->sum('quantity');
        $totalItems = InventoryItem::whereHas('stock', fn ($q) =>
            $q->whereIn('inventory_location_id', $locationIds)
        )->where('is_active', true)->count();
        $totalLocations = InventoryLocation::whereIn('id', $locationIds)
            ->where('status', 'active')->count();

        $summary = [
            'total_value' => $totalValue,
            'total_units' => $totalUnits,
            'total_items' => $totalItems,
            'total_locations' => $totalLocations,
            'low_stock_count' => InventoryItem::where('is_active', true)
                ->whereNotNull('reorder_level')
                ->whereExists(function ($q) use ($locationIds) {
                    $q->selectRaw('1')
                        ->from('inventory_stock')
                        ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                        ->whereIn('inventory_location_id', $locationIds)
                        ->groupBy('inventory_stock.inventory_item_id')
                        ->havingRaw('SUM(quantity) <= products.reorder_level');
                })
                ->count(),
        ];

        return $this->sanitizeUtf8($summary);
    }

    /**
     * Low stock items that need attention
     */
    public function getLowStockItems(): array
    {
        $locationIds = $this->getAccessibleLocationIds();
        $items = InventoryItem::where('is_active', true)
            ->whereNotNull('reorder_level')
            ->with(['stock' => fn ($q) => $q->whereIn('inventory_location_id', $locationIds)])
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
        $locationIds = $this->getAccessibleLocationIds();
        $vendors = Vendor::where('status', 'active')
            ->withCount(['inventoryItems' => fn ($q) =>
                $q->whereHas('stock', fn ($sq) =>
                    $sq->whereIn('inventory_location_id', $locationIds)
                )
            ])
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
        $locationIds = $this->getAccessibleLocationIds();
        $locations = InventoryLocation::where('status', 'active')
            ->whereIn('id', $locationIds)
            ->with('stock.item')
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
        $locationIds = $this->getAccessibleLocationIds();
        $items = InventoryStock::with(['item', 'location'])
            ->whereIn('inventory_location_id', $locationIds)
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
        $locationIds = $this->getAccessibleLocationIds();
        $items = InventoryItem::where('is_active', true)
            ->with(['stock' => fn ($q) => $q->whereIn('inventory_location_id', $locationIds)])
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

}

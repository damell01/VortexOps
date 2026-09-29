<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryCostService;
use App\Support\AdminModules;
use Filament\Pages\Page;
use App\Support\NavVisibility;

class InventoryValueDashboard extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Inventory Value Dashboard';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Analytics & Insights';
    }

    public static function getNavigationSort(): ?int
    {
        return 999;
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-value-dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Real-time inventory metrics, value analytics, velocity trends, and stock insights.';
    }

    // ── Computed Properties ────────────────────────────────────────────────────

    private function analyticsSnapshot(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('inventory:value_dashboard:v2', 120, function (): array {
            $stockTotals = \Illuminate\Support\Facades\DB::table('inventory_stock')
                ->selectRaw('inventory_item_id, SUM(quantity) as qty')
                ->groupBy('inventory_item_id');

            $items = \Illuminate\Support\Facades\DB::table('products')
                ->leftJoinSub($stockTotals, 'stock_totals', 'stock_totals.inventory_item_id', '=', 'products.id')
                ->where('products.is_active', true)
                ->select([
                    'products.id', 'products.name', 'products.sku', 'products.category',
                    'products.reorder_level', 'products.average_cost', 'products.unit_cost',
                ])
                ->selectRaw('COALESCE(stock_totals.qty, 0) as qty')
                ->get();

            $rows = $items->map(function ($item): array {
                $qty = (float) $item->qty;
                $cost = (float) (($item->average_cost ?? 0) > 0 ? $item->average_cost : ($item->unit_cost ?? 0));
                return [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'category' => $item->category ?: 'Uncategorized',
                    'qty' => $qty,
                    'reorder' => $item->reorder_level === null ? null : (float) $item->reorder_level,
                    'avg_cost' => $cost,
                    'value' => max(0, $qty) * $cost,
                ];
            });

            $totalValue = (float) $rows->sum('value');
            $totalUnits = (float) $rows->sum('qty');
            $lowStock = $rows->filter(fn ($row) => $row['reorder'] !== null && $row['qty'] <= $row['reorder']);
            $noCostCount = $rows->filter(fn ($row) => $row['qty'] > 0 && $row['avg_cost'] <= 0)->count();

            $byLocation = \Illuminate\Support\Facades\DB::table('inventory_locations')
                ->leftJoin('inventory_stock', 'inventory_stock.inventory_location_id', '=', 'inventory_locations.id')
                ->leftJoin('products', 'products.id', '=', 'inventory_stock.inventory_item_id')
                ->where('inventory_locations.status', 'active')
                ->groupBy('inventory_locations.id', 'inventory_locations.name', 'inventory_locations.type')
                ->orderBy('inventory_locations.name')
                ->select(['inventory_locations.name', 'inventory_locations.type'])
                ->selectRaw('COUNT(DISTINCT inventory_stock.inventory_item_id) as item_count')
                ->selectRaw('COALESCE(SUM(inventory_stock.quantity * COALESCE(NULLIF(products.average_cost, 0), products.unit_cost, 0)), 0) as inventory_value')
                ->get()
                ->map(fn ($loc) => [
                    'name' => $loc->name,
                    'type' => $loc->type,
                    'count' => (int) $loc->item_count,
                    'value' => (float) $loc->inventory_value,
                ])->all();

            $categoryBreakdown = $rows->groupBy('category')->map(fn ($group, $category) => [
                'category' => $category,
                'count' => $group->count(),
                'value' => (float) $group->sum('value'),
                'qty' => (float) $group->sum('qty'),
            ])->sortByDesc('value')->values()->all();

            return [
                'total_value' => $totalValue,
                'value_by_location' => $byLocation,
                'top_items' => $rows->sortByDesc('value')->take(10)->values()->all(),
                'low_stock_high_value' => $lowStock->sortByDesc('value')->values()->all(),
                'category_breakdown' => $categoryBreakdown,
                'health' => [
                    'total_value' => $totalValue,
                    'total_items' => $rows->count(),
                    'total_units' => $totalUnits,
                    'low_stock_count' => $lowStock->count(),
                    'no_cost_count' => $noCostCount,
                    // Vendor cost variance is expensive to derive from receipt history.
                    // Keep the dashboard fast; detailed cost history remains on item/pallet views.
                    'high_variance_count' => 0,
                ],
            ];
        });
    }

    public function getTotalInventoryValueProperty(): float
    {
        return (float) $this->analyticsSnapshot()['total_value'];
    }

    public function getValueByLocationProperty(): array
    {
        return $this->analyticsSnapshot()['value_by_location'];
    }

    public function getTopItemsProperty(): array
    {
        return $this->analyticsSnapshot()['top_items'];
    }

    public function getLowStockHighValueProperty(): array
    {
        return $this->analyticsSnapshot()['low_stock_high_value'];
    }

    public function getCategoryBreakdownProperty(): array
    {
        return $this->analyticsSnapshot()['category_breakdown'];
    }

    public function getInventoryHealthProperty(): array
    {
        return $this->analyticsSnapshot()['health'];
    }

}

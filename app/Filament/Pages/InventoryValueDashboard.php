<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\InventoryCostService;
use App\Support\AdminModules;
use Filament\Pages\Page;

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

    public static function getNavigationLabel(): string
    {
        return 'Value Dashboard';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-value-dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'Real-time inventory value analytics and insights';
    }

    // ── Computed Properties ────────────────────────────────────────────────────

    public function getTotalInventoryValueProperty(): float
    {
        return InventoryItem::query()
            ->get()
            ->sum(fn (InventoryItem $item) => app(InventoryCostService::class)->calculateInventoryValue($item));
    }

    public function getValueByLocationProperty(): array
    {
        $locations = InventoryLocation::with(['stocks' => fn ($q) => $q->with('item')])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $costService = app(InventoryCostService::class);

        return $locations->map(fn ($loc) => [
            'name'  => $loc->name,
            'type'  => $loc->type,
            'count' => $loc->stocks->count(),
            'value' => $loc->stocks->sum(fn ($stock) => $costService->calculateInventoryValue($stock->item)),
        ])->values()->toArray();
    }

    public function getTopItemsProperty(): array
    {
        $costService = app(InventoryCostService::class);

        return InventoryItem::query()
            ->get()
            ->map(function (InventoryItem $item) use ($costService) {
                $value = $costService->calculateInventoryValue($item);
                return [
                    'id'       => $item->id,
                    'name'     => $item->name,
                    'sku'      => $item->sku,
                    'qty'      => (float) $item->totalQuantity(),
                    'avg_cost' => (float) $item->average_cost,
                    'value'    => $value,
                ];
            })
            ->sortByDesc('value')
            ->take(10)
            ->values()
            ->toArray();
    }

    public function getLowStockHighValueProperty(): array
    {
        $costService = app(InventoryCostService::class);

        return InventoryItem::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (InventoryItem $item) => $item->isLowStock())
            ->map(function (InventoryItem $item) use ($costService) {
                $value = $costService->calculateInventoryValue($item);
                return [
                    'id'       => $item->id,
                    'name'     => $item->name,
                    'sku'      => $item->sku,
                    'qty'      => (float) $item->totalQuantity(),
                    'reorder'  => (float) $item->reorder_level,
                    'avg_cost' => (float) $item->average_cost,
                    'value'    => $value,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->toArray();
    }

    public function getCategoryBreakdownProperty(): array
    {
        $costService = app(InventoryCostService::class);

        return InventoryItem::query()
            ->get()
            ->groupBy('category')
            ->map(fn ($items) => [
                'category' => $items->first()?->category ?? 'Uncategorized',
                'count'    => $items->count(),
                'value'    => $items->sum(fn ($item) => $costService->calculateInventoryValue($item)),
                'qty'      => $items->sum(fn ($item) => $item->totalQuantity()),
            ])
            ->sortByDesc('value')
            ->values()
            ->toArray();
    }

    public function getInventoryHealthProperty(): array
    {
        $costService = app(InventoryCostService::class);
        $items = InventoryItem::query()->get();

        $totalValue = $items->sum(fn ($item) => $costService->calculateInventoryValue($item));
        $lowStockCount = $items->filter(fn ($item) => $item->isLowStock())->count();
        $noCostCount = $items->filter(fn ($item) => ($item->average_cost ?? 0) <= 0 && $item->totalQuantity() > 0)->count();
        $highVarianceCount = 0;

        foreach ($items as $item) {
            $breakdown = $costService->getCostBreakdown($item);
            if (!empty($breakdown)) {
                $costs = array_column($breakdown, 'average_cost');
                if (!empty($costs)) {
                    $minCost = min($costs);
                    $maxCost = max($costs);
                    if ($minCost > 0) {
                        $variance = (($maxCost - $minCost) / $minCost) * 100;
                        if ($variance > 50) {
                            $highVarianceCount++;
                        }
                    }
                }
            }
        }

        return [
            'total_value'         => $totalValue,
            'total_items'         => $items->count(),
            'total_units'         => $items->sum(fn ($item) => $item->totalQuantity()),
            'low_stock_count'     => $lowStockCount,
            'no_cost_count'       => $noCostCount,
            'high_variance_count' => $highVarianceCount,
        ];
    }
}

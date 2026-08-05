<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\Product;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Services\InventoryVelocityService;
use App\Support\AdminModules;
use Filament\Pages\Page;

class AdvancedStockAnalytics extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Stock Analytics & Health';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public function getView(): string
    {
        return 'filament.pages.advanced-stock-analytics';
    }

    public function getSubheading(): ?string
    {
        return 'Stock levels, health metrics, reorder insights, and coverage analysis.';
    }

    public function getStockHealthSummaryProperty(): array
    {
        $products = Product::with(['stock', 'lots'])->where('is_active', true)->get();

        $healthy = 0;
        $lowStock = 0;
        $outOfStock = 0;
        $overStock = 0;

        foreach ($products as $product) {
            $qty = (float) $product->totalQuantity();
            $reorderLevel = $product->reorder_level ?? 0;

            if ($qty <= 0) {
                $outOfStock++;
            } elseif ($qty < $reorderLevel) {
                $lowStock++;
            } elseif ($reorderLevel > 0 && $qty > ($reorderLevel * 3)) {
                $overStock++;
            } else {
                $healthy++;
            }
        }

        return [
            'healthy' => $healthy,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'over_stock' => $overStock,
            'total' => $products->count(),
        ];
    }

    public function getLowStockItemsProperty(): array
    {
        return Product::with(['stock', 'lots'])
            ->where('is_active', true)
            ->whereNotNull('reorder_level')
            ->get()
            ->filter(function ($item) {
                $qty = (float) $item->totalQuantity();
                $reorder = $item->reorder_level ?? 0;
                return $qty > 0 && $qty <= $reorder;
            })
            ->map(function ($item) {
                $qty = (float) $item->totalQuantity();
                $reorder = $item->reorder_level ?? 0;
                $suggested = $item->suggestedReorderQuantity() ?? 0;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'current_qty' => $qty,
                    'reorder_level' => $reorder,
                    'suggested_qty' => $suggested,
                    'avg_cost' => (float) $item->average_cost,
                    'alert_level' => $qty / max($reorder, 1),
                ];
            })
            ->sortBy('alert_level')
            ->values()
            ->take(20)
            ->toArray();
    }

    public function getOutOfStockItemsProperty(): array
    {
        return Product::with(['stock'])
            ->where('is_active', true)
            ->get()
            ->filter(function ($item) {
                return (float) $item->totalQuantity() <= 0 && (float) $item->total_units_received > 0;
            })
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'avg_cost' => (float) $item->average_cost,
                    'total_received' => (float) $item->total_units_received,
                ];
            })
            ->sortByDesc('total_received')
            ->values()
            ->take(20)
            ->toArray();
    }

    public function getAbcAnalysisProperty(): array
    {
        $products = Product::with(['stock'])->where('is_active', true)->get();

        $items = $products->map(function ($item) {
            $qty = (float) $item->totalQuantity();
            $cost = (float) $item->average_cost;
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'value' => $qty * $cost,
                'qty' => $qty,
                'cost' => $cost,
            ];
        })
            ->filter(fn ($item) => $item['value'] > 0)
            ->sortByDesc('value')
            ->values();

        $totalValue = $items->sum('value');
        $cumulative = 0;
        $a = [];
        $b = [];
        $c = [];

        foreach ($items as $item) {
            $cumulative += $item['value'];
            $pct = ($cumulative / $totalValue) * 100;

            $item['cumulative_pct'] = $pct;

            if ($pct <= 80) {
                $a[] = $item;
            } elseif ($pct <= 95) {
                $b[] = $item;
            } else {
                $c[] = $item;
            }
        }

        return [
            'class_a' => array_slice($a, 0, 15),
            'class_b' => array_slice($b, 0, 10),
            'class_c' => array_slice($c, 0, 10),
            'a_count' => count($a),
            'b_count' => count($b),
            'c_count' => count($c),
        ];
    }

    public function getStockCoverageProperty(): array
    {
        $velocityService = app(InventoryVelocityService::class);

        $products = Product::with(['stock'])
            ->where('is_active', true)
            ->get()
            ->map(function ($item) use ($velocityService) {
                $qty = (float) $item->totalQuantity();

                $velocity = $velocityService->getItemVelocity($item->id, 30);

                if ($velocity > 0) {
                    $daysOfStock = ceil($qty / $velocity);
                } else {
                    $daysOfStock = $qty > 0 ? 999 : 0;
                }

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $qty,
                    'velocity' => round($velocity, 2),
                    'days_of_stock' => $daysOfStock,
                    'status' => $daysOfStock > 60 ? 'high' : ($daysOfStock > 14 ? 'good' : ($daysOfStock > 0 ? 'low' : 'out')),
                ];
            })
            ->filter(fn ($item) => $item['quantity'] > 0)
            ->sortBy('days_of_stock')
            ->values()
            ->take(20)
            ->toArray();

        return $products;
    }

    public function getLocationHealthProperty(): array
    {
        return InventoryLocation::with(['stock' => fn ($q) => $q->with('item')])
            ->where('status', 'active')
            ->get()
            ->map(function ($location) {
                $stocks = $location->stock;

                $lowCount = 0;
                $outCount = 0;
                $healthyCount = 0;

                foreach ($stocks as $stock) {
                    $qty = (float) $stock->quantity;
                    $reorder = (float) ($stock->item->reorder_level ?? 0);

                    if ($qty <= 0) {
                        $outCount++;
                    } elseif ($qty < $reorder) {
                        $lowCount++;
                    } else {
                        $healthyCount++;
                    }
                }

                $totalValue = $stocks->sum(function ($stock) {
                    return ((float) $stock->quantity) * ((float) $stock->item->average_cost);
                });

                return [
                    'name' => $location->name,
                    'type' => $location->type,
                    'total_items' => $stocks->count(),
                    'healthy' => $healthyCount,
                    'low_stock' => $lowCount,
                    'out_of_stock' => $outCount,
                    'total_value' => $totalValue,
                ];
            })
            ->sortByDesc('total_value')
            ->values()
            ->toArray();
    }
}

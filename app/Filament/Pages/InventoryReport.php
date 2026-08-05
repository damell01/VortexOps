<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventorySnapshot;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\InventoryLocation;
use App\Services\InventoryVelocityService;
use App\Support\AdminModules;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Livewire\Component;
use Symfony\Component\HttpFoundation\Response;
use League\Csv\Writer;
use SplTempFileObject;

class InventoryReport extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Inventory Report & Analytics';

    public string $activeTab = 'overview';

    public function getView(): string
    {
        return 'filament.pages.inventory-report-enhanced';
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

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function getSubheading(): ?string
    {
        return 'Comprehensive inventory value, analytics, stock health, velocity insights, and coverage analysis.';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ── Value Analysis ────────────────────────────────────────────

    public function getData(): array
    {
        $currentSnapshot = InventorySnapshot::latest('snapshot_date')->first();
        $stocks = InventoryStock::with(['item', 'location'])->get();

        if (! $currentSnapshot || $currentSnapshot->snapshot_date->diffInHours(now()) > 1) {
            $currentSnapshot = InventorySnapshot::generateCurrent();
        }

        $trendData = InventorySnapshot::where('snapshot_date', '>=', now()->subDays(30))
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn ($s) => [
                'date' => $s->snapshot_date->format('M d'),
                'value' => $s->total_value,
            ]);

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

    // ── Stock Health ────────────────────────────────────────────

    public function getStockHealthProperty(): array
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

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'current_qty' => $qty,
                    'reorder_level' => $reorder,
                    'suggested_qty' => $item->suggestedReorderQuantity() ?? 0,
                    'avg_cost' => (float) $item->average_cost,
                ];
            })
            ->sortBy('current_qty')
            ->values()
            ->take(20)
            ->toArray();
    }

    // ── Velocity Analysis ────────────────────────────────────────────

    public function getFastMoversProperty(): array
    {
        return app(InventoryVelocityService::class)->getFastMovers(30, 10);
    }

    public function getSlowMoversProperty(): array
    {
        return app(InventoryVelocityService::class)->getSlowMovers(30, 10);
    }

    public function getDeadStockProperty(): array
    {
        return app(InventoryVelocityService::class)->getDeadStock(30, 10);
    }

    // ── ABC Analysis ────────────────────────────────────────────

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

    // ── Coverage & Location Analysis ────────────────────────────────────────────

    public function getStockCoverageProperty(): array
    {
        $velocityService = app(InventoryVelocityService::class);

        return Product::with(['stock'])
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
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $qty,
                    'velocity' => round($velocity, 2),
                    'days_of_stock' => $daysOfStock,
                ];
            })
            ->filter(fn ($item) => $item['quantity'] > 0)
            ->sortBy('days_of_stock')
            ->values()
            ->take(15)
            ->toArray();
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

    public function exportPdf(): Response
    {
        $data = $this->getData();

        $pdf = Pdf::loadView('filament.pages.inventory-report-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('enable-local-file-access', true);

        return $pdf->download('inventory-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    public function exportCsv(): Response
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        $products = Product::with(['stock', 'lots'])->where('is_active', true)->get();

        $csv->insertOne([
            'SKU',
            'Product Name',
            'Category',
            'Total Quantity',
            'Unit Cost',
            'Average Cost',
            'Total Value',
            'Active Lots',
            'Reorder Level',
            'Status',
        ]);

        foreach ($products as $product) {
            $qty = (float) $product->totalQuantity();
            $avgCost = (float) $product->average_cost;
            $totalValue = $qty * $avgCost;
            $activeLots = $product->lots()->where('status', 'active')->count();

            $status = 'Healthy';
            if ($qty <= 0) {
                $status = 'Out of Stock';
            } elseif ($product->reorder_level && $qty < $product->reorder_level) {
                $status = 'Low Stock';
            } elseif ($product->reorder_level && $qty > ($product->reorder_level * 3)) {
                $status = 'Overstock';
            }

            $csv->insertOne([
                $product->sku,
                $product->name,
                $product->category ?? '',
                number_format($qty),
                number_format($product->unit_cost ?? 0, 2),
                number_format($avgCost, 4),
                number_format($totalValue, 2),
                $activeLots,
                $product->reorder_level ?? 0,
                $status,
            ]);
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv->getContent();
        }, 'inventory-report-' . now()->format('Y-m-d-His') . '.csv');
    }

    public function exportBreakdown(): Response
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());

        $csv->insertOne([
            'SKU',
            'Product Name',
            'Location',
            'Quantity',
            'Unit Cost',
            'Total Value',
        ]);

        $itemDetails = $this->getData()['itemDetails'];
        foreach ($itemDetails as $item) {
            $csv->insertOne([
                $item['sku'],
                $item['name'],
                $item['location'],
                number_format($item['quantity']),
                number_format($item['unit_cost'], 2),
                number_format($item['total_value'], 2),
            ]);
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv->getContent();
        }, 'inventory-breakdown-' . now()->format('Y-m-d-His') . '.csv');
    }
}

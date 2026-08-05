<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventorySnapshot;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\InventoryItem;
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
        return false;
    }

    public function getSubheading(): ?string
    {
        return 'Comprehensive inventory value, analytics, stock health, velocity insights, and coverage analysis.';
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Magic getter to support Livewire v3 property access for "Property" suffix methods.
     * Allows blade templates to access $this->stockHealth instead of calling the method.
     */
    public function __get($property): mixed
    {
        $methodName = 'get' . str_replace('_', '', ucwords($property, '_')) . 'Property';
        if (method_exists($this, $methodName)) {
            return $this->$methodName();
        }
        return parent::__get($property);
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
                'sku' => $this->sanitizeUtf8($stock->item->sku),
                'name' => $this->sanitizeUtf8($stock->item->name),
                'location' => $this->sanitizeUtf8($stock->location->name),
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
                    'name' => $this->sanitizeUtf8($item->name),
                    'sku' => $this->sanitizeUtf8($item->sku),
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
                'name' => $this->sanitizeUtf8($item->name),
                'sku' => $this->sanitizeUtf8($item->sku),
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

        return InventoryItem::with(['stock'])
            ->where('is_active', true)
            ->get()
            ->map(function ($item) use ($velocityService) {
                $qty = (float) $item->totalQuantity();
                $velocityData = $velocityService->calculateItemVelocity($item, 30);
                $velocity = $velocityData['daily_velocity'] ?? 0;

                if ($velocity > 0) {
                    $daysOfStock = ceil($qty / $velocity);
                } else {
                    $daysOfStock = $qty > 0 ? 999 : 0;
                }

                return [
                    'name' => $this->sanitizeUtf8($item->name),
                    'sku' => $this->sanitizeUtf8($item->sku),
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

    // ── Breakdown Reports ────────────────────────────────────────────

    public function getCategoryBreakdownProperty(): array
    {
        return Product::with(['stock', 'lots'])
            ->where('is_active', true)
            ->get()
            ->groupBy('category')
            ->map(function ($items, $category) {
                $totalValue = 0;
                $totalQty = 0;
                $itemsList = [];

                foreach ($items as $item) {
                    $qty = (float) $item->totalQuantity();
                    $cost = (float) $item->average_cost;
                    $value = $qty * $cost;

                    $totalQty += $qty;
                    $totalValue += $value;

                    $itemsList[] = [
                        'name' => $this->sanitizeUtf8($item->name),
                        'sku' => $this->sanitizeUtf8($item->sku),
                        'quantity' => $qty,
                        'unit_cost' => $cost,
                        'total_value' => $value,
                    ];
                }

                return [
                    'category' => $this->sanitizeUtf8($category ?: 'Uncategorized'),
                    'item_count' => count($itemsList),
                    'total_quantity' => $totalQty,
                    'total_value' => $totalValue,
                    'avg_unit_cost' => count($itemsList) > 0 ? $totalValue / $totalQty : 0,
                    'items' => array_slice($itemsList, 0, 10),
                ];
            })
            ->sortByDesc('total_value')
            ->values()
            ->toArray();
    }

    public function getVendorBreakdownProperty(): array
    {
        return Product::with(['stock', 'lots' => fn ($q) => $q->orderByDesc('received_at')])
            ->where('is_active', true)
            ->get()
            ->groupBy(function ($item) {
                return $item->lots()->latest()->first()?->vendor?->name ?? 'Unknown Vendor';
            })
            ->map(function ($items, $vendor) {
                $totalValue = 0;
                $totalQty = 0;
                $itemsList = [];

                foreach ($items as $item) {
                    $qty = (float) $item->totalQuantity();
                    $cost = (float) $item->average_cost;
                    $value = $qty * $cost;

                    $totalQty += $qty;
                    $totalValue += $value;

                    $itemsList[] = [
                        'name' => $this->sanitizeUtf8($item->name),
                        'sku' => $this->sanitizeUtf8($item->sku),
                        'quantity' => $qty,
                        'unit_cost' => $cost,
                        'total_value' => $value,
                    ];
                }

                return [
                    'vendor' => $this->sanitizeUtf8($vendor),
                    'item_count' => count($itemsList),
                    'total_quantity' => $totalQty,
                    'total_value' => $totalValue,
                    'items' => array_slice($itemsList, 0, 8),
                ];
            })
            ->sortByDesc('total_value')
            ->values()
            ->toArray();
    }

    public function getAgingInventoryProperty(): array
    {
        $now = now();
        $inventoryItems = Product::with(['stock', 'lots' => fn ($q) => $q->orderBy('received_at')])
            ->where('is_active', true)
            ->get();

        $current = [];
        $thirtyDays = [];
        $sixtyDays = [];
        $ninetyDays = [];

        foreach ($inventoryItems as $item) {
            $oldestLot = $item->lots()->orderBy('received_at')->first();
            $daysOld = $oldestLot ? $now->diffInDays($oldestLot->received_at) : 0;

            $qty = (float) $item->totalQuantity();
            $cost = (float) $item->average_cost;
            $value = $qty * $cost;

            if ($qty <= 0) continue;

            $itemData = [
                'name' => $this->sanitizeUtf8($item->name),
                'sku' => $this->sanitizeUtf8($item->sku),
                'quantity' => $qty,
                'unit_cost' => $cost,
                'total_value' => $value,
                'received_date' => $oldestLot?->received_at?->format('M d, Y'),
                'days_old' => $daysOld,
            ];

            if ($daysOld <= 30) {
                $current[] = $itemData;
            } elseif ($daysOld <= 60) {
                $thirtyDays[] = $itemData;
            } elseif ($daysOld <= 90) {
                $sixtyDays[] = $itemData;
            } else {
                $ninetyDays[] = $itemData;
            }
        }

        $currentSorted = collect($current)->sortBy('days_old')->values()->all();
        $thirtySorted = collect($thirtyDays)->sortByDesc('days_old')->values()->all();
        $sixtySorted = collect($sixtyDays)->sortByDesc('days_old')->values()->all();
        $ninetySorted = collect($ninetyDays)->sortByDesc('days_old')->values()->all();

        return [
            'current' => [
                'label' => '0-30 days',
                'count' => count($current),
                'value' => array_sum(array_column($current, 'total_value')),
                'items' => array_slice($currentSorted, 0, 15),
            ],
            'thirty_days' => [
                'label' => '31-60 days',
                'count' => count($thirtyDays),
                'value' => array_sum(array_column($thirtyDays, 'total_value')),
                'items' => array_slice($thirtySorted, 0, 15),
            ],
            'sixty_days' => [
                'label' => '61-90 days',
                'count' => count($sixtyDays),
                'value' => array_sum(array_column($sixtyDays, 'total_value')),
                'items' => array_slice($sixtySorted, 0, 15),
            ],
            'ninety_days' => [
                'label' => '90+ days',
                'count' => count($ninetyDays),
                'value' => array_sum(array_column($ninetyDays, 'total_value')),
                'items' => array_slice($ninetySorted, 0, 15),
            ],
        ];
    }

    public function getMarginAnalysisProperty(): array
    {
        return Product::with(['stock', 'lots', 'orders'])
            ->where('is_active', true)
            ->get()
            ->filter(fn ($item) => $item->totalQuantity() > 0)
            ->map(function ($item) {
                $qty = (float) $item->totalQuantity();
                $cost = (float) $item->average_cost;
                $totalCost = $qty * $cost;

                // Sum actual revenue from orders
                $revenue = (float) $item->orders()->sum('total_cost');
                $itemMargin = $revenue > 0 ? $revenue - $totalCost : 0;
                $marginPct = $revenue > 0 ? (($itemMargin / $revenue) * 100) : 0;

                return [
                    'name' => $this->sanitizeUtf8($item->name),
                    'sku' => $this->sanitizeUtf8($item->sku),
                    'quantity' => $qty,
                    'cost' => $cost,
                    'revenue' => $revenue,
                    'total_margin' => $itemMargin,
                    'margin_percent' => $marginPct,
                ];
            })
            ->sortByDesc('total_margin')
            ->values()
            ->take(20)
            ->toArray();
    }

    public function getLotAgingProperty(): array
    {
        $now = now();
        $lots = \App\Models\InventoryLot::with(['item', 'receivingSession.vendor'])
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->get();

        $aging = [];
        foreach ($lots as $lot) {
            $daysOld = $now->diffInDays($lot->received_at);
            $ageGroup = match (true) {
                $daysOld <= 30 => 'fresh',
                $daysOld <= 60 => 'aging_30',
                $daysOld <= 90 => 'aging_60',
                default => 'aging_90',
            };

            $aging[] = [
                'id' => $lot->id,
                'item_name' => $lot->item?->name ?? 'Unknown',
                'sku' => $lot->item?->sku ?? '—',
                'vendor' => $lot->receivingSession?->vendor?->name ?? 'Unknown',
                'received_at' => $lot->received_at->format('M d, Y'),
                'days_old' => $daysOld,
                'quantity' => (float) $lot->remaining_quantity,
                'unit_cost' => (float) $lot->unit_cost,
                'total_value' => (float) $lot->remaining_quantity * (float) $lot->unit_cost,
                'age_group' => $ageGroup,
                'age_label' => match ($ageGroup) {
                    'fresh' => '0-30 days',
                    'aging_30' => '31-60 days',
                    'aging_60' => '61-90 days',
                    'aging_90' => '90+ days',
                },
            ];
        }

        $grouped = collect($aging)
            ->groupBy('age_group')
            ->map(function ($group, $key) {
                $items = $group->sortByDesc('total_value')->values()->take(10)->all();
                return [
                    'label' => $group->first()['age_label'],
                    'count' => $group->count(),
                    'total_value' => $group->sum('total_value'),
                    'total_quantity' => $group->sum('quantity'),
                    'items' => $items,
                ];
            })
            ->sortKeys()
            ->toArray();

        return [
            'fresh' => $grouped['fresh'] ?? ['label' => '0-30 days', 'count' => 0, 'total_value' => 0, 'total_quantity' => 0, 'items' => []],
            'aging_30' => $grouped['aging_30'] ?? ['label' => '31-60 days', 'count' => 0, 'total_value' => 0, 'total_quantity' => 0, 'items' => []],
            'aging_60' => $grouped['aging_60'] ?? ['label' => '61-90 days', 'count' => 0, 'total_value' => 0, 'total_quantity' => 0, 'items' => []],
            'aging_90' => $grouped['aging_90'] ?? ['label' => '90+ days', 'count' => 0, 'total_value' => 0, 'total_quantity' => 0, 'items' => []],
        ];
    }
    public function exportPdf(): Response
    {
        $data = $this->sanitizeUtf8($this->getData());

        $pdf = Pdf::loadView('filament.pages.inventory-report-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('enable-local-file-access', true);

        return $pdf->download('inventory-report-' . now()->format('Y-m-d-His') . '.pdf');
    }

    private function sanitizeUtf8($data): mixed
    {
        if (is_string($data)) {
            return iconv('UTF-8', 'UTF-8//IGNORE', $data);
        } elseif (is_array($data)) {
            return array_map([$this, 'sanitizeUtf8'], $data);
        }
        return $data;
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

    public function exportComprehensivePdf(): Response
    {
        $data = $this->sanitizeUtf8([
            'title' => 'Comprehensive Inventory Report',
            'date' => now()->format('F j, Y'),
            'time' => now()->format('H:i'),
            'summary' => $this->getData(),
            'health' => $this->getStockHealthProperty(),
            'fastMovers' => $this->getFastMoversProperty(),
            'slowMovers' => $this->getSlowMoversProperty(),
            'deadStock' => $this->getDeadStockProperty(),
            'abcAnalysis' => $this->getAbcAnalysisProperty(),
            'coverage' => $this->getStockCoverageProperty(),
            'locationHealth' => $this->getLocationHealthProperty(),
            'categories' => $this->getCategoryBreakdownProperty(),
            'vendors' => $this->getVendorBreakdownProperty(),
            'aging' => $this->getAgingInventoryProperty(),
            'margin' => $this->getMarginAnalysisProperty(),
        ]);

        $pdf = Pdf::loadView('filament.pages.inventory-report-pdf-comprehensive', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('enable-local-file-access', true)
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 5)
            ->setOption('margin-right', 5);

        return $pdf->download('inventory-comprehensive-' . now()->format('Y-m-d-His') . '.pdf');
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryLocation;
use App\Models\InventoryValueSnapshot;
use App\Models\Vendor;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryAnalytics extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Analytics';

    protected static ?string $navigationLabel = 'Analytics';

    /** Trailing window, in days, for the value chart and the KPI deltas. */
    protected const TREND_DAYS = 30;

    /** Donut/legend colours, applied to categories in descending-value order. */
    protected const CATEGORY_COLORS = [
        '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6',
        '#f97316', '#06b6d4', '#ec4899', '#64748b',
    ];

    /**
     * Per-request memos. Not Livewire state — these are rebuilt on every
     * render, they just stop each builder re-running the same aggregates.
     */
    protected ?array $summaryMemo = null;

    protected ?array $snapshotMemo = null;

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
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

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
        // The view reads the summary directly and the KPI/trend builders read
        // it again; without a memo that is three passes over every stock row.
        if ($this->summaryMemo !== null) {
            return $this->summaryMemo;
        }

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

        return $this->summaryMemo = $this->sanitizeUtf8($summary);
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

    /* ── Revamped overview: KPI tiles, value trend, category mix ───────── */

    /**
     * The four headline tiles. Each carries a trend delta measured against the
     * oldest snapshot inside the trailing window; the delta is null (and the
     * tile renders without one) when there isn't enough history to compare.
     */
    public function getKpis(): array
    {
        $summary  = $this->getSummary();
        $baseline = $this->trendBaseline();

        return [
            [
                'key'   => 'value',
                'label' => 'Total Inventory Value',
                'value' => '$' . number_format((float) $summary['total_value'], 2),
                'icon'  => 'heroicon-o-banknotes',
                'tone'  => 'green',
                'delta' => $this->percentDelta($summary['total_value'], $baseline['total_value'] ?? null),
            ],
            [
                'key'   => 'units',
                'label' => 'Total Units in Stock',
                'value' => number_format((float) $summary['total_units']),
                'icon'  => 'heroicon-o-cube',
                'tone'  => 'blue',
                'delta' => $this->percentDelta($summary['total_units'], $baseline['total_quantity'] ?? null),
            ],
            [
                'key'   => 'items',
                'label' => 'Active Items',
                'value' => number_format((int) $summary['total_items']),
                'icon'  => 'heroicon-o-squares-2x2',
                'tone'  => 'purple',
                'delta' => $this->percentDelta($summary['total_items'], $baseline['total_items'] ?? null),
            ],
            [
                'key'   => 'low',
                'label' => 'Low Stock Items',
                'value' => number_format((int) $summary['low_stock_count']),
                'icon'  => 'heroicon-o-exclamation-triangle',
                'tone'  => 'amber',
                // More low-stock items is bad news, so the arrow colour flips.
                'delta' => null,
                'sub'   => $summary['low_stock_count'] > 0
                    ? 'Need reordering'
                    : 'All above reorder level',
            ],
        ];
    }

    /**
     * Oldest snapshot in the trailing window, used as the comparison point for
     * the KPI deltas. Null when the snapshot job hasn't run long enough yet.
     */
    protected function trendBaseline(): array
    {
        $snapshot = InventoryValueSnapshot::whereNull('whatnot_channel_id')
            ->where('snapshot_date', '>=', Carbon::today()->subDays(self::TREND_DAYS)->toDateString())
            ->orderBy('snapshot_date')
            ->first();

        return $snapshot ? $snapshot->only(['total_value', 'total_quantity', 'total_items']) : [];
    }

    /** Signed percentage change, or null when there's no usable baseline. */
    protected function percentDelta($current, $baseline): ?array
    {
        if ($baseline === null || (float) $baseline == 0.0) {
            return null;
        }

        $change = (((float) $current - (float) $baseline) / (float) $baseline) * 100;

        return [
            'pct'       => round(abs($change), 1),
            'direction' => $change >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * Inventory value over the trailing window, as ready-to-render SVG
     * geometry so the view stays free of arithmetic.
     *
     * Prefers the daily snapshot table. With fewer than two snapshots (a fresh
     * install, or before the scheduler has run for a couple of days) the series
     * is reconstructed by walking stock movements backwards from today's value,
     * which produces a real curve rather than an empty panel.
     */
    public function getValueTrend(): array
    {
        $series = $this->snapshotSeries();

        if (count($series) < 2) {
            $series = $this->reconstructedSeries();
        }

        if (count($series) < 2) {
            return ['empty' => true, 'source' => 'none'];
        }

        return $this->plotLine($series) + [
            'empty'  => false,
            'source' => count($this->snapshotSeries()) >= 2 ? 'snapshots' : 'movements',
            'change' => $this->percentDelta(end($series)['value'], $series[0]['value']),
            'latest' => '$' . number_format((float) end($series)['value'], 2),
        ];
    }

    /** @return array<int, array{date: Carbon, value: float}> */
    protected function snapshotSeries(): array
    {
        if ($this->snapshotMemo !== null) {
            return $this->snapshotMemo;
        }

        return $this->snapshotMemo = InventoryValueSnapshot::whereNull('whatnot_channel_id')
            ->where('snapshot_date', '>=', Carbon::today()->subDays(self::TREND_DAYS)->toDateString())
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn ($row) => [
                'date'  => Carbon::parse($row->snapshot_date),
                'value' => (float) $row->total_value,
            ])
            ->values()
            ->all();
    }

    /**
     * Walk today's valuation backwards through inventory movements to
     * approximate the value on each preceding day.
     *
     * Receipts (no source location) add value, issues (no destination) remove
     * it; transfers move stock between locations and net to zero, so they're
     * skipped. Movements are costed at their recorded unit_cost, falling back
     * to the item's weighted average.
     */
    protected function reconstructedSeries(): array
    {
        $locationIds = $this->getAccessibleLocationIds();

        if ($locationIds->isEmpty()) {
            return [];
        }

        $today   = Carbon::today();
        $start   = $today->copy()->subDays(self::TREND_DAYS);
        $current = (float) $this->getSummary()['total_value'];

        // Net value change per day across the window.
        $deltas = InventoryMovement::query()
            ->where('inventory_movements.created_at', '>=', $start)
            ->where(function ($q) use ($locationIds) {
                $q->whereIn('to_location_id', $locationIds)
                    ->orWhereIn('from_location_id', $locationIds);
            })
            ->leftJoin('products', 'products.id', '=', 'inventory_movements.inventory_item_id')
            ->selectRaw('DATE(inventory_movements.created_at) as day')
            ->selectRaw(
                'SUM(CASE
                        WHEN inventory_movements.to_location_id IS NOT NULL AND inventory_movements.from_location_id IS NULL
                            THEN inventory_movements.quantity
                        WHEN inventory_movements.from_location_id IS NOT NULL AND inventory_movements.to_location_id IS NULL
                            THEN -inventory_movements.quantity
                        ELSE 0
                     END * COALESCE(inventory_movements.unit_cost, products.average_cost, 0)) as delta'
            )
            ->groupBy('day')
            ->pluck('delta', 'day')
            ->map(fn ($v) => (float) $v)
            ->all();

        if ($deltas === []) {
            return [];
        }

        // Build forward-ordered points by unwinding the deltas from today back.
        $points = [];
        for ($offset = 0; $offset <= self::TREND_DAYS; $offset++) {
            $day = $today->copy()->subDays($offset);

            array_unshift($points, ['date' => $day, 'value' => max(0, round($current, 2))]);

            // Removing this day's movements yields the previous day's closing value.
            $current -= $deltas[$day->toDateString()] ?? 0.0;
        }

        return $points;
    }

    /**
     * Turn a value series into an SVG path, area fill, axis ticks and dots.
     * Coordinates are in the 720x260 user space the view's viewBox declares.
     */
    protected function plotLine(array $series): array
    {
        $left = 56; $right = 704; $top = 24; $bottom = 200;

        $values = array_column($series, 'value');
        $max    = max($values);
        $min    = min($values);

        // Pad the band so a flat line sits mid-panel instead of on an edge.
        if ($max - $min < 0.01) {
            $max += max(1, abs($max) * 0.1);
            $min -= max(1, abs($min) * 0.1);
        }
        $min = max(0, $min - ($max - $min) * 0.1);
        $max = $max + ($max - $min) * 0.1;
        $span = max(0.01, $max - $min);

        $count = count($series);
        $stepX = $count > 1 ? ($right - $left) / ($count - 1) : 0;

        $points = [];
        foreach ($series as $i => $point) {
            $points[] = [
                'x'     => round($left + $i * $stepX, 2),
                'y'     => round($bottom - (($point['value'] - $min) / $span) * ($bottom - $top), 2),
                'date'  => $point['date']->format('M j'),
                'value' => '$' . number_format($point['value'], 2),
            ];
        }

        $line = '';
        foreach ($points as $i => $p) {
            $line .= ($i === 0 ? 'M' : ' L') . $p['x'] . ' ' . $p['y'];
        }

        $area = $line
            . ' L' . end($points)['x'] . ' ' . $bottom
            . ' L' . $points[0]['x'] . ' ' . $bottom . ' Z';

        $yTicks = [];
        for ($i = 0; $i <= 4; $i++) {
            $value = $min + ($span * $i / 4);
            $yTicks[] = [
                'y'     => round($bottom - (($bottom - $top) * $i / 4), 2),
                'label' => $this->compactMoney($value),
            ];
        }

        // Roughly six x labels, always including the first and last point.
        // The end labels are anchored inwards so they don't clip the viewBox.
        $every = max(1, (int) ceil($count / 6));
        $xLabels = [];
        foreach ($points as $i => $p) {
            if ($i % $every !== 0 && $i !== $count - 1) {
                continue;
            }

            $xLabels[] = [
                'x'      => $p['x'],
                'label'  => $p['date'],
                'anchor' => match (true) {
                    $i === 0          => 'start',
                    $i === $count - 1 => 'end',
                    default           => 'middle',
                },
            ];
        }

        // Drop a penultimate label that would collide with the pinned last one.
        if (count($xLabels) >= 2) {
            $last = $xLabels[count($xLabels) - 1];
            $prev = $xLabels[count($xLabels) - 2];

            if ($last['x'] - $prev['x'] < 60) {
                array_splice($xLabels, count($xLabels) - 2, 1);
            }
        }

        return [
            'line'    => $line,
            'area'    => $area,
            'points'  => $points,
            'yTicks'  => $yTicks,
            'xLabels' => $xLabels,
            'baseline' => $bottom,
        ];
    }

    /** $1.2k / $3.4M style axis labels. */
    protected function compactMoney(float $value): string
    {
        $abs = abs($value);

        if ($abs >= 1_000_000) {
            return '$' . rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.') . 'M';
        }
        if ($abs >= 1_000) {
            return '$' . rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.') . 'k';
        }

        return '$' . number_format($value, 0);
    }

    /**
     * Inventory value grouped by product category, with donut geometry.
     * Uncategorised products are grouped under a single bucket rather than
     * dropped, so the segments always add up to the headline total.
     */
    public function getCategoryBreakdown(): array
    {
        $locationIds = $this->getAccessibleLocationIds();

        $rows = InventoryStock::query()
            ->whereIn('inventory_stock.inventory_location_id', $locationIds)
            ->join('products', 'products.id', '=', 'inventory_stock.inventory_item_id')
            ->selectRaw("COALESCE(NULLIF(products.category, ''), 'Uncategorised') as category")
            ->selectRaw('SUM(inventory_stock.quantity) as units')
            ->selectRaw('SUM(inventory_stock.quantity * COALESCE(products.average_cost, 0)) as value')
            ->groupBy('category')
            ->get();

        $total = (float) $rows->sum('value');

        if ($rows->isEmpty() || $total <= 0) {
            return ['empty' => true, 'segments' => [], 'total' => 0.0];
        }

        // Donut geometry: r=54 stroked circle, rotated so the first segment
        // starts at 12 o'clock. Offsets accumulate around the circumference.
        $radius        = 54.0;
        $circumference = 2 * M_PI * $radius;
        $offset        = 0.0;

        $segments = $rows->sortByDesc('value')->values()->map(function ($row, $i) use ($total, $circumference, &$offset) {
            $value  = (float) $row->value;
            $share  = $value / $total;
            $length = $share * $circumference;

            $segment = [
                'name'    => $row->category,
                'value'   => $value,
                // Compact, so the legend meta line never has to ellipsize.
                'value_label' => $this->compactMoney($value),
                'units'   => (int) $row->units,
                'pct'     => round($share * 100, 1),
                'color'   => self::CATEGORY_COLORS[$i % count(self::CATEGORY_COLORS)],
                'dash'    => round($length, 2) . ' ' . round($circumference - $length, 2),
                'offset'  => round(-$offset, 2),
            ];

            $offset += $length;

            return $segment;
        })->all();

        return [
            'empty'         => false,
            'segments'      => $segments,
            'total'         => $total,
            'total_label'   => $this->compactMoney($total),
            'circumference' => round($circumference, 2),
        ];
    }
}

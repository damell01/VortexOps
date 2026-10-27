<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
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
    //
    // Every panel reads the same catalogue, and the view asks for some of them
    // several times. Each used to reload every item and query its stock one
    // item at a time (half a minute on a few thousand products). Now the
    // catalogue loads once per request with its stock, and each panel is a
    // pass over that in memory.

    private ?\Illuminate\Support\Collection $itemsMemo = null;

    private array $memo = [];

    /** Every item with its stock, value and on-hand quantity worked out once. */
    private function items(): \Illuminate\Support\Collection
    {
        return $this->itemsMemo ??= InventoryItem::query()
            ->with('stock:id,inventory_item_id,inventory_location_id,quantity')
            ->get()
            ->each(function (InventoryItem $item): void {
                $item->setAttribute('on_hand_qty', $item->totalQuantity());
                $item->setAttribute('stock_value', $item->totalQuantity() * (float) ($item->costBasis() ?? 0));
            });
    }

    private function memo(string $key, callable $build): mixed
    {
        return $this->memo[$key] ??= $build();
    }

    public function getTotalInventoryValueProperty(): float
    {
        return $this->memo('total', fn () => (float) $this->items()->sum('stock_value'));
    }

    public function getValueByLocationProperty(): array
    {
        return $this->memo('locations', function () {
            $items = $this->items()->keyBy('id');
            $stock = $items->flatMap(fn (InventoryItem $item) => $item->stock)->groupBy('inventory_location_id');

            return InventoryLocation::visibleTo()->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'type'])
                ->map(function ($loc) use ($stock, $items) {
                    $rows = $stock->get($loc->id, collect());

                    return [
                        'name'  => $loc->name,
                        'type'  => $loc->type,
                        'count' => $rows->count(),
                        // Same figure as before: each stocked item's whole value.
                        'value' => $rows->sum(fn ($row) => (float) $items->get($row->inventory_item_id)?->stock_value),
                    ];
                })->values()->toArray();
        });
    }

    private function row(InventoryItem $item, array $extra = []): array
    {
        return [
            'id'       => $item->id,
            'name'     => $item->name,
            'sku'      => $item->sku,
            'qty'      => (float) $item->on_hand_qty,
            ...$extra,
            'avg_cost' => (float) $item->average_cost,
            'value'    => (float) $item->stock_value,
        ];
    }

    public function getTopItemsProperty(): array
    {
        return $this->memo('top', fn () => $this->items()
            ->sortByDesc('stock_value')
            ->take(10)
            ->map(fn (InventoryItem $item) => $this->row($item))
            ->values()
            ->toArray());
    }

    public function getLowStockHighValueProperty(): array
    {
        return $this->memo('low', fn () => $this->items()
            ->filter(fn (InventoryItem $item) => $item->isLowStock())
            ->sortByDesc('stock_value')
            ->map(fn (InventoryItem $item) => $this->row($item, ['reorder' => (float) $item->reorder_level]))
            ->values()
            ->toArray());
    }

    public function getCategoryBreakdownProperty(): array
    {
        return $this->memo('categories', fn () => $this->items()
            ->groupBy('category')
            ->map(fn ($items) => [
                'category' => $items->first()?->category ?? 'Uncategorized',
                'count'    => $items->count(),
                'value'    => (float) $items->sum('stock_value'),
                'qty'      => (float) $items->sum('on_hand_qty'),
            ])
            ->sortByDesc('value')
            ->values()
            ->toArray());
    }

    /**
     * Items whose vendors charged wildly different prices: the cheapest
     * vendor's weighted unit cost against the dearest, over 50% apart. One
     * grouped query over live pallets instead of one cost breakdown per item.
     */
    private function highVarianceCount(): int
    {
        $qty = '(COALESCE(pallet_lines.case_count,0) * COALESCE(pallet_lines.quantity_per_case,0))';

        return \Illuminate\Support\Facades\DB::table('pallet_lines')
            ->join('pallets', 'pallets.id', '=', 'pallet_lines.pallet_id')
            ->whereNull('pallets.deleted_at')
            ->whereNotNull('pallet_lines.inventory_item_id')
            ->groupBy('pallet_lines.inventory_item_id', 'pallets.vendor_id')
            ->selectRaw("pallet_lines.inventory_item_id as item_id, SUM({$qty}) as qty, SUM({$qty} * COALESCE(pallet_lines.unit_cost,0)) as cost")
            ->get()
            ->groupBy('item_id')
            ->filter(function ($vendors) {
                $costs = $vendors->map(fn ($v) => (float) $v->qty > 0 ? (float) $v->cost / (float) $v->qty : 0.0);
                $min = $costs->min();

                return $min > 0 && (($costs->max() - $min) / $min) * 100 > 50;
            })
            ->count();
    }

    public function getInventoryHealthProperty(): array
    {
        return $this->memo('health', function () {
            $items = $this->items();

            return [
                'total_value'         => $this->getTotalInventoryValueProperty(),
                'total_items'         => $items->count(),
                'total_units'         => (float) $items->sum('on_hand_qty'),
                'low_stock_count'     => $items->filter(fn ($item) => $item->isLowStock())->count(),
                'no_cost_count'       => $items->filter(fn ($item) => ($item->average_cost ?? 0) <= 0 && $item->on_hand_qty > 0)->count(),
                'high_variance_count' => $this->highVarianceCount(),
            ];
        });
    }
}

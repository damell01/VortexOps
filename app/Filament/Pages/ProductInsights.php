<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Models\InventoryItem;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Product profitability, sell-through, and dead-stock analytics — all derived
 * from what we already capture (sold order lines + on-hand stock + costs).
 */
class ProductInsights extends Page
{
    use HasAdminNavVisibility;

    protected static string $moduleSlug = 'inventory';

    /** @var 'all'|'best_margin'|'dead_stock'|'never_sold'|'reorder' */
    public string $view = 'best_margin';

    /** Days without a sale before on-hand stock counts as "dead". */
    public const DEAD_DAYS = 90;

    /** A fast seller counts as low on stock once sell-through crosses this line. */
    public const REORDER_SELL_THROUGH = 70.0;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-presentation-chart-line';
    }

    public static function getNavigationLabel(): string
    {
        return 'Product Insights';
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 90;
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && AdminModules::isEnabled('inventory');
    }

    public function getView(): string
    {
        return 'filament.pages.product-insights';
    }

    public function getSubheading(): ?string
    {
        return 'Which products make money, which are stuck. Margin, sell-through, and dead stock — all from your sales and inventory data.';
    }

    public function setView(string $view): void
    {
        $this->view = $view;
    }

    private ?Collection $baseRows = null;

    /**
     * Every active product enriched with sales + stock metrics (memoized).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function allRows(): Collection
    {
        if ($this->baseRows !== null) {
            return $this->baseRows;
        }

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->withSum('stock as on_hand', 'quantity')
            ->withSum('orders as units_sold', 'quantity')
            ->withSum('orders as revenue', 'total_price')
            ->withSum('orders as cogs', 'total_cost')
            ->withMax('orders as last_sold_at', 'show_date')
            ->get();

        return $this->baseRows = $items->map(function (InventoryItem $item): array {
            $onHand    = (float) ($item->on_hand ?? 0);
            $unitsSold = (float) ($item->units_sold ?? 0);
            $revenue   = (float) ($item->revenue ?? 0);
            $cogs      = (float) ($item->cogs ?? 0);
            $unitCost  = (float) ($item->unit_cost ?? 0);
            $margin    = round($revenue - $cogs, 2);
            $lastSold  = $item->last_sold_at ? \Illuminate\Support\Carbon::parse($item->last_sold_at) : null;
            $available = $unitsSold + $onHand;

            return [
                'id'              => $item->id,
                'name'            => $item->name,
                'category'        => $item->category,
                'on_hand'         => $onHand,
                'units_sold'      => $unitsSold,
                'revenue'         => $revenue,
                'cogs'            => $cogs,
                'margin'          => $margin,
                'margin_pct'      => $revenue > 0 ? round($margin / $revenue * 100, 1) : null,
                'capital'         => round($onHand * $unitCost, 2),
                'last_sold_at'    => $lastSold,
                'days_since_sold' => $lastSold ? (int) $lastSold->diffInDays(now()) : null,
                'sell_through'    => $available > 0 ? round($unitsSold / $available * 100, 1) : null,
                'is_dead'         => $onHand > 0 && (! $lastSold || $lastSold->lt(now()->subDays(self::DEAD_DAYS))),
                'never_sold'      => $unitsSold <= 0,
                // A proven seller (sells fast) that's running low on hand — worth restocking.
                'needs_reorder'   => $unitsSold > 0
                    && $available > 0
                    && round($unitsSold / $available * 100, 1) >= self::REORDER_SELL_THROUGH,
            ];
        });
    }

    /**
     * Rows for the currently selected view.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getRowsProperty(): Collection
    {
        $rows = $this->allRows();

        return match ($this->view) {
            'best_margin' => $rows->sortByDesc('margin')->values(),
            'dead_stock'  => $rows->filter(fn ($r) => $r['is_dead'])->sortByDesc('capital')->values(),
            'never_sold'  => $rows->filter(fn ($r) => $r['never_sold'] && $r['on_hand'] > 0)->sortByDesc('capital')->values(),
            'reorder'     => $rows->filter(fn ($r) => $r['needs_reorder'])->sortByDesc('sell_through')->values(),
            default       => $rows->sortBy('name')->values(),
        };
    }

    /** Catalogue-wide KPIs (independent of the active view). @return array<string, float|int> */
    public function getKpisProperty(): array
    {
        $all = $this->allRows();

        return [
            'inventory_value' => round($all->sum('capital'), 2),
            'dead_value'      => round($all->where('is_dead', true)->sum('capital'), 2),
            'active_skus'     => $all->count(),
            'sold_skus'       => $all->where('units_sold', '>', 0)->count(),
            'reorder_skus'    => $all->where('needs_reorder', true)->count(),
        ];
    }
}

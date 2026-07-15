<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /** Cap on rows rendered per view — nobody scrolls thousands; show the top. */
    public const ROW_LIMIT = 200;

    /** Trailing window used to estimate daily sales velocity for reorder suggestions. */
    public const VELOCITY_WINDOW_DAYS = 30;

    /** Assumed order-to-delivery time when a product's vendor has no lead time set. */
    public const DEFAULT_LEAD_TIME_DAYS = 14;

    /** Extra buffer stock, expressed in days of sales, on top of lead-time demand. */
    public const SAFETY_STOCK_DAYS = 7;

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

    /**
     * Per-product aggregate as a query builder subquery — one row per active
     * product with its on-hand, units sold, revenue, COGS, and last-sold date,
     * computed by the database (grouped joins) rather than by hydrating every
     * product into PHP. This is what keeps the page fast at thousands of items.
     */
    private function aggregateQuery(): \Illuminate\Database\Query\Builder
    {
        $stock = DB::table('inventory_stock')
            ->select('inventory_item_id')
            ->selectRaw('SUM(quantity) as on_hand')
            ->groupBy('inventory_item_id');

        $orders = DB::table('whatnot_show_orders')
            ->select('inventory_item_id')
            ->selectRaw('SUM(quantity) as units_sold, SUM(total_price) as revenue, SUM(total_cost) as cogs, MAX(show_date) as last_sold_at')
            ->whereNotNull('inventory_item_id')
            ->groupBy('inventory_item_id');

        // Separate trailing-window sum (not all-time) so we can estimate a daily
        // sales velocity for the reorder-quantity suggestion below.
        $trailingCutoff = now()->subDays(self::VELOCITY_WINDOW_DAYS)->toDateString();
        $trailingOrders = DB::table('whatnot_show_orders')
            ->select('inventory_item_id')
            ->selectRaw('SUM(quantity) as trailing_units_sold')
            ->whereNotNull('inventory_item_id')
            ->where('show_date', '>=', $trailingCutoff)
            ->groupBy('inventory_item_id');

        return DB::table('products as p')
            ->leftJoinSub($stock, 'st', 'st.inventory_item_id', '=', 'p.id')
            ->leftJoinSub($orders, 'o', 'o.inventory_item_id', '=', 'p.id')
            ->leftJoinSub($trailingOrders, 't', 't.inventory_item_id', '=', 'p.id')
            ->leftJoin('vendors as v', 'v.id', '=', 'p.preferred_vendor_id')
            ->whereRaw('p.is_active = 1 and p.deleted_at is null')
            ->selectRaw('
                p.id, p.name, p.category,
                COALESCE(p.unit_cost, 0)  as unit_cost,
                COALESCE(st.on_hand, 0)   as on_hand,
                COALESCE(o.units_sold, 0) as units_sold,
                COALESCE(o.revenue, 0)    as revenue,
                COALESCE(o.cogs, 0)       as cogs,
                o.last_sold_at            as last_sold_at,
                COALESCE(t.trailing_units_sold, 0) as trailing_units_sold,
                v.lead_time_days          as vendor_lead_time_days
            ');
    }

    private function deadCutoff(): string
    {
        return now()->subDays(self::DEAD_DAYS)->toDateString();
    }

    /**
     * Rows for the current view — filtered and sorted in SQL, capped at
     * ROW_LIMIT so we never hydrate or render more than the top slice.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getRowsProperty(): Collection
    {
        $cutoff = $this->deadCutoff();
        $query  = DB::query()->fromSub($this->aggregateQuery(), 'x');

        match ($this->view) {
            'best_margin' => $query->orderByRaw('(revenue - cogs) desc'),
            'dead_stock'  => $query->whereRaw('on_hand > 0 and (last_sold_at is null or last_sold_at < ?)', [$cutoff])
                ->orderByRaw('(on_hand * unit_cost) desc'),
            'never_sold'  => $query->whereRaw('units_sold <= 0 and on_hand > 0')
                ->orderByRaw('(on_hand * unit_cost) desc'),
            // Threshold is a trusted constant, inlined because binding it as a
            // float trips a SQLite PDO type-affinity bug in the comparison.
            'reorder'     => $query->whereRaw('units_sold > 0 and (units_sold + on_hand) > 0 and round(units_sold * 100.0 / (units_sold + on_hand), 1) >= ' . (float) self::REORDER_SELL_THROUGH)
                ->orderByRaw('(units_sold * 1.0 / (units_sold + on_hand)) desc'),
            default       => $query->orderBy('name'),
        };

        return collect($query->limit(self::ROW_LIMIT)->get())
            ->map(fn ($r) => $this->decorate($r));
    }

    /** Turn a raw aggregate row into the display array the blade expects. */
    private function decorate(object $r): array
    {
        $onHand    = (float) $r->on_hand;
        $unitsSold = (float) $r->units_sold;
        $revenue   = (float) $r->revenue;
        $cogs      = (float) $r->cogs;
        $unitCost  = (float) $r->unit_cost;
        $margin    = round($revenue - $cogs, 2);
        $available = $unitsSold + $onHand;
        $lastSold  = $r->last_sold_at ? Carbon::parse($r->last_sold_at) : null;

        // Reorder-quantity forecast: daily velocity from trailing sales, projected
        // across lead time plus a safety buffer, minus what's already on hand.
        // Null whenever there's no recent sales history to estimate a rate from —
        // a forecast built on zero data is worse than no forecast.
        $trailingUnitsSold = (float) ($r->trailing_units_sold ?? 0);
        $velocity     = round($trailingUnitsSold / self::VELOCITY_WINDOW_DAYS, 2);
        $leadTimeDays = (int) ($r->vendor_lead_time_days ?? self::DEFAULT_LEAD_TIME_DAYS);

        $daysOfStockRemaining = $velocity > 0 ? round($onHand / $velocity, 1) : null;
        $reorderPoint         = $velocity > 0 ? $velocity * ($leadTimeDays + self::SAFETY_STOCK_DAYS) : null;
        $suggestedReorderQty  = ($reorderPoint !== null && $onHand < $reorderPoint)
            ? (int) ceil($reorderPoint - $onHand)
            : null;

        return [
            'id'              => $r->id,
            'name'            => $r->name,
            'category'        => $r->category,
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
            'needs_reorder'   => $unitsSold > 0
                && $available > 0
                && round($unitsSold / $available * 100, 1) >= self::REORDER_SELL_THROUGH,
            'velocity'                => $velocity,
            'lead_time_days'          => $leadTimeDays,
            'days_of_stock_remaining' => $daysOfStockRemaining,
            'suggested_reorder_qty'   => $suggestedReorderQty,
        ];
    }

    /**
     * Catalogue-wide KPIs — one aggregate query over the whole (unpaginated)
     * catalogue, so the numbers stay accurate across every product without
     * loading a single one into PHP.
     *
     * @return array<string, float|int>
     */
    public function getKpisProperty(): array
    {
        $cutoff = $this->deadCutoff();

        $k = DB::query()->fromSub($this->aggregateQuery(), 'x')->selectRaw(
            '
            ROUND(COALESCE(SUM(on_hand * unit_cost), 0), 2) as inventory_value,
            ROUND(COALESCE(SUM(CASE WHEN on_hand > 0 AND (last_sold_at IS NULL OR last_sold_at < ?) THEN on_hand * unit_cost ELSE 0 END), 0), 2) as dead_value,
            COUNT(*) as active_skus,
            COALESCE(SUM(CASE WHEN units_sold > 0 THEN 1 ELSE 0 END), 0) as sold_skus,
            COALESCE(SUM(CASE WHEN units_sold > 0 AND (units_sold + on_hand) > 0 AND ROUND(units_sold * 100.0 / (units_sold + on_hand), 1) >= ' . (float) self::REORDER_SELL_THROUGH . ' THEN 1 ELSE 0 END), 0) as reorder_skus
            ',
            [$cutoff]
        )->first();

        return [
            'inventory_value' => (float) ($k->inventory_value ?? 0),
            'dead_value'      => (float) ($k->dead_value ?? 0),
            'active_skus'     => (int) ($k->active_skus ?? 0),
            'sold_skus'       => (int) ($k->sold_skus ?? 0),
            'reorder_skus'    => (int) ($k->reorder_skus ?? 0),
        ];
    }
}

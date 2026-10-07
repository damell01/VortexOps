<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryLocationResource;
use App\Filament\Resources\InventoryMovementResource;
use App\Filament\Resources\InventoryStockResource;
use App\Filament\Resources\PalletResource;
use App\Filament\Resources\VendorResource;
use App\Models\InventoryMovement;
use App\Models\InventorySnapshot;
use App\Models\InventoryStock;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class InventoryOverview extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Inventory Overview';
    protected static ?string $navigationLabel = 'Overview';
    protected static ?string $slug = 'inventory-overview';

    /** Overview (health at a glance), Tools (quick actions) or Reports (stock table). */
    #[Url(as: 'tab')] public string $tab = 'overview';
    #[Url(as: 'days')] public int $trendDays = 30;
    #[Url(as: 'loc')] public string $reportLocation = '';
    #[Url(as: 'cat')] public string $reportCategory = '';
    public string $reportSearch = '';
    public int $reportLimit = 25;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function canAccess(): bool
    {
        return InventoryItemResource::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Track inventory health, recent activity, and key metrics.';
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-overview';
    }

    #[Computed]
    public function inventorySnapshot(): array
    {
        // The overview only needs stock, reorder level and cost. Avoid eager
        // loading vendor models and wide product rows on every dashboard visit.
        $items = InventoryItemResource::getEloquentQuery()
            ->get(['products.id', 'products.reorder_level', 'products.average_cost', 'products.unit_cost']);

        $total = $items->count();
        $out = 0;
        $low = 0;
        $noReorder = 0;
        $value = 0.0;
        $units = 0.0;

        foreach ($items as $item) {
            $onHand = (float) ($item->stock_sum_quantity ?? 0);
            $units += max(0, $onHand);
            $value += max(0, $onHand) * $item->effectiveCost();

            if ($onHand <= 0) {
                $out++;
            } elseif ($item->reorder_level !== null && $onHand <= (float) $item->reorder_level) {
                $low++;
            }

            if ($item->reorder_level === null) {
                $noReorder++;
            }
        }

        $in = max(0, $total - $out - $low);
        $pct = fn (int $count): float => $total > 0 ? round(($count / $total) * 100, 1) : 0.0;

        return [
            'total' => $total,
            'in' => $in,
            'low' => $low,
            'out' => $out,
            'no_reorder' => $noReorder,
            'value' => round($value, 2),
            'units' => $units,
            'percentages' => [
                'total' => $total > 0 ? 100.0 : 0.0,
                'in' => $pct($in),
                'low' => $pct($low),
                'out' => $pct($out),
            ],
        ];
    }

    #[Computed]
    public function recentMovements(): Collection
    {
        $movements = InventoryMovement::query()
            ->inChannelContext()
            ->with(['item', 'fromLocation', 'toLocation', 'createdByUser'])
            ->latest()
            // Pull extra raw rows because one pallet receipt may have hundreds
            // of one-case movement rows. The dashboard shows business actions,
            // while the full movement history remains available for audit.
            ->limit(120)
            ->get();

        return $this->collapsePalletReceiptMovements($movements)->take(8)->values();
    }

    #[Computed]
    public function recentRestocks(): Collection
    {
        $movements = InventoryMovement::query()
            ->inChannelContext()
            ->whereIn('movement_type', ['opening', 'return'])
            ->with(['item', 'toLocation', 'createdByUser'])
            ->latest()
            ->limit(80)
            ->get();

        return $this->collapsePalletReceiptMovements($movements)->take(5)->values();
    }

    /**
     * Inventory additions can be written as one movement per unit/case.
     * Preserve those raw rows for audit, but collapse additions that share the
     * same item, location, reason and minute into one business action. Thus a
     * +30 receipt/addition is shown as +30 rather than thirty +1 rows.
     */
    private function collapsePalletReceiptMovements(Collection $movements): Collection
    {
        $output = collect();
        $groups = [];

        foreach ($movements as $movement) {
            $isBatchAddition = in_array($movement->movement_type, ['opening', 'return'], true)
                && (float) $movement->quantity > 0;

            if (! $isBatchAddition) {
                $output->push($movement);
                continue;
            }

            $minute = $movement->created_at?->format('Y-m-d H:i') ?? 'unknown';
            $key = implode('|', [
                $movement->inventory_item_id,
                $movement->to_location_id,
                $movement->reason,
                $minute,
            ]);

            if (! isset($groups[$key])) {
                $clone = clone $movement;
                $clone->quantity_before = null;
                $clone->quantity_after = null;
                $clone->quantity = abs((float) $movement->quantity);
                $groups[$key] = $clone;
                $output->push($clone);
                continue;
            }

            $groups[$key]->quantity = (float) $groups[$key]->quantity + abs((float) $movement->quantity);
        }

        return $output->sortByDesc(fn (InventoryMovement $movement) => $movement->created_at?->getTimestamp() ?? 0)->values();
    }

    #[Computed]
    public function valueTrend(): Collection
    {
        return InventorySnapshot::query()
            ->where('snapshot_date', '>=', now()->subDays(in_array($this->trendDays, [7, 30, 90], true) ? $this->trendDays : 30))
            ->orderBy('snapshot_date')
            ->get()
            ->groupBy(fn ($snapshot) => $snapshot->snapshot_date->format('Y-m-d'))
            ->map(fn ($rows) => $rows->last())
            ->values()
            ->map(fn ($snapshot) => [
                'date' => $snapshot->snapshot_date->format('M j'),
                'value' => (float) $snapshot->total_value,
            ]);
    }

    #[Computed]
    public function categoryBreakdown(): Collection
    {
        return InventoryItemResource::getEloquentQuery()
            ->get(['products.id', 'products.category'])
            ->groupBy(fn ($item) => filled($item->category) ? $item->category : 'Other')
            ->map(fn ($rows, $name) => [
                'name' => $name,
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values();
    }

    #[Computed]
    public function topValueItems(): Collection
    {
        return InventoryItemResource::getEloquentQuery()
            ->get(['products.id', 'products.name', 'products.sku', 'products.average_cost', 'products.unit_cost'])
            ->map(function ($item) {
                $quantity = max(0, (float) ($item->stock_sum_quantity ?? 0));
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $quantity,
                    'cost' => $item->effectiveCost(),
                    'value' => $quantity * $item->effectiveCost(),
                ];
            })
            ->filter(fn ($item) => $item['value'] > 0)
            ->sortByDesc('value')
            ->take(5)
            ->values();
    }

    /** Percent change in on-hand value across the trend window, or null without two points. */
    public function valueChange(): ?float
    {
        $trend = $this->valueTrend;
        if ($trend->count() < 2 || (float) $trend->first()['value'] <= 0) return null;
        return round(((float) $trend->last()['value'] - (float) $trend->first()['value']) / (float) $trend->first()['value'] * 100, 1);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['overview', 'tools', 'reports'], true)) $this->tab = $tab;
    }

    public function updatedTrendDays(): void
    {
        unset($this->valueTrend);
    }

    // ── Reports tab: a plain stock table with location / category filters ──

    #[Computed]
    public function reportLocations(): Collection
    {
        return \App\Models\InventoryLocation::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id');
    }

    #[Computed]
    public function reportCategories(): Collection
    {
        return \App\Models\InventoryItem::query()->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category');
    }

    public function reportRows(): array
    {
        // select() replaces the columns, including the stock total getEloquentQuery() added — so add it back.
        $q = InventoryItemResource::getEloquentQuery()->select(['products.id', 'products.name', 'products.sku', 'products.image_path', 'products.category'])->withSum('stock', 'quantity');
        if ($this->reportLocation !== '') {
            $loc = (int) $this->reportLocation;
            $q->withSum(['stock as location_qty' => fn ($s) => $s->where('inventory_location_id', $loc)], 'quantity')
                ->whereHas('stock', fn ($s) => $s->where('inventory_location_id', $loc));
        }
        if ($this->reportCategory !== '') $q->where('products.category', $this->reportCategory);
        if (($n = trim($this->reportSearch)) !== '') $q->where(fn ($x) => $x->where('products.name', 'like', "%{$n}%")->orWhere('products.sku', 'like', "%{$n}%")->orWhere('products.barcode', 'like', "%{$n}%"));

        $total = (clone $q)->count();
        $rows = $q->orderBy('products.name')->limit($this->reportLimit)->get()->map(fn ($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'sku' => $i->sku,
            'image' => $i->imageUrl(),
            'qty' => (float) ($this->reportLocation !== '' ? ($i->location_qty ?? 0) : ($i->stock_sum_quantity ?? 0)),
        ]);

        return ['rows' => $rows, 'total' => $total];
    }

    public function updatedReportSearch(): void { $this->reportLimit = 25; }
    public function updatedReportLocation(): void { $this->reportLimit = 25; }
    public function updatedReportCategory(): void { $this->reportLimit = 25; }
    public function moreReportRows(): void { $this->reportLimit += 25; }

    public function exportUrl(): string
    {
        return route('export.inventory-items');
    }

    public function stockStatusUrl(?string $tab = null): string
    {
        return StockStatus::getUrl($tab ? ['tab' => $tab] : []);
    }

    public function healthUrl(): string
    {
        return InventoryHealth::getUrl();
    }

    public function activityUrl(): string
    {
        return InventoryActivity::getUrl();
    }

    public function inventoryUrl(?string $stock = null): string
    {
        $url = InventoryItemResource::getUrl('index');
        return $stock ? $url . '?stock=' . urlencode($stock) : $url;
    }

    public function itemUrl(int $id): string
    {
        return InventoryItemResource::getUrl('view', ['record' => $id]);
    }

    public function scanUrl(): string
    {
        return InventoryScanner::getUrl();
    }

    public function receiveUrl(): string
    {
        return PalletResource::getUrl('index');
    }

    public function quickAddUrl(): string
    {
        return InventoryItemResource::getUrl('quick-add');
    }

    public function addItemUrl(): string
    {
        return InventoryItemResource::getUrl('create');
    }

    public function importUrl(): string
    {
        return ImportInventorySheet::getUrl();
    }

    public function locationsUrl(): string
    {
        return InventoryLocationResource::getUrl('index');
    }

    public function vendorsUrl(): string
    {
        return VendorResource::getUrl('index');
    }

    public function movementsUrl(): string
    {
        return InventoryMovementResource::getUrl('index');
    }

    public function transferUrl(): string
    {
        return StockTransfer::getUrl();
    }

    public function countUrl(): string
    {
        return InventoryCount::getUrl();
    }

    public function reconciliationUrl(): string
    {
        return InventoryReconciliation::getUrl();
    }

    public function analyticsUrl(): string
    {
        return InventoryValueDashboard::getUrl();
    }

    public function reportUrl(): string
    {
        return InventoryReport::getUrl();
    }

    protected function getHeaderActions(): array
    {
        // The Overview / Tools / Reports tabs carry these on phones; desktop keeps them in the header.
        return [
            \Filament\Actions\Action::make('inventory_report')
                ->label('Inventory Report')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->extraAttributes(['class' => 'ivx-desktop-only'])
                ->url(fn () => $this->reportUrl()),
            \Filament\Actions\Action::make('scan_inventory')
                ->label('Scan Inventory')
                ->icon('heroicon-o-qr-code')
                ->extraAttributes(['class' => 'ivx-desktop-only'])
                ->url(fn () => \App\Filament\Pages\InventoryScanner::getUrl()),
        ];
    }

    public function ageUrl(): string
    {
        return InventoryAge::getUrl();
    }

    public function stockLevelsUrl(): string
    {
        return InventoryStockResource::getUrl('index');
    }

    public function productInsightsUrl(): string
    {
        return ProductInsights::getUrl();
    }

    public function guideUrl(): string
    {
        return InventoryGuide::getUrl();
    }
}
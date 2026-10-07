<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\Vendor;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Inventory Health: what is fresh, what needs attention, how the catalogue
 * splits between in stock / low / out, and where the stock sits by category
 * and by vendor. Ages come from Inventory Age, so the two pages agree.
 */
class InventoryHealth extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Inventory Health';
    protected static ?string $slug = 'inventory-health';

    #[Url(as: 'tab')] public string $tab = 'health';

    public static function shouldRegisterNavigation(): bool { return false; }
    public function getView(): string { return 'filament.pages.inventory-health'; }
    public function getSubheading(): ?string { return 'Fresh inventory and items that need attention.'; }

    public static function canAccess(): bool
    {
        return InventoryItemResource::canAccess();
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['health', 'categories', 'vendors'], true)) $this->tab = $tab;
    }

    /** Items with on-hand, state, value, category and vendor. */
    private function items(): Collection
    {
        return InventoryItemResource::getEloquentQuery()
            ->where('products.is_active', true)
            ->get(['products.id', 'products.category', 'products.preferred_vendor_id', 'products.reorder_level', 'products.average_cost', 'products.unit_cost'])
            ->map(function ($i) {
                $qty = max(0, (float) ($i->stock_sum_quantity ?? 0));
                return [
                    'state' => $qty <= 0 ? 'out' : (($i->reorder_level !== null && $qty <= (float) $i->reorder_level) ? 'low' : 'in'),
                    'qty' => $qty,
                    'value' => $qty * $i->effectiveCost(),
                    'category' => filled($i->category) ? $i->category : 'Uncategorised',
                    'vendor_id' => $i->preferred_vendor_id,
                ];
            });
    }

    public function data(): array
    {
        $items = $this->items();
        $total = $items->count();
        $states = ['in' => $items->where('state', 'in')->count(), 'low' => $items->where('state', 'low')->count(), 'out' => $items->where('state', 'out')->count()];

        // Ages, exactly as Inventory Age counts them.
        $age = new InventoryAge();
        $buckets = collect($age->getBuckets()['buckets'])->keyBy('key');
        $fresh = (int) ($buckets['fresh']['item_count'] ?? 0);
        $attention = (int) (($buckets['at_risk']['item_count'] ?? 0) + ($buckets['stale']['item_count'] ?? 0));

        $group = fn (string $key) => $items->groupBy($key)->map(fn ($rows, $name) => [
            'name' => $name,
            'items' => $rows->count(),
            'units' => $rows->sum('qty'),
            'value' => $rows->sum('value'),
            'out' => $rows->where('state', 'out')->count(),
        ])->sortByDesc('items')->values();

        $vendorNames = Vendor::query()->pluck('name', 'id');
        $vendors = $group('vendor_id')->map(fn ($v) => ['name' => $vendorNames[$v['name']] ?? 'No vendor set'] + $v);

        return [
            'total' => $total,
            'states' => $states,
            'fresh' => $fresh,
            'attention' => $attention,
            'categories' => $group('category'),
            'vendors' => $vendors,
        ];
    }

    public function ageUrl(): string
    {
        return InventoryAge::getUrl();
    }

    public function stockUrl(string $tab): string
    {
        return StockStatus::getUrl(['tab' => $tab]);
    }
}

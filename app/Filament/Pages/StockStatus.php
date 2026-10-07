<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * Stock Status: every active SKU as a scannable list — in stock, low, or out —
 * with what is on hand and what it cost. Tapping a row opens the item.
 */
class StockStatus extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Stock Status';
    protected static ?string $slug = 'stock-status';

    #[Url(as: 'tab')] public string $tab = 'all';
    #[Url(as: 'q')] public string $search = '';
    #[Url(as: 'sort')] public string $sort = 'status';
    public int $limit = 30;

    public static function shouldRegisterNavigation(): bool { return false; }
    public function getView(): string { return 'filament.pages.stock-status'; }
    public function getSubheading(): ?string { return 'Current catalog health across active SKUs.'; }

    public static function canAccess(): bool
    {
        return InventoryItemResource::canAccess();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['all', 'in', 'low', 'out'], true) ? $tab : 'all';
        $this->limit = 30;
    }

    public function updatedSearch(): void { $this->limit = 30; }
    public function updatedSort(): void { $this->limit = 30; }
    public function more(): void { $this->limit += 30; }

    /** Every active item with its on-hand and state, computed once per render. */
    private function items()
    {
        return InventoryItemResource::getEloquentQuery()
            ->where('products.is_active', true)
            ->get(['products.id', 'products.name', 'products.sku', 'products.image_path', 'products.reorder_level', 'products.average_cost', 'products.unit_cost'])
            ->map(function ($i) {
                $qty = (float) ($i->stock_sum_quantity ?? 0);
                $state = $qty <= 0 ? 'out' : (($i->reorder_level !== null && $qty <= (float) $i->reorder_level) ? 'low' : 'in');
                return ['id' => $i->id, 'name' => $i->name, 'sku' => $i->sku, 'image' => $i->imageUrl(), 'qty' => $qty, 'state' => $state, 'cost' => $i->effectiveCost()];
            });
    }

    public function data(): array
    {
        $all = $this->items();
        $counts = ['all' => $all->count(), 'in' => $all->where('state', 'in')->count(), 'low' => $all->where('state', 'low')->count(), 'out' => $all->where('state', 'out')->count()];

        $rows = $this->tab === 'all' ? $all : $all->where('state', $this->tab);
        if (($n = mb_strtolower(trim($this->search))) !== '') {
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['name'].' '.$r['sku']), $n));
        }

        $rank = ['out' => 0, 'low' => 1, 'in' => 2];
        $rows = match ($this->sort) {
            'name' => $rows->sortBy(fn ($r) => mb_strtolower($r['name'])),
            'qty' => $rows->sortByDesc('qty'),
            'value' => $rows->sortByDesc(fn ($r) => $r['qty'] * $r['cost']),
            default => $rows->sortBy(fn ($r) => sprintf('%d|%s', $rank[$r['state']], mb_strtolower($r['name']))),
        };

        return ['counts' => $counts, 'rows' => $rows->take($this->limit)->values(), 'matching' => $rows->count()];
    }

    public function itemUrl(int $id): string
    {
        return InventoryItemResource::getUrl('view', ['record' => $id]);
    }
}

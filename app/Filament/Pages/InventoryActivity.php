<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Recent Inventory Activity: a timeline of stock received, adjusted,
 * transferred, sold, and items created. A pallet received case by case is one
 * line carrying the total, as on the overview.
 */
class InventoryActivity extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Recent Inventory Activity';
    protected static ?string $slug = 'inventory-activity';

    #[Url(as: 'tab')] public string $tab = 'all';
    #[Url(as: 'q')] public string $search = '';
    public int $limit = 40;

    public const TABS = ['all' => 'All', 'received' => 'Received', 'adjusted' => 'Adjusted', 'transferred' => 'Transferred'];

    public static function shouldRegisterNavigation(): bool { return false; }
    public function getView(): string { return 'filament.pages.inventory-activity'; }
    public function getSubheading(): ?string { return 'Latest inventory additions, adjustments and transfers.'; }

    public static function canAccess(): bool
    {
        return InventoryItemResource::canAccess();
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'all';
        $this->limit = 40;
    }

    public function updatedSearch(): void { $this->limit = 40; }
    public function more(): void { $this->limit += 40; }

    /** What kind of event a movement was, from its shape and type. */
    public static function kind(InventoryMovement $m): string
    {
        return match (true) {
            $m->movement_type === 'transfer' || ($m->from_location_id && $m->to_location_id && $m->movement_type !== 'damaged') => 'transferred',
            $m->movement_type === 'damaged' => 'damaged',
            $m->movement_type === 'sale_deduction' => 'sold',
            in_array($m->movement_type, ['adjustment', 'count'], true) => 'adjusted',
            (bool) $m->to_location_id => 'received',
            default => 'adjusted',
        };
    }

    public function entries(): Collection
    {
        $q = InventoryMovement::query()->inChannelContext()
            ->with(['item' => fn ($r) => $r->withTrashed(), 'fromLocation:id,name', 'toLocation:id,name'])
            ->latest('id')
            ->limit($this->limit * 4);

        match ($this->tab) {
            'received' => $q->whereNull('from_location_id')->whereNotNull('to_location_id')->whereNotIn('movement_type', ['adjustment', 'count']),
            'adjusted' => $q->whereIn('movement_type', ['adjustment', 'count', 'damaged']),
            'transferred' => $q->where(fn ($x) => $x->where('movement_type', 'transfer')->orWhere(fn ($y) => $y->whereNotNull('from_location_id')->whereNotNull('to_location_id')->where('movement_type', '!=', 'damaged'))),
            default => null,
        };
        if (($n = trim($this->search)) !== '') {
            $q->whereHas('item', fn ($i) => $i->withTrashed()->where(fn ($x) => $x->where('name', 'like', "%{$n}%")->orWhere('sku', 'like', "%{$n}%")));
        }

        // One receipt written as many one-case rows reads as one line.
        $grouped = [];
        foreach ($q->get() as $m) {
            $kind = static::kind($m);
            $signed = $m->signedChange();
            $key = $kind === 'received'
                ? implode('|', [$m->inventory_item_id, $m->to_location_id, $m->reason, $m->created_at?->format('Y-m-d H:i')])
                : 'm'.$m->id;
            if (isset($grouped[$key])) {
                $grouped[$key]['change'] += $signed;
                continue;
            }
            $grouped[$key] = [
                'kind' => $kind,
                'item' => $m->item?->name ?? 'Deleted item',
                'item_id' => $m->item && ! $m->item->trashed() ? $m->item->id : null,
                'change' => $signed,
                'qty' => abs((float) $m->quantity),
                'from' => $m->fromLocation?->name,
                'to' => $m->toLocation?->name,
                'reason' => $m->reason,
                'at' => $m->created_at,
            ];
        }
        $rows = collect(array_values($grouped));

        // New SKUs belong in "All".
        if ($this->tab === 'all' && trim($this->search) === '') {
            $created = InventoryItem::query()->latest('id')->limit(10)->get(['id', 'name', 'created_at'])
                ->map(fn ($i) => ['kind' => 'created', 'item' => $i->name, 'item_id' => $i->id, 'change' => 0, 'qty' => 0, 'from' => null, 'to' => null, 'reason' => null, 'at' => $i->created_at]);
            $rows = $rows->concat($created);
        }

        return $rows->sortByDesc(fn ($r) => $r['at']?->getTimestamp() ?? 0)->take($this->limit)->values();
    }

    public function itemUrl(?int $id): ?string
    {
        return $id ? InventoryItemResource::getUrl('view', ['record' => $id]) : null;
    }
}

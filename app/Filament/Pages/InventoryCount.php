<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class InventoryCount extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Inventory Count';
    protected static ?string $title = 'Inventory Count';
    protected static ?int $navigationSort = 4;

    public ?int $locationId = null;
    public string $search = '';
    public string $countFilter = 'all';
    public string $categoryFilter = '';
    public ?int $selectedItemId = null;

    /** item id => physical quantity entered during this count */
    public array $counts = [];

    /** item id => system quantity for the selected location */
    public array $systemCounts = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Inventory';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->isAdmin() || $user?->isOwner());
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user?->isAdmin() || $user?->isOwner());
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-count';
    }

    public function getSubheading(): ?string
    {
        return 'Count a location from one spreadsheet-style screen. Scan a barcode to find the item, then enter the total physical quantity you counted.';
    }

    public function mount(): void
    {
        $this->locationId = InventoryLocation::defaultReceivingId()
            ?? InventoryLocation::where('status', 'active')
                ->orderByRaw("CASE WHEN type = 'main_storage' THEN 0 ELSE 1 END")
                ->value('id');

        $this->reloadCounts();
    }

    #[Computed]
    public function locations(): array
    {
        return InventoryLocation::where('status', 'active')
            ->orderByRaw("CASE WHEN type = 'main_storage' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    #[Computed]
    public function categories(): array
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category', 'category')
            ->all();
    }

    #[Computed]
    public function items(): Collection
    {
        $term = trim($this->search);

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->when($term !== '', function ($q) use ($term) {
                $like = "%{$term}%";
                $q->where(fn ($s) => $s
                    ->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('upc', 'like', $like)
                    ->orWhere('brand', 'like', $like));
            })
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('category', $this->categoryFilter))
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'sku', 'barcode', 'upc', 'category']);

        if ($this->countFilter === 'all') {
            return $items;
        }

        return $items->filter(function (InventoryItem $item): bool {
            $entered = array_key_exists($item->id, $this->counts)
                && $this->counts[$item->id] !== ''
                && $this->counts[$item->id] !== null;

            $system = (float) ($this->systemCounts[$item->id] ?? 0);
            $counted = $entered ? (float) $this->counts[$item->id] : null;

            return match ($this->countFilter) {
                'uncounted' => ! $entered,
                'counted' => $entered,
                'changed' => $entered && $counted !== $system,
                'matching' => $entered && $counted === $system,
                default => true,
            };
        })->values();
    }

    #[Computed]
    public function progress(): array
    {
        $total = InventoryItem::where('is_active', true)->count();
        $counted = collect($this->counts)
            ->filter(fn ($qty) => $qty !== '' && $qty !== null)
            ->count();
        $changed = collect($this->counts)
            ->filter(function ($qty, $itemId) {
                if ($qty === '' || $qty === null) return false;
                return (float) $qty !== (float) ($this->systemCounts[$itemId] ?? 0);
            })
            ->count();

        return [
            'total' => $total,
            'counted' => $counted,
            'remaining' => max(0, $total - $counted),
            'changed' => $changed,
            'percent' => $total > 0 ? round(($counted / $total) * 100) : 0,
        ];
    }

    public function updatedLocationId(): void
    {
        $this->reloadCounts();
    }

    public function reloadCounts(): void
    {
        $this->counts = [];
        $this->systemCounts = [];
        $this->selectedItemId = null;
        $this->search = '';
        $this->countFilter = 'all';

        if (! $this->locationId) return;

        InventoryStock::where('inventory_location_id', $this->locationId)
            ->get(['inventory_item_id', 'quantity'])
            ->each(function (InventoryStock $stock): void {
                $this->systemCounts[$stock->inventory_item_id] = (float) $stock->quantity;
            });

        unset($this->items, $this->progress);
    }

    public function scan(string $code): void
    {
        $code = trim($code);
        if ($code === '') return;

        $item = InventoryItem::findByScan($code);
        if (! $item) {
            Notification::make()
                ->title('Barcode not found')
                ->body($code . ' is not attached to an inventory item.')
                ->warning()
                ->send();
            return;
        }

        $this->selectedItemId = $item->id;
        $this->search = $item->name;
        $this->countFilter = 'all';
        $this->categoryFilter = '';

        // Do not add +1 and do not assume the system count is correct. Scanning
        // identifies the box; the counter then enters the total physical qty.
        $this->counts[$item->id] ??= '';

        unset($this->items, $this->progress);
        $this->dispatch('inventory-count-focus', itemId: $item->id);

        Notification::make()
            ->title($item->name)
            ->body('Item found. Enter the total quantity physically counted.')
            ->success()
            ->send();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->selectedItemId = null;
        unset($this->items);
    }

    public function clearCount(int $itemId): void
    {
        unset($this->counts[$itemId]);
        unset($this->items, $this->progress);
    }

    public function saveCount(int $itemId): void
    {
        if (! $this->locationId || ! array_key_exists($itemId, $this->counts)) return;
        if ($this->counts[$itemId] === '' || $this->counts[$itemId] === null) return;

        $item = InventoryItem::findOrFail($itemId);
        $location = InventoryLocation::findOrFail($this->locationId);
        $qty = max(0, (float) $this->counts[$itemId]);

        app(InventoryService::class)->adjustStock(
            $item,
            $location,
            $qty,
            'Physical inventory count',
            'adjustment',
        );

        $this->counts[$itemId] = $qty;
        $this->systemCounts[$itemId] = $qty;
        unset($this->progress);

        Notification::make()
            ->title('Count saved')
            ->body($item->name . ': ' . number_format($qty))
            ->success()
            ->send();
    }

    public function saveAll(): void
    {
        if (! $this->locationId) return;

        $location = InventoryLocation::findOrFail($this->locationId);
        $changed = 0;
        $verified = 0;

        foreach ($this->counts as $itemId => $qty) {
            if ($qty === '' || $qty === null) continue;

            $qty = max(0, (float) $qty);
            $before = (float) ($this->systemCounts[$itemId] ?? 0);
            $verified++;

            if ($qty === $before) continue;

            $item = InventoryItem::find($itemId);
            if (! $item) continue;

            app(InventoryService::class)->adjustStock(
                $item,
                $location,
                $qty,
                'Physical inventory count',
                'adjustment',
            );

            $this->systemCounts[$itemId] = $qty;
            $this->counts[$itemId] = $qty;
            $changed++;
        }

        unset($this->items, $this->progress);

        Notification::make()
            ->title('Inventory count saved')
            ->body($verified . ' item(s) verified; ' . $changed . ' stock level(s) changed.')
            ->success()
            ->send();
    }
}

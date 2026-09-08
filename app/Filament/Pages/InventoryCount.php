<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Services\InventoryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class InventoryCount extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Inventory Count';
    protected static ?string $title = 'Inventory Count';
    protected static ?int $navigationSort = 4;

    public ?int $locationId = null;
    public string $search = '';
    public string $scanCode = '';

    /** item id => counted quantity */
    public array $counts = [];

    /** item id => current quantity when the count session loaded */
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
        return 'Count a whole location from one screen. Type quantities like a spreadsheet or scan a barcode to jump straight to an item.';
    }

    public function mount(): void
    {
        $this->locationId = InventoryLocation::defaultReceivingId()
            ?? InventoryLocation::where('status', 'active')->orderByRaw("CASE WHEN type = 'main_storage' THEN 0 ELSE 1 END")->value('id');

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
    public function items()
    {
        $term = trim($this->search);

        return InventoryItem::query()
            ->where('is_active', true)
            ->when($term !== '', function ($q) use ($term) {
                $like = "%{$term}%";
                $q->where(fn ($s) => $s
                    ->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('upc', 'like', $like));
            })
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'sku', 'barcode', 'upc']);
    }

    public function updatedLocationId(): void
    {
        $this->reloadCounts();
    }

    public function reloadCounts(): void
    {
        $this->counts = [];
        $this->systemCounts = [];

        if (! $this->locationId) return;

        InventoryStock::where('inventory_location_id', $this->locationId)
            ->get(['inventory_item_id', 'quantity'])
            ->each(function (InventoryStock $stock): void {
                $qty = (float) $stock->quantity;
                $this->systemCounts[$stock->inventory_item_id] = $qty;
                $this->counts[$stock->inventory_item_id] = $qty;
            });
    }

    public function scan(string $code): void
    {
        $code = trim($code);
        $this->scanCode = '';
        if ($code === '') return;

        $item = InventoryItem::findByScan($code);
        if (! $item) {
            Notification::make()->title('Barcode not found')->body($code)->warning()->send();
            return;
        }

        $this->search = $item->name;

        if (! array_key_exists($item->id, $this->counts)) {
            $current = (float) InventoryStock::where('inventory_location_id', $this->locationId)
                ->where('inventory_item_id', $item->id)
                ->value('quantity');
            $this->systemCounts[$item->id] = $current;
            $this->counts[$item->id] = $current;
        }

        Notification::make()->title($item->name)->body('Ready to count. Enter the physical quantity you see.')->success()->send();
    }

    public function incrementScan(string $code): void
    {
        $code = trim($code);
        if ($code === '') return;

        $item = InventoryItem::findByScan($code);
        if (! $item) {
            Notification::make()->title('Barcode not found')->body($code)->warning()->send();
            return;
        }

        $current = $this->counts[$item->id] ?? (float) InventoryStock::where('inventory_location_id', $this->locationId)
            ->where('inventory_item_id', $item->id)
            ->value('quantity');

        $this->systemCounts[$item->id] ??= $current;
        $this->counts[$item->id] = (float) $current + 1;
        $this->search = $item->name;
    }

    public function saveCount(int $itemId): void
    {
        if (! $this->locationId || ! array_key_exists($itemId, $this->counts)) return;

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

        $this->systemCounts[$itemId] = $qty;
        Notification::make()->title('Count saved')->body($item->name . ': ' . number_format($qty))->success()->send();
    }

    public function saveAll(): void
    {
        if (! $this->locationId) return;

        $changed = 0;
        foreach ($this->counts as $itemId => $qty) {
            $qty = max(0, (float) $qty);
            $before = (float) ($this->systemCounts[$itemId] ?? 0);
            if ($qty === $before) continue;

            $item = InventoryItem::find($itemId);
            if (! $item) continue;

            app(InventoryService::class)->adjustStock(
                $item,
                InventoryLocation::findOrFail($this->locationId),
                $qty,
                'Physical inventory count',
                'adjustment',
            );

            $this->systemCounts[$itemId] = $qty;
            $changed++;
        }

        Notification::make()
            ->title($changed ? 'Inventory count saved' : 'Nothing changed')
            ->body($changed ? $changed . ' item count(s) updated.' : 'All entered quantities already match the system.')
            ->success()
            ->send();
    }
}

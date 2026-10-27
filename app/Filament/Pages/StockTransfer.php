<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Services\InventoryService;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use App\Support\NavVisibility;

class StockTransfer extends Page
{
    use HasModuleAccess { canAccess as moduleAllows; }

    /** Only for people who run a location (StockGate). */
    public static function canAccess(): bool
    {
        return static::moduleAllows() && \App\Support\StockGate::canChangeAny();
    }

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Stock Transfer';

    public ?int $fromLocationId = null;
    public ?int $toLocationId = null;
    public string $searchQuery = '';
    public array $selectedTransfers = []; // ['item_id' => 'quantity', ...]
    public bool $showConfirm = false;
    public string $transferReason = '';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-arrow-path';
    }

    public static function getNavigationGroup(): string|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function getNavigationLabel(): string
    {
        return 'Stock Transfer';
    }

    public function getSubheading(): ?string
    {
        return 'Transfer inventory between locations with bulk operation support.';
    }

    public function getView(): string
    {
        return 'filament.pages.stock-transfer';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return static::canAccess();
    }

    #[Computed]
    public function locations()
    {
        $visible = \App\Support\InventoryVisibility::locationIdsFor(auth()->user());

        return InventoryLocation::where('status', 'active')
            ->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))
            ->orderBy('name')
            ->get();
    }

    /** Where stock can be taken from: locations this person runs (see StockGate). */
    #[Computed]
    public function sourceLocations()
    {
        $ids = \App\Support\StockGate::changeableIds(auth()->user());

        return $this->locations->filter(fn ($location) => in_array((int) $location->id, $ids, true))->values();
    }

    #[Computed]
    public function sourceStock()
    {
        if (! $this->sourceLocations->contains('id', $this->fromLocationId)) {
            return [];
        }

        $query = InventoryStock::where('inventory_location_id', $this->fromLocationId)
            ->where('quantity', '>', 0)
            ->with('item');

        if ($this->searchQuery) {
            $query->whereHas('item', function ($q) {
                $q->where('name', 'LIKE', "%{$this->searchQuery}%")
                    ->orWhere('sku', 'LIKE', "%{$this->searchQuery}%")
                    ->orWhere('barcode', 'LIKE', "%{$this->searchQuery}%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn ($stock) => [
                'id' => $stock->id,
                'item_id' => $stock->inventory_item_id,
                'item_name' => $stock->item?->name ?? 'Unknown',
                'sku' => $stock->item?->sku ?? '—',
                'available' => (float) $stock->quantity,
                'transferring' => (float) ($this->selectedTransfers[$stock->inventory_item_id] ?? 0),
            ])
            ->toArray();
    }

    #[Computed]
    public function transferSummary()
    {
        if (empty($this->selectedTransfers)) {
            return null;
        }

        $itemIds = array_keys($this->selectedTransfers);
        $items = InventoryItem::whereIn('id', $itemIds)->get();

        $totalQty = 0;
        $totalValue = 0;

        foreach ($items as $item) {
            $qty = (float) ($this->selectedTransfers[$item->id] ?? 0);
            $totalQty += $qty;
            $totalValue += $qty * ((float) $item->average_cost);
        }

        return [
            'item_count' => count($this->selectedTransfers),
            'total_quantity' => $totalQty,
            'total_value' => $totalValue,
        ];
    }

    public function updatedFromLocationId(): void
    {
        $this->clearSelection();
        if ($this->fromLocationId === $this->toLocationId) $this->toLocationId = null;
        unset($this->sourceStock, $this->transferSummary);
    }

    public function updateTransferQty(int $itemId, string $qty): void
    {
        $value = max(0, (float) $qty);

        if ($value == 0) {
            unset($this->selectedTransfers[$itemId]);
        } else {
            $this->selectedTransfers[$itemId] = $value;
        }
    }

    public function executeTransfer(): void
    {
        abort_unless(static::canAccess() && ! NavVisibility::isReadOnlyForUser(static::class, auth()->user()), 403);
        if (!$this->fromLocationId || !$this->toLocationId) {
            Notification::make()
                ->title('Invalid transfer')
                ->body('Please select both source and destination locations.')
                ->danger()
                ->send();
            return;
        }

        if ($this->fromLocationId === $this->toLocationId) {
            Notification::make()
                ->title('Invalid transfer')
                ->body('Source and destination must be different locations.')
                ->warning()
                ->send();
            return;
        }

        if (empty($this->selectedTransfers)) {
            Notification::make()
                ->title('No items selected')
                ->body('Select at least one item to transfer.')
                ->warning()
                ->send();
            return;
        }

        $this->validate([
            'selectedTransfers.*' => ['required', 'numeric', 'gt:0'],
            'transferReason' => ['string', 'max:1000'],
        ]);

        try {
            $inventoryService = app(InventoryService::class);
            $fromLocation = $this->sourceLocations->firstWhere('id', $this->fromLocationId);
            if (! $fromLocation) throw new \RuntimeException('Choose an active source you can manage.');
            \App\Support\StockGate::authorize($fromLocation);
            $toLocation = $this->locations->firstWhere('id', $this->toLocationId);
            if (! $toLocation) throw new \RuntimeException('Choose an active, visible destination.');

            // A failed later item must not leave earlier transfers committed.
            \Illuminate\Support\Facades\DB::transaction(function () use ($inventoryService, $fromLocation, $toLocation) {
                $transfers = $this->selectedTransfers;
                ksort($transfers);
                foreach ($transfers as $itemId => $qty) {
                    $item = InventoryItem::findOrFail($itemId);
                    $inventoryService->transferStock(
                        $item,
                        $fromLocation,
                        $toLocation,
                        (float) $qty,
                        $this->transferReason ?: "Bulk transfer from {$fromLocation->name} to {$toLocation->name}"
                    );
                }
            });

            Notification::make()
                ->title('Transfer complete')
                ->body(count($this->selectedTransfers) . ' item(s) transferred successfully.')
                ->success()
                ->send();

            // Reset form
            $this->selectedTransfers = [];
            $this->fromLocationId = null;
            $this->toLocationId = null;
            $this->transferReason = '';
            $this->showConfirm = false;

        } catch (\Exception $e) {
            Notification::make()
                ->title('Transfer failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearSelection(): void
    {
        $this->selectedTransfers = [];
        $this->showConfirm = false;
        unset($this->sourceStock, $this->transferSummary);
    }
}

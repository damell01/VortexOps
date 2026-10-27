<?php

namespace App\Filament\Concerns;

use App\Models\InventoryLocation;
use App\Support\InventoryVisibility;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

trait FiltersInventoryLocation
{
    #[Url(as: 'location')]
    public string $inventoryLocation = '';

    public function inventoryLocationOptions(): array
    {
        $visible = InventoryVisibility::locationIdsFor(auth()->user());

        return InventoryLocation::query()
            ->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))
            ->orderBy('name')->get(['id', 'name', 'status'])
            ->mapWithKeys(fn ($location) => [$location->id => $location->name.($location->status === 'active' ? '' : ' (inactive)')])
            ->all();
    }

    public function updatedInventoryLocation(): void
    {
        $this->clearSelection();
        $this->selectedTableRecords = [];
        $this->deselectedTableRecords = [];
        $this->isTrackingDeselectedTableRecords = false;
        unset($this->cachedSelectedTableRecords);
        $this->deselectAllTableRecords();
        $this->resetPage();
        $this->catalogVisible = 40;
        $this->statsMemo = null;
        unset($this->catalogItems, $this->catalogTotal);
    }

    /**
     * With one location picked: how many of the visible products are there,
     * and where the rest are. A location holding 64 of 412 products reads as
     * a broken filter unless the page says the other 348 are stocked at the
     * receiving warehouse, or were imported and never received anywhere.
     *
     * @return array{here: int, units: float, total: int, elsewhere: array<string, int>, unstocked: int}|null
     */
    public function locationCoverage(): ?array
    {
        if (! $this->locationIsVisible()) {
            return null;
        }

        $location = (int) $this->inventoryLocation;
        $visible = InventoryVisibility::locationIdsFor(auth()->user());
        $ids = \App\Filament\Resources\InventoryItemResource::getEloquentQuery()->reorder()->select('products.id')->toBase();
        $total = \Illuminate\Support\Facades\DB::query()->fromSub($ids, 'visible_products')->count();

        $stock = fn () => \Illuminate\Support\Facades\DB::table('inventory_stock')
            ->whereIn('inventory_stock.inventory_item_id', clone $ids)
            ->when($visible !== null, fn ($q) => $q->whereIn('inventory_stock.inventory_location_id', $visible));

        $here = (clone $stock())->where('inventory_location_id', $location)->distinct()->count('inventory_item_id');
        $units = (float) (clone $stock())->where('inventory_location_id', $location)->where('quantity', '>', 0)->sum('quantity');
        if ($here >= $total) {
            return ['here' => $here, 'units' => $units, 'total' => $total, 'elsewhere' => [], 'unstocked' => 0];
        }

        $notHere = fn ($q) => $q->whereNotIn('inventory_stock.inventory_item_id',
            \Illuminate\Support\Facades\DB::table('inventory_stock')->select('inventory_item_id')->where('inventory_location_id', $location));

        $elsewhere = $notHere($stock())
            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock.inventory_location_id')
            ->where('inventory_stock.quantity', '>', 0)
            ->groupBy('inventory_locations.id', 'inventory_locations.name')
            ->selectRaw('inventory_locations.name, COUNT(DISTINCT inventory_stock.inventory_item_id) as items')
            ->orderByDesc('items')
            ->limit(4)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->name => (int) $row->items])
            ->all();

        $stockedElsewhere = $notHere($stock())->where('inventory_stock.quantity', '>', 0)->distinct()->count('inventory_stock.inventory_item_id');

        return [
            'here' => $here,
            'units' => $units,
            'total' => $total,
            'elsewhere' => $elsewhere,
            'unstocked' => max(0, $total - $here - $stockedElsewhere),
        ];
    }

    protected function locationIsVisible(): bool
    {
        return ctype_digit($this->inventoryLocation)
            && array_key_exists((int) $this->inventoryLocation, $this->inventoryLocationOptions());
    }

    protected function filterInventoryItemsByLocation(Builder $query): Builder
    {
        if ($this->inventoryLocation === '') {
            return $query;
        }
        if (! $this->locationIsVisible()) {
            return $query->whereRaw('1 = 0');
        }

        $location = (int) $this->inventoryLocation;
        $stock = fn ($q) => $q->where('inventory_location_id', $location);

        // Replace the resource's all-location sum: cards, table quantities,
        // health counts and low/out-of-stock filtering must agree. Keep zero
        // rows so an empty shelf can still be found with Out of Stock.
        return $query->select('products.*')->whereHas('stock', $stock)
            ->withSum(['stock as stock_sum_quantity' => $stock], 'quantity')
            ->with(['stock' => $stock, 'stock.location']);
    }
}

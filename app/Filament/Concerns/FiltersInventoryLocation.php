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
        $this->resetPage();
        $this->catalogVisible = 40;
        $this->statsMemo = null;
        unset($this->catalogItems, $this->catalogTotal);
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

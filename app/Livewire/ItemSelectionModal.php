<?php

namespace App\Livewire;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Show;
use App\Models\WhatnotShowOrder;
use Livewire\Component;
use Livewire\Attributes\Reactive;

class ItemSelectionModal extends Component
{
    #[Reactive]
    public ?int $orderId = null;

    #[Reactive]
    public ?int $showId = null;

    public string $search = '';
    public ?int $selectedItemId = null;
    public ?int $selectedLocationId = null;
    public float $unitCost = 0;
    public ?Show $show = null;
    public ?WhatnotShowOrder $order = null;
    public bool $isSearching = false;

    public function mount(?int $orderId = null, ?int $showId = null): void
    {
        $this->orderId = $orderId;
        $this->showId = $showId;

        if ($orderId) {
            $this->order = WhatnotShowOrder::find($orderId);
            $this->show = $this->order?->show;
        } elseif ($showId) {
            $this->show = Show::find($showId);
        }
    }

    public function getSearchResults()
    {
        if (strlen($this->search) < 2) {
            return [];
        }

        $streamer = $this->show?->primaryStreamer();
        $locationIds = $streamer?->inventoryLocations()->pluck('id') ?? collect();

        $query = InventoryItem::query()
            ->where('is_active', true)
            ->where(fn ($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
            );

        // Prioritize items from streamer's locations if they have assigned locations
        if ($locationIds->isNotEmpty()) {
            $scoped = (clone $query)
                ->whereHas('stock', fn ($s) => $s->whereIn('inventory_location_id', $locationIds))
                ->orderBy('name')
                ->limit(20)
                ->get();

            if ($scoped->isNotEmpty()) {
                return $scoped;
            }
        }

        return $query->orderBy('name')->limit(20)->get();
    }

    public function updatedSearch($value): void
    {
        if (strlen($value) >= 2) {
            // Track search in client-side history via dispatch
            $this->dispatch('trackSearch', query: $value);
        }
    }

    public function selectItem($itemId): void
    {
        $item = InventoryItem::find($itemId);
        if ($item) {
            $this->selectedItemId = $itemId;
            $this->unitCost = (float) ($item->average_cost ?? $item->unit_cost ?? 0);
        }
    }

    public function getAvailableLocations()
    {
        $streamer = $this->show?->primaryStreamer();

        if ($streamer?->inventoryLocations()->exists()) {
            return $streamer->inventoryLocations()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        return InventoryLocation::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function save(): void
    {
        if (! $this->selectedItemId) {
            session()->flash('error', 'Please select an item');
            return;
        }

        if ($this->order) {
            $this->order->update([
                'inventory_item_id' => $this->selectedItemId,
                'inventory_location_id' => $this->selectedLocationId,
                'unit_cost' => $this->unitCost,
            ]);
        }

        $this->dispatch('itemSelected', [
            'itemId' => $this->selectedItemId,
            'locationId' => $this->selectedLocationId,
            'cost' => $this->unitCost,
        ]);

        $this->reset(['search', 'selectedItemId', 'selectedLocationId', 'unitCost']);
    }

    public function render()
    {
        return view('livewire.item-selection-modal', [
            'searchResults' => $this->getSearchResults(),
            'availableLocations' => $this->getAvailableLocations(),
        ]);
    }
}

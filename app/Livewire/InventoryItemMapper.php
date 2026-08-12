<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\WhatnotShowOrder;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;

class InventoryItemMapper extends Component
{
    public ?WhatnotShowOrder $order = null;
    public ?int $orderId = null;
    public bool $isOpen = false;

    public string $itemSearch = '';
    public ?int $selectedItemId = null;
    public ?int $selectedLocationId = null;
    public int $quantity = 1;

    public array $searchResults = [];

    #[\Livewire\Attributes\On('open-inventory-mapper')]
    public function openMapper(int $orderId): void
    {
        $this->orderId = $orderId;
        $this->order = WhatnotShowOrder::find($orderId);
        $this->isOpen = true;
    }

    #[\Livewire\Attributes\Debounce(300)]
    public function updatedItemSearch(): void
    {
        if (strlen($this->itemSearch) < 2) {
            $this->searchResults = [];
            return;
        }

        $this->searchResults = InventoryItem::where('name', 'like', '%' . $this->itemSearch . '%')
            ->limit(10)
            ->pluck('name', 'id')
            ->toArray();
    }

    public function mapItem(): void
    {
        if (!$this->selectedItemId || !$this->selectedLocationId) {
            $this->addError('mapper', 'Please select both an item and a location');
            return;
        }

        $this->order->update([
            'inventory_item_id' => $this->selectedItemId,
            'inventory_location_id' => $this->selectedLocationId,
            'quantity' => $this->quantity,
        ]);

        $this->dispatch('item-mapped', orderId: $this->orderId);
        $this->closeMapper();
    }

    public function closeMapper(): void
    {
        $this->isOpen = false;
        $this->order = null;
        $this->orderId = null;
        $this->itemSearch = '';
        $this->selectedItemId = null;
        $this->selectedLocationId = null;
        $this->quantity = 1;
        $this->searchResults = [];
    }

    public function render()
    {
        return view('livewire.inventory-item-mapper', [
            'locations' => InventoryLocation::where('status', 'active')->get(),
        ]);
    }
}

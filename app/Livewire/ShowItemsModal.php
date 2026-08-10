<?php

namespace App\Livewire;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use Livewire\Component;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\On;

class ShowItemsModal extends Component
{
    #[Reactive]
    public ?int $showId = null;

    public ?int $selectedLocationId = null;
    public string $search = '';
    public array $selectedItems = [];
    public bool $isOpen = false;

    public function getLocations(): array
    {
        return InventoryLocation::where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getAvailableItems(): array
    {
        if (!$this->selectedLocationId) {
            return [];
        }

        $query = InventoryStock::where('inventory_location_id', $this->selectedLocationId)
            ->where('quantity', '>', 0)
            ->with('item');

        if ($this->search) {
            $query->whereHas('item', function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('sku', 'like', "%{$this->search}%");
            });
        }

        return $query->orderBy('quantity', 'desc')->get()->map(function ($stock) {
            return [
                'id' => $stock->item->id,
                'name' => $stock->item->name,
                'sku' => $stock->item->sku,
                'quantity_available' => $stock->quantity,
                'average_cost' => $stock->item->average_cost ?? $stock->item->unit_cost ?? 0,
            ];
        })->values()->toArray();
    }

    public function toggleItem(int $itemId): void
    {
        if (isset($this->selectedItems[$itemId])) {
            unset($this->selectedItems[$itemId]);
        } else {
            $item = InventoryItem::find($itemId);
            if ($item) {
                $this->selectedItems[$itemId] = [
                    'id' => $itemId,
                    'name' => $item->name,
                    'quantity' => 1,
                    'unit_cost' => $item->average_cost ?? $item->unit_cost ?? 0,
                ];
            }
        }
    }

    public function updateQuantity(int $itemId, ?float $quantity): void
    {
        if ($quantity && $quantity > 0 && isset($this->selectedItems[$itemId])) {
            $this->selectedItems[$itemId]['quantity'] = (float) $quantity;
        }
    }

    public function updateCost(int $itemId, ?float $cost): void
    {
        if ($cost !== null && isset($this->selectedItems[$itemId])) {
            $this->selectedItems[$itemId]['unit_cost'] = (float) $cost;
        }
    }

    public function save(): void
    {
        if (empty($this->selectedItems) || !$this->selectedLocationId) {
            session()->flash('error', 'Please select a location and at least one item');
            return;
        }

        $items = array_values($this->selectedItems);
        $this->dispatch('itemsSelected', items: $items, locationId: $this->selectedLocationId);
        $this->reset(['selectedLocationId', 'search', 'selectedItems']);
        $this->isOpen = false;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->reset();
    }

    public function openModal(): void
    {
        $this->isOpen = true;
    }

    #[On('openShowItemsModal')]
    public function handleOpenModal(): void
    {
        $this->openModal();
    }

    #[On('closeShowItemsModal')]
    public function handleCloseModal(): void
    {
        $this->close();
    }

    public function render()
    {
        return view('livewire.show-items-modal', [
            'locations' => $this->getLocations(),
            'items' => $this->getAvailableItems(),
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Livewire\Attributes\Reactive;

class InventorySelectorModal extends Component
{
    #[Reactive]
    public $showId;

    public string $search = '';
    public string $selectedCategory = '';
    public ?int $selectedItemId = null;
    public string $newItemName = '';
    public ?float $newItemCost = null;
    public bool $showingCreateForm = false;

    public function getInventoryItems(): Collection
    {
        $query = InventoryItem::query()->where('is_active', true);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('sku', 'like', "%{$this->search}%");
            });
        }

        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }

        return $query->with('stock')->get();
    }

    public function getCategories(): array
    {
        return InventoryItem::distinct()
            ->where('is_active', true)
            ->whereNotNull('category')
            ->pluck('category')
            ->sort()
            ->values()
            ->toArray();
    }

    public function selectItem(int $itemId): void
    {
        $this->selectedItemId = $itemId;
        $this->dispatch('itemSelected', itemId: $itemId);
    }

    public function createNewItem(): void
    {
        if (! $this->newItemName) {
            return;
        }

        $item = InventoryItem::create([
            'name' => $this->newItemName,
            'unit_cost' => $this->newItemCost,
            'is_active' => true,
        ]);

        $this->selectedItemId = $item->id;
        $this->dispatch('itemSelected', itemId: $item->id);
        $this->reset('newItemName', 'newItemCost', 'showingCreateForm');
    }

    public function render()
    {
        return view('livewire.inventory-selector-modal', [
            'items' => $this->getInventoryItems(),
            'categories' => $this->getCategories(),
        ]);
    }
}

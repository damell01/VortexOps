<?php

namespace App\Livewire;

use App\Models\InventoryItem;
use App\Models\StreamerLogEntry;
use App\Models\WhatnotShowOrder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Livewire\Attributes\Reactive;

class StreamerLogItemsModal extends Component
{
    #[Reactive]
    public int $recordId;

    #[Reactive]
    public ?string $title = null;

    #[Reactive]
    public ?string $description = null;

    #[Reactive]
    public bool $multiSelect = true;

    #[Reactive]
    public bool $allowQuantityInput = true;

    #[Reactive]
    public bool $allowCostInput = true;

    #[Reactive]
    public bool $allowCreateItem = true;

    #[Reactive]
    public ?string $successEvent = 'items-added';

    public string $search = '';
    public string $selectedCategory = '';
    public array $selectedItems = [];
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

    public function toggleItem(int $itemId): void
    {
        if (isset($this->selectedItems[$itemId])) {
            unset($this->selectedItems[$itemId]);
            return;
        }

        if (!$this->multiSelect && !empty($this->selectedItems)) {
            $this->selectedItems = [];
        }

        $item = InventoryItem::find($itemId);
        if ($item) {
            $this->selectedItems[$itemId] = [
                'quantity' => 1,
                'name' => $item->name,
                'unit_cost' => $item->unit_cost ?? 0,
                'item_id' => $item->id,
            ];
        }
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        if (isset($this->selectedItems[$itemId])) {
            $this->selectedItems[$itemId]['quantity'] = max(1, $quantity);
        }
    }

    public function updateUnitCost(int $itemId, float $cost): void
    {
        if (isset($this->selectedItems[$itemId])) {
            $this->selectedItems[$itemId]['unit_cost'] = max(0, $cost);
        }
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

        $this->selectedItems[$item->id] = [
            'quantity' => 1,
            'name' => $item->name,
            'unit_cost' => $item->unit_cost ?? 0,
            'item_id' => $item->id,
        ];

        $this->reset('newItemName', 'newItemCost', 'showingCreateForm');
    }

    public function confirmSelection(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        $record = StreamerLogEntry::find($this->recordId);
        if (! $record || ! $record->show) {
            return;
        }

        foreach ($this->selectedItems as $itemId => $data) {
            WhatnotShowOrder::updateOrCreate(
                [
                    'show_id' => $record->show_id,
                    'inventory_item_id' => $itemId,
                ],
                [
                    'quantity' => $data['quantity'],
                    'unit_cost' => $data['unit_cost'],
                    'total_cost' => round((float) $data['unit_cost'] * (int) $data['quantity'], 2),
                ]
            );
        }

        if ($this->successEvent) {
            $this->dispatch($this->successEvent);
        }

        $this->dispatch('closeModal');
    }

    public function render()
    {
        return view('livewire.streamer-log-items-modal', [
            'items' => $this->getInventoryItems(),
            'categories' => $this->getCategories(),
            'title' => $this->title ?? 'Add Items to Show',
            'description' => $this->description ?? 'Search inventory, select items, and set quantities',
        ]);
    }
}

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

        // Streamers only see inventory from their assigned locations
        $user = auth()->user();
        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            $streamer = $user->streamer;
            if ($streamer) {
                $locationIds = $streamer->inventoryLocations()->pluck('id');
                $query->whereHas('stock', fn ($q) =>
                    $q->whereIn('inventory_location_id', $locationIds)
                )->distinct();
            } else {
                // Streamer without assigned locations - return empty
                return collect();
            }
        }

        return $query->with('stock')->get();
    }

    public function getCategories(): array
    {
        $query = InventoryItem::where('is_active', true)->whereNotNull('category');

        // Streamers only see categories from their assigned inventory locations
        $user = auth()->user();
        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            $streamer = $user->streamer;
            if ($streamer) {
                $locationIds = $streamer->inventoryLocations()->pluck('id');
                $query->whereHas('stock', fn ($q) =>
                    $q->whereIn('inventory_location_id', $locationIds)
                );
            } else {
                return [];
            }
        }

        return $query->distinct()
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

        $totalCost = 0;

        foreach ($this->selectedItems as $itemId => $data) {
            $item = InventoryItem::find($itemId);
            $unitCost = (float) $data['unit_cost'] ?: ((float) $item?->unit_cost ?? 0);
            $qty = (int) $data['quantity'];

            WhatnotShowOrder::updateOrCreate(
                [
                    'show_id' => $record->show_id,
                    'inventory_item_id' => $itemId,
                ],
                [
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($unitCost * $qty, 2),
                ]
            );

            $totalCost += round($unitCost * $qty, 2);
        }

        // Update log entry with product cost and set status if still pending
        $record->update([
            'product_cost' => $totalCost,
            'status' => $record->status === 'pending' ? 'pending' : $record->status,
        ]);

        // Send notification to admins/owners
        $show = $record->show;
        \Filament\Notifications\Notification::make()
            ->title('📦 Items Added to Streamer Log')
            ->body("Items have been added to the log for \"{$show->title}\"")
            ->success()
            ->sendToDatabase($show->streamers->first()?->user);

        if ($this->successEvent) {
            $this->js("window.dispatchEvent(new CustomEvent('{$this->successEvent}'))");
        }
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

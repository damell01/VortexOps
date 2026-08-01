<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Show;
use App\Models\StreamerLogEntry;

class ShowStreamerLogPanel extends Component
{
    public ?Show $show = null;
    public ?StreamerLogEntry $logEntry = null;
    public bool $isOpen = false;
    public ?int $mapItemId = null;

    #[\Livewire\Attributes\On('open-show')]
    public function openShow(Show $show): void
    {
        $this->show = $show;
        $this->logEntry = $show->streamerLogEntry()->first() ?? StreamerLogEntry::create([
            'show_id' => $show->id,
            'streamer_id' => auth()->user()->streamer?->id,
            'status' => 'pending',
            'gross_revenue' => $show->gross_revenue,
        ]);
        $this->isOpen = true;
    }

    public function closePanel(): void
    {
        $this->isOpen = false;
        $this->show = null;
        $this->logEntry = null;
    }

    public function openInventoryMapper(int $orderId): void
    {
        $this->mapItemId = $orderId;
        $this->dispatch('open-inventory-mapper', orderId: $orderId);
    }

    public function markReadyForFulfillment(): void
    {
        if (!$this->show) return;

        $unmappedCount = $this->show->orders()->whereNull('inventory_item_id')->count();
        if ($unmappedCount > 0) {
            $this->dispatch('notify', type: 'warning', message: "Cannot mark ready: {$unmappedCount} items still need mapping.");
            return;
        }

        $this->show->update(['fulfillment_ready' => true]);
        $this->dispatch('notify', type: 'success', message: 'Show marked ready for fulfillment!');
        $this->closePanel();
    }

    public function render()
    {
        return view('livewire.show-streamer-log-panel', [
            'orders' => $this->show?->orders()->get() ?? collect(),
        ]);
    }
}

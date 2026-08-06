<?php

namespace App\Livewire;

use App\Models\StreamerLogEntry;
use Livewire\Component;

class StreamerLogTabs extends Component
{
    public StreamerLogEntry $record;
    public string $activeTab = 'overview';

    public function mount(StreamerLogEntry $record)
    {
        $this->record = $record;
    }

    public function setTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $this->record->load([
            'show.orders.inventoryItem',
            'show.shipments',
        ]);

        return view('livewire.streamer-log-tabs', [
            'record' => $this->record,
            'show' => $this->record->show,
            'orders' => $this->record->show?->orders()->whereNotNull('inventory_item_id')->get() ?? collect(),
            'shipments' => $this->record->show?->shipments ?? collect(),
        ]);
    }
}

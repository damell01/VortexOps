<?php

namespace App\Livewire;

use App\Services\InventoryPriceAlerts;
use App\Support\InventoryVisibility;
use Livewire\Component;

class InventoryPriceAcknowledgment extends Component
{
    public array $changes = [];

    public function mount(InventoryPriceAlerts $alerts): void
    {
        $user = auth()->user();
        if ($user && InventoryVisibility::isReadOnlyStreamer($user)) {
            $this->changes = $alerts->pending($user);
        }
    }

    public function acknowledge(InventoryPriceAlerts $alerts): void
    {
        abort_unless(auth()->check() && InventoryVisibility::isReadOnlyStreamer(auth()->user()), 403);
        if ($this->changes === []) {
            return;
        }

        $last = end($this->changes);
        $alerts->acknowledge(auth()->user(), (int) $last['id']);
        $this->changes = $alerts->pending(auth()->user());
    }

    public function render()
    {
        return view('livewire.inventory-price-acknowledgment');
    }
}

<?php

namespace App\Livewire;

use App\Services\InventoryPriceAlerts;
use App\Support\InventoryVisibility;
use Livewire\Component;

class InventoryPriceAcknowledgment extends Component
{
    public array $changes = [];
    public array $comingSoon = [];
    public array $arrivals = [];

    public function mount(InventoryPriceAlerts $alerts): void
    {
        $user = auth()->user();
        if ($user && InventoryVisibility::isReadOnlyStreamer($user)) {
            $this->changes = $alerts->pending($user);
            $this->comingSoon = app(\App\Services\\ComingSoonPalletItems::class)->items($user);
        }
    }

    public function acknowledge(InventoryPriceAlerts $alerts): void
    {
        abort_unless(auth()->check() && InventoryVisibility::isReadOnlyStreamer(auth()->user()), 403);
        if ($this->changes === [] && $this->comingSoon === [] && $this->arrivals === []) {
            return;
        }

        if ($this->changes !== []) {
            $last = end($this->changes);
            $alerts->acknowledge(auth()->user(), (int) $last['id']);
        }
        if ($this->comingSoon !== []) {
            app(\App\Services\ComingSoonPalletItems::class)->acknowledge(auth()->user(), $this->comingSoon);
        }
        if ($this->arrivals !== []) {
            app(\App\Services\InventoryArrivalAlerts::class)->acknowledge(auth()->user(), $this->arrivals);
        }
        $this->arrivals = app(\App\Services\InventoryArrivalAlerts::class)->pending(auth()->user());
        $this->changes = $alerts->pending(auth()->user());
        $this->comingSoon = app(\App\Services\ComingSoonPalletItems::class)->items(auth()->user());
    }

    public function render()
    {
        return view('livewire.inventory-price-acknowledgment');
    }
}

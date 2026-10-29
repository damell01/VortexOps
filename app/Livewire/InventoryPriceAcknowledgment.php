<?php

namespace App\Livewire;

use App\Services\InventoryPriceAlerts;
use App\Support\InventoryVisibility;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;

class InventoryPriceAcknowledgment extends Component
{
    #[Locked]
    public array $changes = [];
    #[Locked]
    public array $comingSoon = [];
    #[Locked]
    public array $arrivals = [];

    public function mount(InventoryPriceAlerts $alerts): void
    {
        $user = auth()->user();
        if ($user && \App\Support\InventoryPopupPolicy::enabledFor($user)) {
            $this->changes = $alerts->pending($user);
            $this->comingSoon = app(\App\Services\ComingSoonPalletItems::class)->items($user);
            $this->arrivals = app(\App\Services\InventoryArrivalAlerts::class)->pending($user);
        }
    }

    public function acknowledge(InventoryPriceAlerts $alerts): void
    {
        abort_unless(auth()->check() && \App\Support\InventoryPopupPolicy::enabledFor(auth()->user()), 403);
        if ($this->changes === [] && $this->comingSoon === [] && $this->arrivals === []) {
            return;
        }

        try {
            DB::transaction(function () use ($alerts): void {
                // These locked arrays are the batch actually shown in the popup.
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
            });
        } catch (\InvalidArgumentException $e) {
            \Filament\Notifications\Notification::make()->title('Inventory changed — review the updated list')->warning()->send();
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

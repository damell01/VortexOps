<?php

namespace App\Livewire;

use App\Support\InventoryVisibility;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class InventoryArrivalFeed extends Component
{
    public function render()
    {
        $locations = InventoryVisibility::locationIdsFor(auth()->user());
        $query = DB::table('inventory_arrival_events as arrivals')
            ->join('products', 'products.id', '=', 'arrivals.product_id')
            ->whereNull('products.deleted_at')
            ->where('arrivals.created_at', '>=', now()->subDays(7));
        if ($locations !== null) {
            $query->whereIn('arrivals.location_id', $locations);
        }
        return view('livewire.inventory-arrival-feed', [
            'events' => $query->orderByDesc('arrivals.id')->limit(10)->get([
                'arrivals.id', 'arrivals.kind', 'products.name', 'arrivals.created_at',
            ]),
        ]);
    }
}

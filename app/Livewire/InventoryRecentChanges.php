<?php

namespace App\Livewire;

use App\Models\InventoryLocation;
use App\Support\InventoryVisibility;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class InventoryRecentChanges extends Component
{
    public bool $open = false;
    public string $tab = 'all';
    public string $range = '30';
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    public function updatedRange(): void
    {
        if (! in_array($this->range, ['7', '30', '90', 'custom'], true)) {
            $this->range = '30';
        }
    }

    private function dateBounds(): array
    {
        if ($this->range === 'custom') {
            $from = $this->dateFrom ? \Illuminate\Support\Carbon::parse($this->dateFrom)->startOfDay() : now()->subDays(29)->startOfDay();
            $to = $this->dateTo ? \Illuminate\Support\Carbon::parse($this->dateTo)->endOfDay() : now()->endOfDay();
            return [$from->min(now()->endOfDay()), $to];
        }

        return [now()->subDays(((int) $this->range) - 1)->startOfDay(), now()->endOfDay()];
    }

    public function show(): void
    {
        abort_unless(auth()->check() && ! auth()->user()->isStreamerAssistant(), 403);
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function render()
    {
        $prices = collect();
        $stock = collect();
        if ($this->open && auth()->check() && ! auth()->user()->isStreamerAssistant()) {
            $user = auth()->user();
            $locations = InventoryVisibility::locationIdsFor($user);
            if (InventoryVisibility::isReadOnlyStreamer($user)) {
                $locations = array_values(array_unique(array_merge(
                    $locations ?? [],
                    array_map('intval', array_keys(InventoryLocation::activeOptionsByType('main_storage')))
                )));
            }
            [$since, $until] = $this->dateBounds();
            $prices = DB::table('inventory_price_change_events as e')
                ->join('products as p', 'p.id', '=', 'e.product_id')
                ->whereNull('p.deleted_at')->whereBetween('e.created_at', [$since, $until])
                ->when($locations !== null, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('inventory_stock as s')->whereColumn('s.inventory_item_id', 'e.product_id')->whereIn('s.inventory_location_id', $locations)))
                ->orderByDesc('e.id')->limit(100)
                ->get(['e.id','e.old_price','e.new_price','e.created_at','p.name','p.image_path']);
            $stock = DB::table('inventory_arrival_events as e')
                ->join('products as p', 'p.id', '=', 'e.product_id')
                ->whereNull('p.deleted_at')->where('e.created_at', '>=', $since)
                ->when($locations !== null, fn ($q) => $q->whereIn('e.location_id', $locations))
                ->orderByDesc('e.id')->limit(100)
                ->get(['e.id','e.kind','e.quantity_before','e.quantity_after','e.created_at','p.name','p.image_path']);
        }
        return view('livewire.inventory-recent-changes', compact('prices', 'stock'));
    }
}

<?php

namespace App\Livewire;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventorySnapshot;
use Livewire\Component;

class InventoryDashboard extends Component
{
    public string $filterLocation = '';
    public string $filterCategory = '';
    public bool $lowStockOnly = false;

    public function render()
    {
        $currentSnapshot = InventorySnapshot::latest('snapshot_date')->first();
        if (!$currentSnapshot || $currentSnapshot->snapshot_date->diffInHours(now()) > 1) {
            $currentSnapshot = InventorySnapshot::generateCurrent();
        }

        // Get items with filters
        $query = InventoryItem::with(['stock.location'])->where('is_active', true);

        if ($this->filterCategory) {
            $query->where('category', $this->filterCategory);
        }

        if ($this->lowStockOnly) {
            $query->whereNotNull('reorder_level')->whereHas('stock', function ($q) {
                $q->havingRaw('SUM(quantity) <= reorder_level');
            }, '<=');
        }

        $items = $query->withSum('stock', 'quantity')->get();

        // Calculate metrics
        $healthyCount = $items->filter(fn ($i) => ($i->stock_sum_quantity ?? 0) > ($i->reorder_level ?? 0))->count();
        $lowStockCount = $items->filter(fn ($i) => ($i->stock_sum_quantity ?? 0) > 0 && ($i->stock_sum_quantity ?? 0) <= ($i->reorder_level ?? 0))->count();
        $outOfStockCount = $items->filter(fn ($i) => ($i->stock_sum_quantity ?? 0) <= 0)->count();

        $categories = InventoryItem::whereNotNull('category')->distinct()->pluck('category')->sort();
        $locations = InventoryLocation::where('status', 'active')->get();

        return view('livewire.inventory-dashboard', [
            'snapshot' => $currentSnapshot,
            'items' => $items,
            'categories' => $categories,
            'locations' => $locations,
            'healthyCount' => $healthyCount,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
        ]);
    }
}

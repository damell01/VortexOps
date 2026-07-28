<?php

namespace App\Console\Commands;

use App\Models\InventoryValueSnapshot;
use App\Models\WhatnotChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SnapshotInventoryValue extends Command
{
    protected $signature = 'inventory:snapshot-value';

    protected $description = 'Capture today\'s total inventory value (WAC) per channel, for monthly valuation trend reporting';

    public function handle(): int
    {
        $today = now()->toDateString();

        foreach (WhatnotChannel::all() as $channel) {
            $this->captureFor($today, $channel->id);
        }

        // All-channels combined snapshot.
        $this->captureFor($today, null);

        $this->info("Inventory value snapshot captured for {$today}.");

        return self::SUCCESS;
    }

    private function captureFor(string $date, ?int $channelId): void
    {
        $query = DB::table('inventory_stock')
            ->join('products', 'inventory_stock.inventory_item_id', '=', 'products.id')
            ->whereNotNull('products.average_cost')
            ->where('products.average_cost', '>', 0);

        if ($channelId) {
            $query->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock.inventory_location_id')
                ->where('inventory_locations.whatnot_channel_id', $channelId);
        }

        $totals = $query->selectRaw(
            'SUM(inventory_stock.quantity * products.average_cost) as total_value, ' .
            'SUM(inventory_stock.quantity) as total_quantity, ' .
            'COUNT(DISTINCT products.id) as total_items'
        )->first();

        InventoryValueSnapshot::updateOrCreate(
            ['snapshot_date' => $date, 'whatnot_channel_id' => $channelId],
            [
                'total_value'    => $totals->total_value ?? 0,
                'total_quantity' => $totals->total_quantity ?? 0,
                'total_items'    => $totals->total_items ?? 0,
            ]
        );
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\InventoryValueSnapshot;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotInventoryValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_captures_a_combined_and_per_channel_snapshot(): void
    {
        $channel  = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $location = InventoryLocation::create([
            'name'               => 'Main Storage',
            'type'               => 'main_storage',
            'status'             => 'active',
            'whatnot_channel_id' => $channel->id,
        ]);
        $item = InventoryItem::create([
            'name'         => 'Test Cards',
            'average_cost' => 10.00,
            'is_active'    => true,
        ]);
        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 25,
        ]);

        $this->artisan('inventory:snapshot-value')->assertSuccessful();

        $today = now()->toDateString();

        $combined = InventoryValueSnapshot::where('snapshot_date', $today)->whereNull('whatnot_channel_id')->first();
        $this->assertNotNull($combined);
        $this->assertEquals(250.00, (float) $combined->total_value); // 25 * 10.00
        $this->assertEquals(25, (float) $combined->total_quantity);
        $this->assertEquals(1, $combined->total_items);

        $perChannel = InventoryValueSnapshot::where('snapshot_date', $today)->where('whatnot_channel_id', $channel->id)->first();
        $this->assertNotNull($perChannel);
        $this->assertEquals(250.00, (float) $perChannel->total_value);
    }

    public function test_rerunning_the_same_day_updates_rather_than_duplicates(): void
    {
        $item     = InventoryItem::create(['name' => 'Test Cards', 'average_cost' => 5.00, 'is_active' => true]);
        $location = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 10]);

        $this->artisan('inventory:snapshot-value')->assertSuccessful();

        InventoryStock::first()->update(['quantity' => 40]);

        $this->artisan('inventory:snapshot-value')->assertSuccessful();

        $this->assertEquals(1, InventoryValueSnapshot::whereNull('whatnot_channel_id')->count());
        $this->assertEquals(200.00, (float) InventoryValueSnapshot::whereNull('whatnot_channel_id')->first()->total_value); // 40 * 5.00
    }

    public function test_average_value_for_month_averages_across_daily_snapshots(): void
    {
        $month = now()->startOfMonth();

        InventoryValueSnapshot::create(['snapshot_date' => $month->copy()->addDays(0), 'total_value' => 100]);
        InventoryValueSnapshot::create(['snapshot_date' => $month->copy()->addDays(1), 'total_value' => 200]);
        InventoryValueSnapshot::create(['snapshot_date' => $month->copy()->addDays(2), 'total_value' => 300]);

        $this->assertEquals(200.0, InventoryValueSnapshot::averageValueForMonth($month));
    }
}

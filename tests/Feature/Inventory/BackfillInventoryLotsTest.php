<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillInventoryLotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_creates_nothing(): void
    {
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $item     = InventoryItem::create(['name' => 'Box', 'sku' => 'B1', 'unit_cost' => 10, 'average_cost' => 0, 'is_active' => true]);

        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 40]);

        $this->artisan('inventory:backfill-lots')->assertSuccessful();

        $this->assertSame(0, InventoryLot::count());
    }

    public function test_apply_covers_the_gap_at_the_items_known_cost(): void
    {
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $item     = InventoryItem::create(['name' => 'Box', 'sku' => 'B1', 'unit_cost' => 10, 'average_cost' => 0, 'is_active' => true]);

        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 40]);

        $this->artisan('inventory:backfill-lots', ['--apply' => true])
            ->expectsConfirmation(
                '1 synthetic lot(s) will be created for stock received before lot tracking covered it. Continue?',
                'yes',
            )
            ->assertSuccessful();

        $lot = InventoryLot::where('product_id', $item->id)->first();
        $this->assertNotNull($lot);
        $this->assertEquals(40, (float) $lot->remaining_quantity);
        $this->assertEquals(10, (float) $lot->unit_cost);
        $this->assertSame(InventoryLot::SOURCE_SYNTHETIC, $lot->source);

        $this->assertEquals(10.0000, (float) $item->fresh()->average_cost);
    }

    public function test_a_gap_already_partly_covered_only_backfills_the_shortfall(): void
    {
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $item     = InventoryItem::create(['name' => 'Box', 'sku' => 'B1', 'unit_cost' => 10, 'average_cost' => 0, 'is_active' => true]);

        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 40]);
        InventoryLot::create([
            'product_id' => $item->id, 'quantity' => 15, 'unit_cost' => 6,
            'remaining_quantity' => 15, 'source' => InventoryLot::SOURCE_RECEIVED,
            'status' => InventoryLot::STATUS_ACTIVE, 'received_at' => now(),
        ]);

        $this->artisan('inventory:backfill-lots', ['--apply' => true])
            ->expectsConfirmation(
                '1 synthetic lot(s) will be created for stock received before lot tracking covered it. Continue?',
                'yes',
            )
            ->assertSuccessful();

        $this->assertSame(2, InventoryLot::where('product_id', $item->id)->count());

        $synthetic = InventoryLot::where('product_id', $item->id)->where('source', InventoryLot::SOURCE_SYNTHETIC)->first();
        $this->assertEquals(25, (float) $synthetic->remaining_quantity);

        // (15*6 + 25*10) / 40 = 8.5
        $this->assertEqualsWithDelta(8.5, (float) $item->fresh()->average_cost, 0.01);
    }

    public function test_no_gap_backfills_nothing(): void
    {
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $item     = InventoryItem::create(['name' => 'Box', 'sku' => 'B1', 'unit_cost' => 10, 'average_cost' => 0, 'is_active' => true]);

        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 15]);
        InventoryLot::create([
            'product_id' => $item->id, 'quantity' => 15, 'unit_cost' => 6,
            'remaining_quantity' => 15, 'source' => InventoryLot::SOURCE_RECEIVED,
            'status' => InventoryLot::STATUS_ACTIVE, 'received_at' => now(),
        ]);

        $this->artisan('inventory:backfill-lots', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, InventoryLot::where('product_id', $item->id)->count());
    }

    public function test_a_gap_with_no_known_cost_is_reported_but_not_created(): void
    {
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $item     = InventoryItem::create(['name' => 'Mystery Box', 'sku' => 'B2', 'unit_cost' => 0, 'average_cost' => 0, 'is_active' => true]);

        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 40]);

        $this->artisan('inventory:backfill-lots', ['--apply' => true])->assertSuccessful();

        $this->assertSame(0, InventoryLot::count());
    }
}

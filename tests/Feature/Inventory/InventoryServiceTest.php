<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $service;
    private InventoryItem $item;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventoryService::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->location = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        $this->item      = InventoryItem::create([
            'name'                  => 'Test Cards',
            'unit_cost'             => 5.00,
            'average_cost'          => 5.00,
            'total_units_received'  => 100,
            'is_active'             => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => 100,
        ]);

        // Backs the fixture's starting stock with a lot, the way the backfill
        // command does for pre-existing inventory — otherwise the weighted
        // average below would only see the lot this test itself adds.
        InventoryLot::create([
            'product_id'         => $this->item->id,
            'quantity'           => 100,
            'unit_cost'          => 5.00,
            'remaining_quantity' => 100,
            'source'             => InventoryLot::SOURCE_SYNTHETIC,
            'status'             => InventoryLot::STATUS_ACTIVE,
            'received_at'        => now()->subDay(),
        ]);
    }

    public function test_add_stock_without_unit_cost_leaves_average_cost_unchanged(): void
    {
        $this->service->addStock($this->item, $this->location, 50, 'opening', null);

        $this->item->refresh();
        $this->assertEquals(5.00, (float) $this->item->average_cost);
        $this->assertEquals(100, (float) $this->item->total_units_received);
        $this->assertEquals(150, (float) InventoryStock::where('inventory_item_id', $this->item->id)->first()->quantity);
    }

    public function test_add_stock_with_unit_cost_updates_weighted_average_cost(): void
    {
        // Existing: 100 units @ $5. Restocking 100 units @ $9.
        $this->service->addStock($this->item, $this->location, 100, 'opening', null, 9.00);

        $this->item->refresh();
        // (100*5 + 100*9) / 200 = 7.00
        $this->assertEquals(7.0000, (float) $this->item->average_cost);
        $this->assertEquals(200, (float) $this->item->total_units_received);
    }

    public function test_add_stock_with_zero_unit_cost_does_not_update_average_cost(): void
    {
        $this->service->addStock($this->item, $this->location, 20, 'opening', null, 0.0);

        $this->item->refresh();
        $this->assertEquals(5.00, (float) $this->item->average_cost);
        $this->assertEquals(100, (float) $this->item->total_units_received);
    }

    public function test_add_stock_with_unit_cost_on_adjustment_does_not_update_average_cost(): void
    {
        // A unit_cost shouldn't be offered/applied for adjustment movements —
        // guard the service layer against it regardless of what the caller sends.
        $this->service->addStock($this->item, $this->location, 20, 'adjustment', null, 12.00);

        $this->item->refresh();
        $this->assertEquals(5.00, (float) $this->item->average_cost);
        $this->assertEquals(100, (float) $this->item->total_units_received);
    }

    public function test_add_stock_with_unit_cost_on_return_does_not_update_average_cost(): void
    {
        $this->service->addStock($this->item, $this->location, 20, 'return', null, 12.00);

        $this->item->refresh();
        $this->assertEquals(5.00, (float) $this->item->average_cost);
        $this->assertEquals(100, (float) $this->item->total_units_received);
    }

    public function test_add_stock_logs_movement_and_increments_quantity(): void
    {
        $movement = $this->service->addStock($this->item, $this->location, 30, 'opening', 'Test restock', 6.00);

        $this->assertInstanceOf(InventoryMovement::class, $movement);
        $this->assertEquals('opening', $movement->movement_type);
        $this->assertEquals(30, (float) $movement->quantity);
        $this->assertEquals('Test restock', $movement->reason);
        $this->assertEquals(130, (float) InventoryStock::where('inventory_item_id', $this->item->id)->first()->quantity);
    }

    public function test_deduct_stock_consumes_the_oldest_lot_first(): void
    {
        // Fixture already has 100 @ $5 (older). Add a second, newer lot @ $9.
        $this->service->addStock($this->item, $this->location, 50, 'opening', null, 9.00);

        // Selling 120 should exhaust the $5 lot (100) before touching the $9 one (20 of 50 left).
        $this->service->deductStock($this->item, $this->location, 120);

        $oldLot = InventoryLot::where('unit_cost', 5.00)->first();
        $newLot = InventoryLot::where('unit_cost', 9.00)->first();
        $this->assertEquals(0, (float) $oldLot->remaining_quantity);
        $this->assertEquals(30, (float) $newLot->remaining_quantity);

        $this->item->refresh();
        $this->assertEquals(9.0000, (float) $this->item->average_cost);
    }

    public function test_adjust_stock_decrease_consumes_lots_and_recomputes_average(): void
    {
        // 100 @ $5 on hand. Physical count finds only 60 — 40 shrank away.
        $this->service->adjustStock($this->item, $this->location, 60, 'Physical count', 'adjustment');

        $lot = InventoryLot::where('product_id', $this->item->id)->first();
        $this->assertEquals(60, (float) $lot->remaining_quantity);

        $this->item->refresh();
        $this->assertEquals(5.0000, (float) $this->item->average_cost);
    }

    public function test_adjust_stock_increase_does_not_touch_lots_or_average_cost(): void
    {
        // Counting more than expected has no known cost behind it.
        $this->service->adjustStock($this->item, $this->location, 150, 'Found extra stock', 'adjustment');

        $lot = InventoryLot::where('product_id', $this->item->id)->first();
        $this->assertEquals(100, (float) $lot->remaining_quantity);

        $this->item->refresh();
        $this->assertEquals(5.0000, (float) $this->item->average_cost);
    }

    public function test_average_cost_holds_at_last_known_value_once_sold_out(): void
    {
        $this->service->deductStock($this->item, $this->location, 100);

        $this->item->refresh();
        $this->assertEquals(0, (float) InventoryStock::where('inventory_item_id', $this->item->id)->first()->quantity);
        // Sold out entirely, but the last real price paid stays as the answer
        // until new stock arrives — not reset to 0.
        $this->assertEquals(5.0000, (float) $this->item->average_cost);
    }
}

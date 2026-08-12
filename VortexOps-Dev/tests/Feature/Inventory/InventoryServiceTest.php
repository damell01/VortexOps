<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
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
}

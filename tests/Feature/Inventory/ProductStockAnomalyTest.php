<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockAnomalyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_item_with_stock_is_not_flagged(): void
    {
        $item = InventoryItem::create(['name' => 'Card A', 'unit_cost' => 10, 'is_active' => true]);
        $loc  = InventoryLocation::create(['name' => 'Main']);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id, 'quantity' => 5]);

        $this->assertFalse($item->hasBeenOutOfStockFor(14));
    }

    public function test_item_with_no_stock_and_no_movements_is_flagged(): void
    {
        $item = InventoryItem::create(['name' => 'Card B', 'unit_cost' => 10, 'is_active' => true]);

        $this->assertTrue($item->hasBeenOutOfStockFor(14));
        $this->assertNull($item->lastRestockedAt());
    }

    public function test_item_zero_stock_but_recently_restocked_is_not_flagged(): void
    {
        $item = InventoryItem::create(['name' => 'Card C', 'unit_cost' => 10, 'is_active' => true]);
        $loc  = InventoryLocation::create(['name' => 'Main']);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'to_location_id'    => $loc->id,
            'quantity'          => 5,
            'movement_type'     => 'opening',
        ]);

        // Sold through since — zero on hand now, but the restock was recent.
        $this->assertFalse($item->hasBeenOutOfStockFor(14));
        $this->assertNotNull($item->lastRestockedAt());
    }

    public function test_item_zero_stock_with_old_restock_is_flagged(): void
    {
        $item = InventoryItem::create(['name' => 'Card D', 'unit_cost' => 10, 'is_active' => true]);
        $loc  = InventoryLocation::create(['name' => 'Main']);

        $movement = InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'to_location_id'    => $loc->id,
            'quantity'          => 5,
            'movement_type'     => 'opening',
        ]);
        $movement->forceFill(['created_at' => now()->subDays(30)])->save();

        $this->assertTrue($item->hasBeenOutOfStockFor(14));
    }
}

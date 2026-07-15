<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Show;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WhatnotShowOrder;
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

    private function sale(InventoryItem $item, int $qty, ?string $when = null): void
    {
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => auth()->id()]);

        WhatnotShowOrder::create([
            'show_id' => $show->id, 'inventory_item_id' => $item->id, 'buyer_username' => 'b',
            'item_name' => $item->name, 'quantity' => $qty, 'total_price' => 10 * $qty, 'status' => 'completed',
            'show_date' => $when ?? now()->toDateString(),
        ]);
    }

    public function test_suggested_reorder_quantity_null_without_recent_sales(): void
    {
        $item = InventoryItem::create(['name' => 'No Velocity', 'unit_cost' => 10, 'is_active' => true]);

        $this->assertNull($item->suggestedReorderQuantity());
    }

    public function test_suggested_reorder_quantity_uses_default_lead_time_without_a_vendor(): void
    {
        $item = InventoryItem::create(['name' => 'Default Lead', 'unit_cost' => 10, 'is_active' => true]);
        $loc  = InventoryLocation::create(['name' => 'Main']);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id, 'quantity' => 5]);
        $this->sale($item, 30); // 1/day trailing velocity

        // (14 default lead + 7 safety) * 1/day = 21; on hand 5 → suggest 16.
        $this->assertEquals(16, $item->suggestedReorderQuantity());
    }

    public function test_suggested_reorder_quantity_uses_vendor_lead_time(): void
    {
        $vendor = Vendor::create(['name' => 'Fast Vendor', 'status' => 'active', 'lead_time_days' => 3]);
        $item = InventoryItem::create(['name' => 'Vendor Lead', 'unit_cost' => 10, 'is_active' => true, 'preferred_vendor_id' => $vendor->id]);
        $loc  = InventoryLocation::create(['name' => 'Main']);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id, 'quantity' => 5]);
        $this->sale($item, 30); // 1/day trailing velocity

        // (3 lead + 7 safety) * 1/day = 10; on hand 5 → suggest 5.
        $this->assertEquals(5, $item->suggestedReorderQuantity());
    }

    public function test_suggested_reorder_quantity_null_when_stock_covers_demand(): void
    {
        $item = InventoryItem::create(['name' => 'Overstocked', 'unit_cost' => 10, 'is_active' => true]);
        $loc  = InventoryLocation::create(['name' => 'Main']);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id, 'quantity' => 500]);
        $this->sale($item, 30);

        $this->assertNull($item->suggestedReorderQuantity());
    }
}

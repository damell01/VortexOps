<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\DashboardImproved;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's Recent Inventory Activity: a removal reads as a removal.
 * Movements store quantity unsigned, and the card summed it as written, so
 * a correction that took 2 off showed "+2.00" beside the log's "-2".
 */
class DashboardActivitySignTest extends TestCase
{
    use RefreshDatabase;

    private function activity(): array
    {
        $method = new \ReflectionMethod(DashboardImproved::class, 'recentInventoryActivity');

        return collect($method->invoke(new DashboardImproved))->keyBy('name')->all();
    }

    public function test_removals_additions_and_moves_carry_their_own_sign(): void
    {
        $main = InventoryLocation::create(['name' => 'Main Warehouse']);
        $shelf = InventoryLocation::create(['name' => 'Shelf']);
        $removed = InventoryItem::create(['name' => 'Corrected Box', 'is_active' => true]);
        $added = InventoryItem::create(['name' => 'Received Box', 'is_active' => true]);
        $moved = InventoryItem::create(['name' => 'Moved Box', 'is_active' => true]);

        InventoryMovement::create(['inventory_item_id' => $removed->id, 'from_location_id' => $main->id, 'quantity' => 2, 'quantity_before' => 5, 'quantity_after' => 3, 'movement_type' => 'adjustment', 'reason' => 'Inventory correction']);
        InventoryMovement::create(['inventory_item_id' => $added->id, 'to_location_id' => $main->id, 'quantity' => 4, 'movement_type' => 'opening']);
        InventoryMovement::create(['inventory_item_id' => $moved->id, 'from_location_id' => $main->id, 'to_location_id' => $shelf->id, 'quantity' => 3, 'movement_type' => 'transfer']);

        $rows = $this->activity();

        $this->assertSame(-2.0, $rows['Corrected Box']['qty']);
        $this->assertSame(4.0, $rows['Received Box']['qty']);
        $this->assertTrue($rows['Moved Box']['transfer']);
        $this->assertFalse($rows['Corrected Box']['transfer']);
    }

    public function test_a_deleted_product_leaves_the_dashboard_but_the_log_still_names_it(): void
    {
        $main = InventoryLocation::create(['name' => 'Main Warehouse']);
        $item = InventoryItem::create(['name' => 'Retired Box', 'is_active' => true]);
        InventoryMovement::create(['inventory_item_id' => $item->id, 'from_location_id' => $main->id, 'quantity' => 2, 'movement_type' => 'adjustment']);
        $item->delete();

        $this->assertArrayNotHasKey('Retired Box', $this->activity());
        $this->assertArrayNotHasKey('Inventory item', $this->activity());
        $this->assertNull(InventoryMovement::first()->item);
        $this->assertSame('Retired Box', InventoryMovement::first()->itemIncludingDeleted?->name);
    }
}

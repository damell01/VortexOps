<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnstockedProductsTiedToMainTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_with_no_stock_line_get_one_at_main(): void
    {
        $other = InventoryLocation::create(['name' => 'Other', 'type' => 'other', 'status' => 'active']);
        $main = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);
        $loose = InventoryItem::create(['name' => 'Loose', 'sku' => 'L-1', 'unit_cost' => 1, 'is_active' => true]);
        $stocked = InventoryItem::create(['name' => 'Stocked', 'sku' => 'S-1', 'unit_cost' => 1, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $stocked->id, 'inventory_location_id' => $other->id, 'quantity' => 3]);

        $migration = require database_path('migrations/2026_10_27_200000_tie_unstocked_products_to_main.php');
        $migration->up();
        $migration->up();

        $this->assertSame([$main->id], InventoryStock::where('inventory_item_id', $loose->id)->pluck('inventory_location_id')->all());
        $this->assertSame([$other->id], InventoryStock::where('inventory_item_id', $stocked->id)->pluck('inventory_location_id')->all());
    }
}

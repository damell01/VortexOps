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

    public function test_products_created_by_a_sheet_import_land_at_main(): void
    {
        $main = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);

        app(\App\Services\ProductSheetImporter::class)->apply([['name' => 'Fresh Box', 'sku' => 'FB-1', 'cost' => null, 'sale_price' => null, 'sold_as' => null, 'type' => null]]);
        $fresh = InventoryItem::where('name', 'Fresh Box')->firstOrFail();
        $this->assertSame([$main->id], InventoryStock::where('inventory_item_id', $fresh->id)->pluck('inventory_location_id')->all());

        $rows = [['status' => 'new', 'data' => ['name' => 'Second Box', 'sku' => 'SB-1'], 'sheet_row' => 2]];
        app(\App\Services\InventoryImportService::class)->import($rows);
        $second = InventoryItem::where('sku', 'SB-1')->firstOrFail();
        $this->assertSame([$main->id], InventoryStock::where('inventory_item_id', $second->id)->pluck('inventory_location_id')->all());
    }
}

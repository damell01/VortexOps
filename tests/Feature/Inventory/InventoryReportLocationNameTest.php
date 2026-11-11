<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryReport;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** A location saved as " Main Warehouse" (leading space) showed an all-zero report. */
class InventoryReportLocationNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stray_space_in_the_location_name_does_not_empty_the_report(): void
    {
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $main = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);
        DB::table('inventory_locations')->where('id', $main->id)->update(['name' => ' Main Warehouse']); // as stored on production
        $item = InventoryItem::create(['name' => 'Hobby Box', 'sku' => 'HB-1', 'unit_cost' => 10, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $main->id, 'quantity' => 4]);

        $page = new InventoryReport;
        $page->mount();
        $page->reportLocation = 'Main Warehouse'; // what the browser sends back after trimming the option
        $summary = $page->getViewerData()['summary'];

        $this->assertSame(1, $summary['in_stock_items']);
        $this->assertSame(4.0, (float) $summary['quantity']);
    }

    public function test_location_names_are_saved_without_surrounding_spaces(): void
    {
        $this->assertSame('Back Room', InventoryLocation::create(['name' => '  Back Room ', 'type' => 'storage', 'status' => 'active'])->fresh()->name);
    }
}

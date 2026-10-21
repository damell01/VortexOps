<?php

namespace Tests\Feature\Inventory;

use App\Exports\InventoryPriceListExport;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryPriceListExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }
    public function test_download_uses_fresh_catalog_including_added_zero_stock_and_inactive_items(): void
    {
        $existing = InventoryItem::create(['name' => 'Existing', 'unit_cost' => 8, 'average_cost' => 10, 'sale_price' => 20, 'is_active' => true]);
        $location = InventoryLocation::create(['name' => 'Main', 'status' => 'active']);
        InventoryStock::create(['inventory_item_id' => $existing->id, 'inventory_location_id' => $location->id, 'quantity' => 3]);
        $export = new InventoryPriceListExport();
        $added = InventoryItem::create(['name' => 'Added later', 'sku' => '=1+1', 'unit_cost' => 4, 'sale_price' => null, 'is_active' => false]);
        $deleted = InventoryItem::create(['name' => 'Deleted', 'is_active' => true]);
        $deleted->delete();
        $this->assertSame(['Added later', 'Existing'], $export->query()->get()->pluck('name')->all());
        $path = tempnam(sys_get_temp_dir(), 'inventory-export-');
        try {
            file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('Product', $sheet->getCell('B1')->getValue());
            $this->assertSame('Added later', $sheet->getCell('B2')->getValue());
            $this->assertSame('=1+1', $sheet->getCell('A2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A2')->getDataType());
            $this->assertEquals(0, $sheet->getCell('G2')->getValue());
            $this->assertSame('Inactive', $sheet->getCell('H2')->getValue());
            $this->assertEquals(20, $sheet->getCell('F3')->getValue());
            $this->assertEquals(3, $sheet->getCell('G3')->getValue());
            $this->assertNull($sheet->getCell('F2')->getValue());
        } finally { @unlink($path); }
    }
    public function test_admin_download_route_and_streamer_denial(): void
    {
        Excel::fake();
        $this->get(route('export.inventory-prices'))->assertOk();
        $streamer = User::factory()->create();
        $streamer->assignRole(Role::findOrCreate('streamer', 'web'));
        $this->actingAs($streamer);
        $this->get(route('export.inventory-prices'))->assertForbidden();
    }
}

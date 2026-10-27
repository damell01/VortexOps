<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Streamer;
use App\Models\User;
use App\Services\InventoryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AdminMainViewAndCountImportTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $main;
    private InventoryLocation $shelf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $this->main = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);
        $ty = Streamer::create(['name' => 'Ty & Luna', 'status' => 'active']);
        $this->shelf = InventoryLocation::create(['name' => 'Ty & Luna', 'type' => 'streamer_inventory', 'status' => 'active', 'streamer_id' => $ty->id]);
    }

    public function test_admins_see_main_and_not_streamer_shelves(): void
    {
        $box = InventoryItem::create(['name' => 'Hobby Box', 'sku' => 'HB-1', 'unit_cost' => 10, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $box->id, 'inventory_location_id' => $this->main->id, 'quantity' => 5]);
        InventoryStock::create(['inventory_item_id' => $box->id, 'inventory_location_id' => $this->shelf->id, 'quantity' => 40]);
        $theirsOnly = InventoryItem::create(['name' => 'Shelf Only', 'sku' => 'SO-1', 'unit_cost' => 1, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $theirsOnly->id, 'inventory_location_id' => $this->shelf->id, 'quantity' => 9]);

        $page = Livewire::test(ListInventoryItems::class);
        $this->assertSame([$this->main->id => 'Main Warehouse'], $page->instance()->inventoryLocationOptions());

        // Every product is still listed; quantities count Main only.
        $stats = collect($page->instance()->getStats())->keyBy('key');
        $this->assertSame('2', $stats['']['value']);
        $this->assertSame('5 units on hand', $stats['']['percentage']);
        $this->assertSame('1', $stats['out']['value']);
    }

    public function test_a_quantity_column_sets_counts_at_the_chosen_location(): void
    {
        $existing = InventoryItem::create(['name' => 'Ice Rider Calyrex Gift Box', 'sku' => 'VB-ICE', 'unit_cost' => 53, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $existing->id, 'inventory_location_id' => $this->main->id, 'quantity' => 0]);

        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray([
            ['Product Name', 'SKU', 'Qty'],
            ['Ice Rider Calyrex Gift Box', 'VB-ICE', 12],
            ['151 Gathering (Loose Packs)', 'VB-151', 30],
            ['No count yet', 'VB-NC', null],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'inv') . '.xlsx';
        (new Xlsx($sheet))->save($path);

        $service = app(InventoryImportService::class);
        $preview = $service->preview($path);
        $this->assertContains('quantity', $preview['headers']);

        // Updating existing details is off; the count still lands.
        $result = $service->import($preview['rows'], false, $this->main);
        @unlink($path);

        $this->assertSame(2, $result['stocked']);
        $this->assertSame(12.0, (float) InventoryStock::where('inventory_item_id', $existing->id)->where('inventory_location_id', $this->main->id)->value('quantity'));
        $new = InventoryItem::where('sku', 'VB-151')->firstOrFail();
        $this->assertSame(30.0, (float) InventoryStock::where('inventory_item_id', $new->id)->where('inventory_location_id', $this->main->id)->value('quantity'));
        $this->assertFalse(InventoryStock::where('inventory_item_id', InventoryItem::where('sku', 'VB-NC')->value('id'))->exists());
    }
}

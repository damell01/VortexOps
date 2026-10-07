<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryReport;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryReportOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs((User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh());
    }

    private function stockedItem(string $name = 'Chrome Box'): InventoryItem
    {
        $item = InventoryItem::create(['name' => $name, 'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true]);
        $location = InventoryLocation::firstOrCreate(['name' => 'Report Warehouse'], ['type' => 'main_storage', 'status' => 'active']);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 5]);
        return $item;
    }

    public function test_movements_distinguish_sales_transfers_and_corrections(): void
    {
        $item = $this->stockedItem();
        $main = InventoryLocation::first();
        $shelf = InventoryLocation::create(['name' => 'Report Shelf', 'type' => 'main_storage', 'status' => 'active']);
        foreach ([['opening', null, $main->id, 10], ['show_sale', $main->id, null, 2],
            ['adjustment', $main->id, null, 1], ['transfer', $main->id, $shelf->id, 4]] as [$type, $from, $to, $quantity]) {
            InventoryMovement::create(['inventory_item_id' => $item->id, 'movement_type' => $type,
                'from_location_id' => $from, 'to_location_id' => $to, 'quantity' => $quantity, 'unit_cost' => 10]);
        }
        $page = Livewire::test(InventoryReport::class)->assertOk();
        $summary = $page->instance()->getMovementSummary()['current'];
        $this->assertSame(10.0, $summary['received']);
        $this->assertSame(2.0, $summary['removed']);
        $this->assertSame(-1.0, $summary['adjustments']);
        $this->assertSame(4.0, $summary['transfers']);
        $this->assertSame(7.0, $summary['net']);
        $this->assertSame(2.0, $page->instance()->getTopMovingRows()[0]['moved_units']);
        $page->call('setTab', 'recent')->assertOk()->assertSee('Internal transfers');
        $page->call('setTab', 'moving')->assertOk()->assertSee('Units Sold');
    }

    public function test_location_filter_values_only_stock_at_that_location(): void
    {
        $item = $this->stockedItem();
        $other = InventoryLocation::create(['name' => 'Other Report Shelf', 'type' => 'main_storage', 'status' => 'active']);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $other->id, 'quantity' => 20]);
        $page = Livewire::test(InventoryReport::class)->set('reportLocation', 'Report Warehouse')->assertOk();
        $this->assertSame(5.0, $page->instance()->getViewerData()['summary']['quantity']);
        $this->assertSame(50.0, $page->instance()->getViewerData()['summary']['value']);
    }

    public function test_pagination_and_custom_dates_render_and_preserve_filtering(): void
    {
        foreach (range(1, 26) as $number) $this->stockedItem(sprintf('Report item %02d', $number));
        $page = Livewire::test(InventoryReport::class)->assertOk()->assertSee('Page 1 of 2');
        $page->call('changeReportPage', 2)->assertOk()->assertSee('Page 2 of 2');
        $page->set('reportSearch', 'Report item 26')->assertOk()->assertSee('Page 1 of 1');
        $this->assertCount(1, $page->instance()->getReportItems());
        $page->set('reportStart', now()->subDays(6)->toDateString())
            ->set('reportEnd', now()->toDateString())->set('reportDays', 'custom')->assertOk();
        $this->assertSame(7, $page->instance()->periodDays());
        $page->call('setTab', 'aging')->assertOk();
        $page->call('exportCsv')->assertFileDownloaded();
    }
}

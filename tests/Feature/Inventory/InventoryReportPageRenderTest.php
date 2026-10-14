<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryReport;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventorySnapshot;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryReportPageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        $main = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);
        $gem = InventoryItem::create(['name' => 'Gem 3 Box', 'sku' => 'GEM-3', 'category' => 'Boxes', 'unit_cost' => 80, 'average_cost' => 80, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $gem->id, 'inventory_location_id' => $main->id, 'quantity' => 10]);
        $loose = InventoryItem::create(['name' => 'Loose Packs', 'sku' => 'LP-1', 'unit_cost' => 4, 'average_cost' => 4, 'is_active' => true, 'reorder_level' => 50]);
        InventoryStock::create(['inventory_item_id' => $loose->id, 'inventory_location_id' => $main->id, 'quantity' => 5]);
        InventoryItem::create(['name' => 'Sold Out Tin', 'sku' => 'TIN-0', 'category' => 'Tins', 'unit_cost' => 12, 'average_cost' => 12, 'is_active' => true]);
    }

    public function test_the_redesigned_report_renders_its_sections_and_real_figures(): void
    {
        $page = Livewire::test(InventoryReport::class)->assertOk()
            ->assertSee('Inventory Report &amp; Analytics', false)
            ->assertSee('Stock health, movement and money tied up in inventory.')
            ->assertSee('Stock values reflect current inventory; activity uses selected period.')
            ->assertSee(['Inventory Value', '$820.00', 'Total Items', '2 in stock', 'Units On Hand', 'Avg Cost per Unit', '$54.67'])
            ->assertSee(['Low Stock', 'Out of Stock', 'Uncategorized Items'])
            ->assertSee(['Inventory Value Trend', 'No snapshots in this period yet'])
            ->assertSee(['Idle Inventory', '30 – 59 days', '90+ days', 'View Idle Inventory'])
            ->assertSee(['Current Inventory', 'Valuation', 'Locations', 'Recent Activity', 'Top Value', 'Top Sold', 'Idle'])
            ->assertSee(['Gem 3 Box', 'GEM-3', 'Main Warehouse', 'Showing 1–3 of 3 items', 'Page 1 of 1']);

        $summary = $page->instance()->getViewerData()['summary'];
        $this->assertSame(1, $summary['low_stock']);
        $this->assertSame(1, $summary['out_stock']);
        $this->assertSame(1, $summary['uncategorized']);
    }

    public function test_every_tab_renders(): void
    {
        $page = Livewire::test(InventoryReport::class);
        foreach (array_keys(InventoryReport::TABS) as $tab) {
            $page->call('setTab', $tab)->assertOk()->assertSet('activeTab', $tab);
        }
        $page->call('setTab', 'valuation')->assertSee(['Boxes', '$800.00', 'Share of value']);
        $page->call('setTab', 'locations')->assertSee(['Main Warehouse', '$820.00']);
        $page->call('setTab', 'out-stock')->assertSee('Sold Out Tin')->assertDontSee('Gem 3 Box');
        $page->call('setTab', 'locations')->call('exportCsv')->assertFileDownloaded();
    }

    public function test_scope_sort_and_uncategorized_shortcut(): void
    {
        $page = Livewire::test(InventoryReport::class)->set('reportStock', 'in');
        $this->assertCount(2, $page->instance()->getReportItems());

        $page->set('reportStock', '')->call('sortBy', 'name');
        $this->assertSame('Gem 3 Box', $page->instance()->getReportItems()->first()['name']);

        $page->call('showUncategorized')->assertSet('reportCategory', 'Uncategorized');
        $this->assertSame(['Loose Packs'], $page->instance()->getReportItems()->pluck('name')->all());
    }

    public function test_the_trend_draws_from_stored_snapshots(): void
    {
        InventorySnapshot::generateCurrent();
        Livewire::test(InventoryReport::class)->assertOk()
            ->assertDontSee('No snapshots in this period yet')
            ->assertSeeHtml('vxr-chart');
    }
}

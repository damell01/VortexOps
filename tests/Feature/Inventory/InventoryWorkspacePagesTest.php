<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryActivity;
use App\Filament\Pages\InventoryHealth;
use App\Filament\Pages\InventoryOverview;
use App\Filament\Pages\StockStatus;
use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Filament\Resources\InventoryItemResource\Pages\ViewInventoryItem;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The inventory workspace screens: Overview (with Tools and Reports), Stock
 * Status, Health, Recent Activity, and the Add Stock sheet.
 */
class InventoryWorkspacePagesTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $main;

    private InventoryLocation $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        $this->main = InventoryLocation::create(['name' => 'A Main Warehouse', 'status' => 'active']);
        $this->second = InventoryLocation::create(['name' => 'B Secondary', 'status' => 'active']);
    }

    private function item(string $name, float $qty, ?float $reorder = 3, string $category = 'Pokémon'): InventoryItem
    {
        $item = InventoryItem::create(['name' => $name, 'category' => $category, 'average_cost' => 10, 'reorder_level' => $reorder, 'is_active' => true, 'is_container' => false]);
        if ($qty > 0) app(InventoryService::class)->addStock($item, $this->main, $qty, 'opening', 'Received', 10.0);

        return $item;
    }

    public function test_overview_tabs_render_with_the_stock_counts(): void
    {
        $this->item('Plenty', 20);
        $this->item('Running Low', 2);
        $this->item('Gone', 0);

        $page = Livewire::test(InventoryOverview::class)->assertOk()->assertSee('Inventory Value Trend');
        $snap = $page->instance()->inventorySnapshot;
        $this->assertSame([1, 1, 1], [$snap['in'], $snap['low'], $snap['out']]);
        $this->assertSame(22.0, $snap['units']);

        $page->call('setTab', 'tools')->assertSee('Receive Inventory')->assertSee('Transfer Stock');
        $page->call('setTab', 'reports')->assertSee('Inventory Report')->assertSee('Plenty');
    }

    public function test_the_report_table_shows_real_stock_not_zero(): void
    {
        $this->item('Booster Box', 12);

        $rows = Livewire::test(InventoryOverview::class)->instance()->reportRows()['rows'];

        $this->assertSame(12.0, $rows->firstWhere('name', 'Booster Box')['qty']);
    }

    public function test_the_report_filters_by_location(): void
    {
        $here = $this->item('Here', 5);
        $there = $this->item('There', 0);
        app(InventoryService::class)->addStock($there, $this->second, 7, 'opening');

        $rows = Livewire::test(InventoryOverview::class)->set('reportLocation', (string) $this->second->id)->instance()->reportRows()['rows'];

        $this->assertSame(['There'], $rows->pluck('name')->all());
        $this->assertSame(7.0, $rows->first()['qty']);
    }

    public function test_stock_status_counts_and_filters(): void
    {
        $this->item('Plenty', 20);
        $this->item('Running Low', 2);
        $this->item('Gone', 0);

        $page = Livewire::test(StockStatus::class)->assertOk();
        $this->assertSame(['all' => 3, 'in' => 1, 'low' => 1, 'out' => 1], $page->instance()->data()['counts']);

        $page->call('setTab', 'low')->assertSee('Running Low')->assertDontSee('Plenty');
        $this->assertSame('Gone', $page->call('setTab', 'all')->instance()->data()['rows']->first()['name'], 'status sort puts out of stock first');
    }

    public function test_health_renders_every_tab(): void
    {
        $this->item('Plenty', 20, 3, 'Sports Cards');

        Livewire::test(InventoryHealth::class)->assertOk()->assertSee('Health Breakdown')
            ->call('setTab', 'categories')->assertSee('Sports Cards')
            ->call('setTab', 'vendors')->assertSee('No vendor set');
    }

    public function test_activity_lists_receipts_and_transfers(): void
    {
        $item = $this->item('Moving Box', 10);
        app(InventoryService::class)->transferStock($item, $this->main, $this->second, 4, 'Restock B');

        $page = Livewire::test(InventoryActivity::class)->assertOk()->assertSee('Moving Box')->assertSee('Transferred')->assertSee('Received');
        $page->call('setTab', 'transferred')->assertSee('A Main Warehouse → B Secondary');
        $this->assertSame(['transferred'], $page->instance()->entries()->pluck('kind')->unique()->values()->all());
    }

    public function test_add_stock_sheet_books_quantity_cost_and_received_date(): void
    {
        $item = $this->item('Backdated', 0);

        Livewire::test(ListInventoryItems::class)
            ->mountAction('add_stock', ['product' => $item->id])
            ->setActionData(['quantity' => 3, 'unit_cost' => 12.5, 'location_id' => $this->main->id, 'received_at' => now()->subDays(40)->toDateString(), 'movement_type' => 'opening'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(3.0, (float) $item->stock()->sum('quantity'));
        $movement = InventoryMovement::where('inventory_item_id', $item->id)->latest('id')->first();
        $this->assertTrue($movement->created_at->isSameDay(now()->subDays(40)), 'the receipt kept the day it arrived');
        $lot = InventoryLot::where('product_id', $item->id)->first();
        $this->assertSame(12.5, (float) $lot->unit_cost);
        $this->assertTrue($lot->received_at->isSameDay(now()->subDays(40)));
    }

    public function test_the_item_page_shows_the_new_overview(): void
    {
        $item = $this->item('Detail Box', 4);
        $item->update(['sale_price' => 20]);

        Livewire::test(ViewInventoryItem::class, ['record' => $item])->assertOk()
            ->assertSee('Item Details')->assertSee('Potential Margin')->assertSee('Price &amp; Cost', false);
    }
}

<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryLocationResource\Pages\ListInventoryLocations;
use App\Filament\Resources\StreamerResource\Pages\ListStreamers;
use App\Filament\Resources\StreamerResource\Pages\ViewStreamer;
use App\Models\Business;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Streamer;
use App\Models\User;
use App\Services\StreamerInventorySetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** One button sets up a streamer's own inventory and stocks it. */
class StreamerInventorySetupTest extends TestCase
{
    use RefreshDatabase;

    private Streamer $jay;
    private InventoryLocation $main;
    private InventoryItem $box;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        $estill = Business::where('short_name', 'Estill')->firstOrFail();
        $this->jay = Streamer::create(['name' => 'Jay', 'status' => 'active', 'member_type' => 'streamer', 'business_id' => $estill->id]);
        $this->main = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        $this->box = InventoryItem::create(['name' => 'Hobby Box', 'sku' => 'HB-1', 'unit_cost' => 10, 'average_cost' => 10, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $this->box->id, 'inventory_location_id' => $this->main->id, 'quantity' => 10]);
    }

    private function qty(InventoryLocation $at): float
    {
        return (float) InventoryStock::where('inventory_item_id', $this->box->id)->where('inventory_location_id', $at->id)->value('quantity');
    }

    public function test_one_button_makes_their_location_and_moves_stock_in(): void
    {
        Livewire::test(ViewStreamer::class, ['record' => $this->jay->getKey()])
            ->callAction('setUpInventory', [
                'name' => "Jay's Inventory",
                'from_location_id' => $this->main->id,
                'lines' => [['inventory_item_id' => $this->box->id, 'quantity' => 4]],
            ])
            ->assertHasNoActionErrors();

        $own = app(StreamerInventorySetup::class)->ownLocation($this->jay);
        $this->assertNotNull($own);
        $this->assertSame("Jay's Inventory", $own->name);
        $this->assertSame($this->jay->business_id, $own->business_id);
        $this->assertEqualsWithDelta(4, $this->qty($own), 0.001);
        $this->assertEqualsWithDelta(6, $this->qty($this->main), 0.001);
    }

    public function test_it_can_set_up_an_empty_location_and_add_stock_later_without_a_second_one(): void
    {
        $page = Livewire::test(ViewStreamer::class, ['record' => $this->jay->getKey()]);
        $page->callAction('setUpInventory', ['name' => "Jay's Inventory", 'from_location_id' => null, 'lines' => []])->assertHasNoActionErrors();
        $page->callAction('setUpInventory', ['from_location_id' => $this->main->id, 'lines' => [['inventory_item_id' => $this->box->id, 'quantity' => 2]]])->assertHasNoActionErrors();

        $this->assertSame(1, InventoryLocation::where('streamer_id', $this->jay->id)->count());
        $this->assertEqualsWithDelta(2, $this->qty(app(StreamerInventorySetup::class)->ownLocation($this->jay)), 0.001);
    }

    public function test_more_than_is_on_hand_is_reported_and_nothing_breaks(): void
    {
        [$own] = app(StreamerInventorySetup::class)->ensure($this->jay);

        $result = app(StreamerInventorySetup::class)->stock($this->jay, $own, $this->main, [['inventory_item_id' => $this->box->id, 'quantity' => 50]]);

        $this->assertSame(0, $result['moved']);
        $this->assertCount(1, $result['problems']);
        $this->assertEqualsWithDelta(10, $this->qty($this->main), 0.001);
    }

    public function test_the_list_sets_up_everyone_selected_in_one_go(): void
    {
        $sam = Streamer::create(['name' => 'Sam', 'status' => 'active', 'member_type' => 'streamer']);
        app(StreamerInventorySetup::class)->ensure($this->jay);

        Livewire::test(ListStreamers::class)->callTableBulkAction('setUpInventory', [$this->jay, $sam]);

        $this->assertSame(1, InventoryLocation::where('streamer_id', $this->jay->id)->count());
        $this->assertSame("Sam's Inventory", InventoryLocation::where('streamer_id', $sam->id)->value('name'));
    }

    public function test_the_locations_shortcut_does_not_make_a_second_one(): void
    {
        app(StreamerInventorySetup::class)->ensure($this->jay);

        Livewire::test(ListInventoryLocations::class)->callAction('streamerLocation', ['streamer_id' => $this->jay->id, 'name' => 'Another']);

        $this->assertSame(1, InventoryLocation::where('streamer_id', $this->jay->id)->count());
    }
}

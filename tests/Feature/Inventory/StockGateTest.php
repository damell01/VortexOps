<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Filament\Resources\InventoryItemResource\Pages\ManageStock;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Streamer;
use App\Models\User;
use App\Support\StockGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Main and shared locations are run by the business; a streamer's own
 * location by that streamer. Neither changes the other's stock.
 */
class StockGateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $jayUser;
    private InventoryLocation $main;
    private InventoryLocation $jays;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->jayUser = User::factory()->create();
        $jay = Streamer::create(['name' => 'Jay', 'status' => 'active', 'user_id' => $this->jayUser->id]);
        $this->main = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
        $this->jays = InventoryLocation::create(['name' => "Jay's Shelf", 'type' => 'streamer_inventory', 'status' => 'active', 'streamer_id' => $jay->id]);
        $this->item = InventoryItem::create(['name' => 'Hobby Box', 'sku' => 'HB-1', 'unit_cost' => 10, 'is_active' => true]);
        InventoryStock::create(['inventory_item_id' => $this->item->id, 'inventory_location_id' => $this->main->id, 'quantity' => 10]);
        InventoryStock::create(['inventory_item_id' => $this->item->id, 'inventory_location_id' => $this->jays->id, 'quantity' => 4]);
    }

    public function test_each_side_changes_only_its_own_locations(): void
    {
        $this->assertTrue(StockGate::canChange($this->owner, $this->main));
        $this->assertFalse(StockGate::canChange($this->owner, $this->jays));
        $this->assertFalse(StockGate::canChange($this->jayUser, $this->jays));
        $this->assertFalse(StockGate::canChange($this->jayUser, $this->main));
        $this->assertSame([$this->main->id], StockGate::changeableIds($this->owner));
        $this->assertSame([], StockGate::changeableIds($this->jayUser));
    }

    public function test_the_owner_cannot_add_stock_at_a_streamers_shelf(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(ListInventoryItems::class)
            ->callAction('add_stock', ['location_id' => $this->jays->id, 'quantity' => 5, 'movement_type' => 'opening'], ['product' => $this->item->id]);

        $this->assertSame(4.0, (float) InventoryStock::where('inventory_location_id', $this->jays->id)->value('quantity'));

        Livewire::test(ListInventoryItems::class)
            ->callAction('add_stock', ['location_id' => $this->main->id, 'quantity' => 5, 'movement_type' => 'opening'], ['product' => $this->item->id])
            ->assertHasNoActionErrors();
        $this->assertSame(15.0, (float) InventoryStock::where('inventory_location_id', $this->main->id)->value('quantity'));
    }

    public function test_manage_stock_corrects_only_owned_locations_but_can_send_anywhere_visible(): void
    {
        $this->actingAs($this->owner);

        $page = Livewire::test(ManageStock::class, ['record' => $this->item->id]);
        $this->assertSame([$this->main->id => 'Main Storage'], $page->instance()->sourceOptions);
        $this->assertArrayHasKey($this->jays->id, $page->set('operation', 'transfer')->instance()->destinationOptions);

        // Correcting Jay's shelf is refused.
        $page->set('operation', 'adjust')->set('fromLocationId', $this->jays->id)->set('newQuantity', '0')->call('submit');
        $this->assertSame(4.0, (float) InventoryStock::where('inventory_location_id', $this->jays->id)->value('quantity'));

        // Sending Main stock to Jay is the owner's call.
        $page->set('operation', 'transfer')->set('fromLocationId', $this->main->id)->set('toLocationId', $this->jays->id)->set('moveQuantity', '3')->call('submit');
        $this->assertSame(7.0, (float) InventoryStock::where('inventory_location_id', $this->main->id)->value('quantity'));
        $this->assertSame(7.0, (float) InventoryStock::where('inventory_location_id', $this->jays->id)->value('quantity'));
    }

    public function test_a_streamer_cannot_correct_main(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('streamer', 'web');
        $this->jayUser->assignRole('streamer');
        $this->actingAs($this->jayUser);

        $page = Livewire::test(ManageStock::class, ['record' => $this->item->id]);
        $this->assertSame([], $page->instance()->sourceOptions);
        $page->set('operation', 'adjust')->set('fromLocationId', $this->main->id)->set('newQuantity', '0')->call('submit');
        $this->assertSame(10.0, (float) InventoryStock::where('inventory_location_id', $this->main->id)->value('quantity'));
    }

    public function test_a_location_filter_says_where_the_other_products_are(): void
    {
        $this->actingAs($this->owner);
        $other = InventoryLocation::create(['name' => 'Receiving Bay', 'type' => 'other', 'status' => 'active']);
        foreach (range(1, 3) as $i) {
            $p = InventoryItem::create(['name' => "Elsewhere $i", 'sku' => "EL-$i", 'unit_cost' => 1, 'is_active' => true]);
            InventoryStock::create(['inventory_item_id' => $p->id, 'inventory_location_id' => $other->id, 'quantity' => 2]);
        }
        InventoryItem::create(['name' => 'Never received', 'sku' => 'NR-1', 'unit_cost' => 1, 'is_active' => true]);

        $page = Livewire::test(ListInventoryItems::class)->set('inventoryLocation', (string) $this->main->id);
        $this->assertSame(['here' => 1, 'units' => 10.0, 'total' => 5, 'elsewhere' => ['Receiving Bay' => 3], 'unstocked' => 1], $page->instance()->locationCoverage());
        $page->assertSee('Main Storage: 1 product, 10 units on hand.')->assertSee('3 stocked at Receiving Bay')->assertSee('1 with no stock anywhere');
        $this->assertSame('10 units on hand', $page->instance()->getStats()[0]['percentage']);
    }

    public function test_a_streamer_without_a_location_gets_no_stock_tools(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('streamer', 'web');
        $loner = User::factory()->create();
        $loner->assignRole('streamer');
        Streamer::create(['name' => 'Loner', 'status' => 'active', 'user_id' => $loner->id]);
        $this->actingAs($loner);

        $this->assertFalse(StockGate::canChangeAny());
        $this->assertFalse(\App\Filament\Pages\QuickAddStock::canAccess());
        $this->assertFalse(\App\Filament\Pages\StockTransfer::canAccess());
        $this->assertFalse(\App\Filament\Pages\InventoryScanner::canAccess());
        $this->assertFalse(\App\Filament\Pages\ImportInventorySheet::canAccess());
        $labels = array_column(\App\Support\InventoryNavigation::linksForPage([\App\Filament\Resources\InventoryItemResource::class]), 'label');
        $this->assertNotContains('Quick add stock', $labels);

        // Owning a shelf does not grant a streamer write access.
        \Spatie\Permission\Models\Role::findOrCreate('streamer', 'web');
        $this->jayUser->assignRole('streamer');
        $this->actingAs($this->jayUser);
        $this->assertFalse(StockGate::canChangeAny());
    }
}

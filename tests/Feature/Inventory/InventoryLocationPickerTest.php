<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryLocationPickerTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $main;

    private InventoryLocation $second;

    private InventoryItem $shared;

    private InventoryItem $elsewhere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        $this->main = InventoryLocation::create(['name' => 'Picker Warehouse', 'type' => 'main_storage', 'status' => 'active']);
        $this->second = InventoryLocation::create(['name' => 'Picker Shelf', 'type' => 'streamer_inventory', 'status' => 'active']);
        $this->shared = InventoryItem::create(['name' => 'Shared picker product', 'sku' => 'PICK-SHARED', 'is_active' => true, 'reorder_level' => 4]);
        $this->elsewhere = InventoryItem::create(['name' => 'Shelf only product', 'sku' => 'PICK-SHELF', 'is_active' => true]);
        foreach ([[$this->shared, $this->main, 3], [$this->shared, $this->second, 90], [$this->elsewhere, $this->second, 12]] as [$item, $location, $quantity]) {
            InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => $quantity]);
        }
    }

    public function test_main_list_scopes_products_quantities_location_names_and_stats_in_both_views(): void
    {
        $page = Livewire::test(ListInventoryItems::class)->assertSee('Inventory location')
            ->set('inventoryLocation', (string) $this->main->id)->assertSee('Shared picker product')->assertDontSee('Shelf only product');
        $item = $page->instance()->catalogItems->sole();
        $this->assertEquals(3, $item->stock_sum_quantity);
        $this->assertSame([$this->main->id], $item->stock->pluck('inventory_location_id')->all());
        $this->assertSame('1', $page->instance()->getStats()[2]['value']); // Low here, despite 90 elsewhere.
        $page->call('filterStock', 'low');
        $this->assertSame([$this->shared->id], $page->instance()->catalogItems->modelKeys());
        $page->call('filterStock', null);
        $page->call('setViewMode', 'table')->call('loadTable')->assertCanSeeTableRecords([$this->shared])->assertCanNotSeeTableRecords([$this->elsewhere]);
        $this->assertEquals(3, $page->instance()->getTableRecords()->first()->stock_sum_quantity);
        $page->set('inventoryLocation', '')->assertCanSeeTableRecords([$this->shared, $this->elsewhere]);
    }

    public function test_location_change_clears_bulk_selection_and_resets_loaded_cards(): void
    {
        $page = Livewire::test(ListInventoryItems::class)->set('selectedItems', [(string) $this->shared->id])
            ->set('selectAllMatching', true)->set('catalogVisible', 120)
            ->set('selectedTableRecords', [(string) $this->shared->id])->set('isTrackingDeselectedTableRecords', true)
            ->set('inventoryLocation', (string) $this->main->id)
            ->assertSet('selectedItems', [])->assertSet('selectAllMatching', false)->assertSet('catalogVisible', 40)
            ->assertSet('selectedTableRecords', [])->assertSet('isTrackingDeselectedTableRecords', false)
            ->assertDispatched('deselectAllTableRecords');
        $page->set('catalogSearch', 'Shelf only')->assertSee('No matching inventory');
    }

    public function test_zero_stock_rows_remain_visible_at_selected_location(): void
    {
        InventoryStock::where('inventory_location_id', $this->main->id)->update(['quantity' => 0]);
        $page = Livewire::test(ListInventoryItems::class)->set('inventoryLocation', (string) $this->main->id);
        $this->assertSame([$this->shared->id], $page->instance()->catalogItems->modelKeys());
        $this->assertSame('1', $page->instance()->getStats()[3]['value']);
    }

    public function test_picker_cannot_expand_streamer_visibility_or_accept_an_unknown_location(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('streamer', 'web'));
        Streamer::create(['name' => 'Viewer', 'user_id' => $user->id, 'status' => 'active']);
        $this->actingAs($user);
        $page = Livewire::test(ListInventoryItems::class);
        $this->assertArrayNotHasKey($this->second->id, $page->instance()->inventoryLocationOptions());
        $page->set('inventoryLocation', (string) $this->second->id);
        $this->assertCount(0, $page->instance()->catalogItems);
        $page->set('inventoryLocation', '99999999');
        $this->assertCount(0, $page->instance()->catalogItems);
    }

    public function test_admin_can_select_inactive_locations_from_a_direct_link(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($user);
        $this->second->update(['status' => 'inactive']);
        $page = Livewire::withQueryParams(['location' => (string) $this->second->id])->test(ListInventoryItems::class)
            ->assertSee('Picker Shelf (inactive)');
        $this->assertCount(2, $page->instance()->catalogItems);
        $this->assertEquals(90, $page->instance()->catalogItems->firstWhere('id', $this->shared->id)->stock_sum_quantity);
        $this->assertSame(3, InventoryStock::count());
    }
}

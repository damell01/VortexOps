<?php
namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryScanner;
use App\Filament\Resources\InventoryItemResource;
use App\Models\{InventoryItem, InventoryLocation, InventoryMovement, Streamer, User};
use App\Support\InventoryVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryMovementVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_admin_can_read_movements_and_use_the_scanned_items_stock_link(): void
    {
        $this->enableAdminModules();
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($user);
        $location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $item = InventoryItem::create(['name' => 'Scanned stock fixture', 'sku' => 'STOCK-LINK-1']);
        $movement = InventoryMovement::create(['inventory_item_id' => $item->id, 'to_location_id' => $location->id, 'quantity' => 2, 'movement_type' => 'opening']);
        $this->assertNull(InventoryVisibility::locationIdsFor($user));
        $this->assertTrue(InventoryMovement::whereKey($movement->id)->exists());
        Livewire::test(InventoryScanner::class)->set('scanInput', 'STOCK-LINK-1')->call('submitScan')
            ->assertSee('Move / Correct Stock')->assertSee(InventoryItemResource::getUrl('stock', ['record' => $item]), false);
    }

    public function test_streamers_are_not_offered_the_hidden_add_stock_action(): void
    {
        $this->enableAdminModules();
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('streamer', 'web'));
        $this->actingAs($user);
        Livewire::test(\App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems::class)
            ->assertActionDoesNotExist('add_stock');
    }

    public function test_streamer_movements_still_exclude_other_streamers_private_locations(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('streamer', 'web'));
        $this->actingAs($user);
        $main = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $other = Streamer::create(['name' => 'Different streamer', 'status' => 'active']);
        $private = InventoryLocation::create(['name' => 'Private shelf', 'type' => 'streamer_inventory', 'streamer_id' => $other->id, 'status' => 'active']);
        $item = InventoryItem::create(['name' => 'Visibility fixture', 'sku' => 'MOVE-VISIBILITY']);
        $visible = InventoryMovement::create(['inventory_item_id' => $item->id, 'to_location_id' => $main->id, 'quantity' => 2, 'movement_type' => 'opening']);
        $hidden = InventoryMovement::create(['inventory_item_id' => $item->id, 'to_location_id' => $private->id, 'quantity' => 2, 'movement_type' => 'opening']);
        $this->assertTrue(InventoryMovement::whereKey($visible)->exists());
        $this->assertFalse(InventoryMovement::whereKey($hidden)->exists());
    }
}

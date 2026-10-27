<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryScanner;
use App\Filament\Pages\QuickAddStock;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Filament\Resources\InventoryLocationResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\InventoryMovement;
use App\Models\Streamer;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\InventoryVisibility;
use App\Support\StockGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryAccessGatingTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $main;
    private InventoryLocation $other;
    private InventoryLocation $back;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('streamer', 'web');
        $this->main = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);
        $other = Streamer::create(['name' => 'Other Person', 'status' => 'active']);
        $this->other = InventoryLocation::create(['name' => 'Other Private Shelf', 'type' => 'streamer_inventory', 'streamer_id' => $other->id, 'status' => 'active']);
        $this->back = InventoryLocation::create(['name' => 'Unassigned Back Room', 'type' => 'other', 'status' => 'active']);
        $this->item = InventoryItem::create(['name' => 'Gated Box', 'sku' => 'GATED-BOX', 'is_active' => true]);
        foreach ([$this->main->id => 10, $this->other->id => 70, $this->back->id => 30] as $id => $quantity) {
            InventoryStock::updateOrCreate(['inventory_item_id' => $this->item->id, 'inventory_location_id' => $id], ['quantity' => $quantity]);
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
        return $user;
    }

    public function test_admin_without_an_assignment_sees_only_main_in_queries_selectors_and_totals(): void
    {
        $user = $this->admin();
        $this->assertSame([$this->main->id], InventoryVisibility::locationIdsFor($user));
        $this->assertSame([$this->main->id], InventoryLocation::visibleTo()->pluck('id')->all());
        $this->assertSame([$this->main->id], array_keys(InventoryLocation::activeOptions()));
        $this->assertSame([$this->main->id], InventoryLocationResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame(10.0, $this->item->fresh()->totalQuantity());
        $this->assertFalse(StockGate::canChange($user, $this->back));
        $this->assertFalse(StockGate::canChange($user, $this->other));
        $this->get(InventoryLocationResource::getUrl('view', ['record' => $this->other]))->assertNotFound();
    }

    public function test_staff_admin_with_an_owned_location_sees_main_and_their_own_only(): void
    {
        $user = $this->admin();
        $profile = Streamer::create(['name' => 'Admin Staff', 'member_type' => 'fulfillment', 'user_id' => $user->id, 'status' => 'active']);
        $mine = InventoryLocation::create(['name' => 'My Assigned Shelf', 'type' => 'other', 'streamer_id' => $profile->id, 'status' => 'active']);
        $user = $user->fresh();$this->actingAs($user);
        $ids = InventoryVisibility::locationIdsFor($user);sort($ids);
        $expected = [$this->main->id, $mine->id];sort($expected);
        $this->assertSame($expected, $ids);
        $this->assertFalse(InventoryVisibility::isReadOnlyStreamer($user));
        $this->assertTrue(StockGate::canChange($user, $mine));
    }

    public function test_owner_exception_and_option_cache_do_not_leak_into_the_next_user(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($owner);
        $this->assertNull(InventoryVisibility::locationIdsFor($owner));
        $this->assertCount(3, InventoryLocation::activeOptions());
        $this->assertSame(110.0, $this->item->fresh()->totalQuantity());
        $this->admin();
        $this->assertSame([$this->main->id], array_keys(InventoryLocation::activeOptions()));
        $this->assertSame(10.0, $this->item->fresh()->totalQuantity());
    }

    public function test_streamer_with_a_shelf_is_read_only_and_has_no_card_action_buttons(): void
    {
        $user = User::factory()->create();$user->assignRole('streamer');
        $profile = Streamer::create(['name' => 'Read Only Streamer', 'user_id' => $user->id, 'status' => 'active']);
        $mine = InventoryLocation::create(['name' => 'My Streamer Shelf', 'type' => 'streamer_inventory', 'streamer_id' => $profile->id, 'status' => 'active']);
        $this->actingAs($user->fresh());
        $this->assertContains($mine->id, InventoryVisibility::locationIdsFor(auth()->user()));
        $this->assertFalse(StockGate::canChange(auth()->user(), $mine));
        $this->assertFalse(StockGate::canChangeAny());
        $this->assertFalse(InventoryItemResource::canCreate());
        $this->assertFalse(InventoryItemResource::canEdit($this->item));
        $this->assertFalse(InventoryItemResource::canManageStock());
        $this->assertFalse(InventoryScanner::canAccess());
        $this->assertFalse(QuickAddStock::canAccess());
        $page = Livewire::test(ListInventoryItems::class)->set('tableFilters.mine_only.isActive', false)->assertSee('Gated Box');
        $this->assertStringNotContainsString('<div class="vx-card-actions">', $page->html());
        $this->get(InventoryItemResource::getUrl('create'))->assertForbidden();
        $this->get(InventoryItemResource::getUrl('edit', ['record' => $this->item]))->assertForbidden();
        $page->call('startBarcodeScan', $this->item->id)->assertForbidden();
        $this->assertNull($this->item->fresh()->barcode);
    }

    public function test_admin_role_does_not_restore_streamer_write_access(): void
    {
        $user = $this->admin();$user->assignRole('streamer');$this->actingAs($user->fresh());
        $this->assertFalse(InventoryItemResource::canCreate());
        $this->assertFalse(InventoryItemResource::canEdit($this->item));
        $this->assertFalse(InventoryItemResource::canDeleteAny());
        $this->assertFalse(StockGate::canChangeAny());
        $this->postJson('/inventory-scanner-api/barcodes/attach', ['product_id' => $this->item->id, 'barcode' => 'DENIED-CODE'])->assertForbidden();
    }

    public function test_internal_bookkeeping_still_updates_both_sides_without_exposing_hidden_stock(): void
    {
        $this->admin();
        app(InventoryService::class)->transferStock($this->item, $this->main, $this->back, 2);
        $this->assertDatabaseHas('inventory_stock', ['inventory_location_id' => $this->main->id, 'inventory_item_id' => $this->item->id, 'quantity' => 8]);
        $this->assertDatabaseHas('inventory_stock', ['inventory_location_id' => $this->back->id, 'inventory_item_id' => $this->item->id, 'quantity' => 32]);
        $this->assertSame(8.0, $this->item->fresh()->totalQuantity());
        $this->assertSame(1, InventoryMovement::count());
    }
}

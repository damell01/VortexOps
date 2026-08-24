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

/**
 * All Inventory opens on the streamer's own shelves.
 *
 * That is what they count, pick from and report against. The warehouse matters
 * too, but only on the rarer trip where they are looking for something to ask
 * for a transfer of — so it is one toggle away rather than mixed in.
 *
 * The filter is a default, not a restriction: turning it off still respects
 * InventoryVisibility, so it can never become a way to see somewhere they are
 * not allowed.
 */
class StreamerSeesTheirOwnShelvesFirstTest extends TestCase
{
    use RefreshDatabase;

    private User $streamerUser;

    private InventoryItem $mine;

    private InventoryItem $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('streamer', 'web');

        $this->streamerUser = User::factory()->create(['email' => 'streamer@example.com']);
        $this->streamerUser->assignRole('streamer');

        $streamer = Streamer::create([
            'user_id' => $this->streamerUser->id, 'name' => 'Tyler',
            'email' => 'streamer@example.com', 'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $ownLocation  = InventoryLocation::create(['name' => "Tyler's Shelf", 'is_active' => true, 'streamer_id' => $streamer->id]);
        $mainLocation = InventoryLocation::create(['name' => 'Main Warehouse', 'is_active' => true]);

        $this->mine      = InventoryItem::create(['name' => 'ONMYSHELF', 'sku' => 'MINE-1', 'unit_cost' => 5, 'average_cost' => 5, 'is_active' => true]);
        $this->warehouse = InventoryItem::create(['name' => 'INWAREHOUSE', 'sku' => 'WH-1', 'unit_cost' => 5, 'average_cost' => 5, 'is_active' => true]);

        InventoryStock::create(['inventory_item_id' => $this->mine->id, 'inventory_location_id' => $ownLocation->id, 'quantity' => 7]);
        InventoryStock::create(['inventory_item_id' => $this->warehouse->id, 'inventory_location_id' => $mainLocation->id, 'quantity' => 40]);

        // Streamers see the warehouse too, which is the whole reason a default
        // is needed rather than a hard scope.
        \App\Models\Setting::set('streamer_visible_location_ids', json_encode([$mainLocation->id]));

        $this->streamerUser = $this->streamerUser->fresh();
    }

    private function table()
    {
        return Livewire::actingAs($this->streamerUser)
            ->test(ListInventoryItems::class)
            ->call('loadTable');
    }

    public function test_it_opens_on_their_own_shelves(): void
    {
        $html = $this->table()->html();

        $this->assertStringContainsString('ONMYSHELF', $html);
        $this->assertStringNotContainsString('INWAREHOUSE', $html, 'the warehouse is showing before it was asked for');
    }

    public function test_turning_the_filter_off_reveals_what_they_can_transfer_from(): void
    {
        $html = $this->table()
            ->set('tableFilters.mine_only.isActive', false)
            ->html();

        $this->assertStringContainsString('INWAREHOUSE', $html, 'the warehouse stayed hidden with the filter off');
        $this->assertStringContainsString('ONMYSHELF', $html);
    }

    public function test_an_admin_is_not_given_the_filter_at_all(): void
    {
        // Nothing limits an admin, so a "my inventory only" toggle would mean
        // nothing on their screen.
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);

        $filters = Livewire::actingAs($owner)
            ->test(ListInventoryItems::class)
            ->instance()
            ->getTable()
            ->getFilters();

        $this->assertArrayNotHasKey('mine_only', $filters);
    }

    public function test_a_streamer_with_no_shelf_of_their_own_still_sees_a_catalog(): void
    {
        // Filtering to "mine" when there is no mine would empty the page and
        // read as a broken catalog rather than an unassigned location.
        $orphan = User::factory()->create(['email' => 'orphan@example.com']);
        $orphan->assignRole('streamer');

        Streamer::create([
            'user_id' => $orphan->id, 'name' => 'Orphan',
            'email' => 'orphan@example.com', 'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $html = Livewire::actingAs($orphan->fresh())
            ->test(ListInventoryItems::class)
            ->call('loadTable')
            ->html();

        $this->assertStringContainsString('INWAREHOUSE', $html);
    }
}

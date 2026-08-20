<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Setting;
use App\Models\Streamer;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\InventoryVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A streamer cannot ask for a case they cannot see exists.
 *
 * Inventory used to show streamers their own shelf and nothing else, so the
 * main store was invisible to them and every move had to be asked for in a
 * message and done by somebody else. Showing them everything is the other
 * wrong answer: another streamer's shelf, the damaged bin and the returns pile
 * are none of their business and turn a useful screen into a list to scroll
 * past.
 *
 * So it is configured, in one place, and every screen asks there. These pin
 * both halves — that the main store is reachable, and that the rest is not.
 */
class StreamerInventoryAccessTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $main;
    private InventoryLocation $mine;
    private InventoryLocation $theirs;
    private InventoryLocation $damaged;
    private User $streamerUser;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        Setting::set('enabled_admin_modules', json_encode(['inventory']));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->main    = InventoryLocation::create(['name' => 'Main Warehouse', 'type' => 'main_storage', 'status' => 'active']);
        $this->damaged = InventoryLocation::create(['name' => 'Damaged Bin', 'type' => 'damaged', 'status' => 'active']);

        $this->streamerUser = User::factory()->create();
        $this->streamerUser->assignRole('streamer');

        $mineStreamer = Streamer::create(['name' => 'Me', 'user_id' => $this->streamerUser->id, 'status' => 'active']);
        $them         = Streamer::create(['name' => 'Them', 'status' => 'active']);

        $this->mine   = InventoryLocation::create(['name' => 'My Shelf', 'type' => 'streamer_inventory', 'status' => 'active', 'streamer_id' => $mineStreamer->id]);
        $this->theirs = InventoryLocation::create(['name' => 'Their Shelf', 'type' => 'streamer_inventory', 'status' => 'active', 'streamer_id' => $them->id]);

        cache()->forget('inv_loc:active');
        cache()->forget('inv_loc:type:main_storage');
    }

    /**
     * Send stock to the streamer's own inventory, through the page that does it.
     *
     * This was a table action driven with a form array. The capability did not
     * change when it became a page; only the screen did, so the tests follow
     * the capability.
     */
    private function send(InventoryItem $item, InventoryLocation $from, float $quantity, ?string $reason = null): void
    {
        \Livewire\Livewire::test(
            \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::class,
            ['record' => $item->id],
        )
            ->call('setOperation', \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::SEND)
            ->set('fromLocationId', $from->id)
            ->set('moveQuantity', (string) $quantity)
            ->set('reason', $reason ?? '')
            ->call('submit');
    }

    private function stocked(string $name, InventoryLocation $at, float $qty = 10): InventoryItem
    {
        $item = InventoryItem::create(['name' => $name, 'unit_cost' => 5, 'average_cost' => 5, 'is_active' => true]);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $at->id,
            'quantity'              => $qty,
        ]);

        return $item;
    }

    // ── What the setting means ────────────────────────────────────────────

    public function test_unset_means_the_main_store(): void
    {
        // Nobody has made the decision yet, and the sensible reading of that is
        // the place a streamer would be looking anyway.
        $this->assertSame([$this->main->id], InventoryVisibility::configuredForStreamers());
    }

    public function test_an_empty_selection_is_a_decision_and_is_honoured(): void
    {
        // Distinct from unset. Ticking nothing means "their own shelf only",
        // and quietly reopening the main store would ignore an explicit choice.
        Setting::set(InventoryVisibility::SETTING_KEY, json_encode([]));

        $this->assertSame([], InventoryVisibility::configuredForStreamers());
        $this->assertSame([$this->mine->id], InventoryVisibility::locationIdsFor($this->streamerUser));
    }

    public function test_their_own_location_is_visible_however_it_is_configured(): void
    {
        // It is theirs. Hiding it would leave them unable to see what they were
        // sent, which is the one thing they definitely need.
        Setting::set(InventoryVisibility::SETTING_KEY, json_encode([$this->damaged->id]));

        $visible = InventoryVisibility::locationIdsFor($this->streamerUser);

        $this->assertContains($this->mine->id, $visible);
    }

    public function test_an_admin_is_not_limited_at_all(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertNull(InventoryVisibility::locationIdsFor($admin));
        $this->assertFalse(InventoryVisibility::isLimited($admin));
    }

    public function test_a_signed_out_visitor_sees_nothing(): void
    {
        $this->assertSame([], InventoryVisibility::locationIdsFor(null));
    }

    // ── What reaches the list ─────────────────────────────────────────────

    public function test_a_streamer_can_now_see_the_main_store(): void
    {
        // The point of the change. Before this the query was limited to their
        // own shelf, so a case in the warehouse simply did not exist for them.
        $this->stocked('Warehouse Case', $this->main);

        $this->actingAs($this->streamerUser);

        $this->assertSame(
            ['Warehouse Case'],
            InventoryItemResource::getEloquentQuery()->pluck('name')->all(),
        );
    }

    public function test_a_streamer_does_not_see_another_streamers_shelf(): void
    {
        $this->stocked('Not Yours', $this->theirs);

        $this->actingAs($this->streamerUser);

        $this->assertSame([], InventoryItemResource::getEloquentQuery()->pluck('name')->all());
    }

    public function test_a_streamer_sees_what_is_on_their_own_shelf(): void
    {
        $this->stocked('Already Sent', $this->mine);

        $this->actingAs($this->streamerUser);

        $this->assertSame(['Already Sent'], InventoryItemResource::getEloquentQuery()->pluck('name')->all());
    }

    public function test_ticking_a_location_makes_it_visible(): void
    {
        $this->stocked('Broken Box', $this->damaged);

        $this->actingAs($this->streamerUser);
        $this->assertSame([], InventoryItemResource::getEloquentQuery()->pluck('name')->all());

        Setting::set(InventoryVisibility::SETTING_KEY, json_encode([$this->main->id, $this->damaged->id]));

        $this->assertSame(['Broken Box'], InventoryItemResource::getEloquentQuery()->pluck('name')->all());
    }

    // ── Where they can pull stock from ────────────────────────────────────

    public function test_their_own_shelf_is_not_offered_as_a_source(): void
    {
        // Moving stock from your shelf to your shelf is not a transfer, and
        // offering it invites a movement that nets to nothing and still lands
        // in the audit trail.
        $this->actingAs($this->streamerUser);

        $sources = InventoryVisibility::sourceOptionsFor($this->streamerUser);

        $this->assertArrayHasKey($this->main->id, $sources);
        $this->assertArrayNotHasKey($this->mine->id, $sources);
        $this->assertArrayNotHasKey($this->theirs->id, $sources);
    }

    public function test_the_destination_is_their_own_location(): void
    {
        $this->assertSame(
            $this->mine->id,
            InventoryVisibility::destinationFor($this->streamerUser)?->id,
        );
    }

    public function test_a_streamer_with_no_location_has_nowhere_to_send_it(): void
    {
        // A setup problem rather than a permission one — the action hides
        // rather than failing halfway through a transfer.
        $orphan = User::factory()->create();
        $orphan->assignRole('streamer');

        $this->assertNull(InventoryVisibility::destinationFor($orphan));
    }

    // ── Actually moving it ────────────────────────────────────────────────

    public function test_a_streamer_can_pull_stock_onto_their_own_shelf(): void
    {
        $item = $this->stocked('Warehouse Case', $this->main, 10);

        $this->actingAs($this->streamerUser);

        $this->send($item, $this->main, 4, 'Friday break');

        $this->assertEqualsWithDelta(6.0, (float) InventoryStock::where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->main->id)->value('quantity'), 0.01);

        $this->assertEqualsWithDelta(4.0, (float) InventoryStock::where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->mine->id)->value('quantity'), 0.01);
    }

    public function test_the_move_is_recorded_as_a_transfer_with_both_ends(): void
    {
        // "Main Warehouse → My Shelf", not two unrelated adjustments. The whole
        // reason for going through the service rather than writing the two
        // rows directly.
        $item = $this->stocked('Warehouse Case', $this->main, 10);

        $this->actingAs($this->streamerUser);

        $this->send($item, $this->main, 4);

        $movement = \App\Models\InventoryMovement::where('inventory_item_id', $item->id)->latest('id')->first();

        $this->assertSame('transfer', $movement->movement_type);
        $this->assertSame($this->main->id, $movement->from_location_id);
        $this->assertSame($this->mine->id, $movement->to_location_id);
        $this->assertEqualsWithDelta(-4.0, $movement->signedChange(), 0.01);
    }

    public function test_an_item_they_cannot_see_cannot_be_acted_on_at_all(): void
    {
        // Stronger than the guard inside the action: the list query never
        // returns the row, so Filament cannot resolve a record to act on. The
        // guard is still there for the case this cannot cover — a source that
        // is visible but not a permitted source — but the first line of defence
        // is that the item is simply not on their screen.
        $item = $this->stocked('Not Yours', $this->theirs, 10);

        $this->actingAs($this->streamerUser);

        // The page resolves its record through the resource's own query, which
        // is already scoped — so it cannot even be opened for an item they are
        // not allowed to see. That is a better failure than a guard inside the
        // form, because it happens before anything is typed.
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        try {
            $this->send($item, $this->theirs, 4);
        } finally {
            $this->assertEqualsWithDelta(10.0, (float) InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $this->theirs->id)->value('quantity'), 0.01);

            $this->assertSame(0, \App\Models\InventoryMovement::where('movement_type', 'transfer')->count());
        }
    }

    public function test_a_source_outside_their_reach_is_refused_by_the_action(): void
    {
        // The case the list scope cannot catch: the item is visible — it is in
        // the main store — but the form is submitted naming a location they may
        // not take from. Options are rebuilt on every open, and a page left
        // sitting is a page whose options are old.
        $item = $this->stocked('Warehouse Case', $this->main, 10);
        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->theirs->id,
            'quantity'              => 10,
        ]);

        $this->actingAs($this->streamerUser);

        $this->send($item, $this->theirs, 4);

        $this->assertEqualsWithDelta(10.0, (float) InventoryStock::where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->theirs->id)->value('quantity'), 0.01);

        $this->assertSame(0, \App\Models\InventoryMovement::where('movement_type', 'transfer')->count());
    }

    public function test_more_than_is_there_is_refused_rather_than_going_negative(): void
    {
        $item = $this->stocked('Warehouse Case', $this->main, 3);

        $this->actingAs($this->streamerUser);

        $this->send($item, $this->main, 99);

        $this->assertEqualsWithDelta(3.0, (float) InventoryStock::where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->main->id)->value('quantity'), 0.01);
    }
}

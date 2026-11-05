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
 * What a streamer sees and can move.
 *
 * A streamer runs their shows from their own shelf, so that shelf is their
 * whole inventory view: Main, another streamer's shelf and the damaged bin
 * are not on their screens (085aea12). A streamer with no shelf yet sees Main.
 * Streamers are read-only; stock reaches their shelf when the owner or an
 * admin sends it. Admins see every location unless a location hides them.
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

        $mineStreamer = Streamer::updateOrCreate(['user_id' => $this->streamerUser->id], ['name' => 'Me', 'user_id' => $this->streamerUser->id, 'status' => 'active']);
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

    /** Stock at a location, read past the viewer's visibility scope. */
    private function qtyAt(InventoryItem $item, InventoryLocation $at): ?float
    {
        $value = InventoryVisibility::withoutLocationRestrictions(fn () => InventoryStock::where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $at->id)->value('quantity'));

        return $value === null ? null : (float) $value;
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
        // Distinct from unset: ticking nothing adds no extra locations. A
        // streamer with a shelf works from that shelf either way.
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

    public function test_an_admin_sees_every_location_unless_one_hides_them(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $all = [$this->main->id, $this->damaged->id, $this->mine->id, $this->theirs->id];
        sort($all);
        $visible = InventoryVisibility::locationIdsFor($admin);
        sort($visible);
        $this->assertSame($all, $visible);

        Setting::set('inventory_hidden_admins:' . $this->damaged->id, json_encode([$admin->id]));
        InventoryVisibility::flushMemo();
        $this->assertNotContains($this->damaged->id, InventoryVisibility::locationIdsFor($admin));
    }

    public function test_a_signed_out_visitor_sees_nothing(): void
    {
        $this->assertSame([], InventoryVisibility::locationIdsFor(null));
    }

    // ── What reaches the list ─────────────────────────────────────────────

    public function test_a_streamer_with_a_shelf_does_not_see_the_main_store(): void
    {
        $this->stocked('Warehouse Case', $this->main);

        $this->actingAs($this->streamerUser);

        $this->assertSame([], InventoryItemResource::getEloquentQuery()->pluck('name')->all());
    }

    public function test_a_streamer_with_no_shelf_yet_sees_the_main_store(): void
    {
        $this->stocked('Warehouse Case', $this->main);
        $newcomer = User::factory()->create();
        $newcomer->assignRole('streamer');
        Streamer::updateOrCreate(['user_id' => $newcomer->id], ['name' => 'New', 'user_id' => $newcomer->id, 'status' => 'active']);

        $this->actingAs($newcomer->fresh());

        $this->assertSame([$this->main->id], InventoryVisibility::locationIdsFor($newcomer->fresh()));
        $this->assertSame(['Warehouse Case'], InventoryItemResource::getEloquentQuery()->pluck('name')->all());
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

        $this->assertSame([], InventoryItemResource::getEloquentQuery()->pluck('name')->all());
    }

    // ── Where they can pull stock from ────────────────────────────────────

    public function test_their_own_shelf_is_not_offered_as_a_source(): void
    {
        // Moving stock from your shelf to your shelf is not a transfer, and
        // offering it invites a movement that nets to nothing and still lands
        // in the audit trail.
        $this->actingAs($this->streamerUser);

        $sources = InventoryVisibility::sourceOptionsFor($this->streamerUser);

        // Their shelf is all they see, so there is nothing else to offer.
        $this->assertSame([], $sources);
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

    public function test_a_streamer_cannot_pull_stock_out_of_main(): void
    {
        // Main is run by the business (App\Support\StockGate): a streamer
        // asks for stock, an admin sends it.
        $item = $this->stocked('Warehouse Case', $this->main, 10);

        $this->actingAs($this->streamerUser);

        // Main is not on their screens, so the page cannot even resolve the item.
        try {
            $this->send($item, $this->main, 4, 'Friday break');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
        }

        $this->assertEqualsWithDelta(10.0, $this->qtyAt($item, $this->main), 0.01);
        $this->assertNull($this->qtyAt($item, $this->mine));
    }

    public function test_the_owner_sending_main_stock_to_a_shelf_is_recorded_as_a_transfer_with_both_ends(): void
    {
        // "Main Warehouse → My Shelf", not two unrelated adjustments.
        $item = $this->stocked('Warehouse Case', $this->main, 10);
        $admin = User::factory()->create(['email' => config('app.owner_email')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Resources\InventoryItemResource\Pages\ManageStock::class, ['record' => $item->id])
            ->call('setOperation', \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::TRANSFER)
            ->set('fromLocationId', $this->main->id)
            ->set('toLocationId', $this->mine->id)
            ->set('moveQuantity', '4')
            ->call('submit');

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
            $this->assertEqualsWithDelta(10.0, (float) InventoryStock::withoutGlobalScope('streamerLocationOverride')->where('inventory_item_id', $item->id)
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
        $item = $this->stocked('On My Shelf Too', $this->mine, 10);
        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->theirs->id,
            'quantity'              => 10,
        ]);

        $this->actingAs($this->streamerUser);

        $this->send($item, $this->theirs, 4);

        $this->assertEqualsWithDelta(10.0, (float) InventoryStock::withoutGlobalScope('streamerLocationOverride')->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $this->theirs->id)->value('quantity'), 0.01);

        $this->assertSame(0, \App\Models\InventoryMovement::where('movement_type', 'transfer')->count());
    }

    public function test_more_than_is_there_is_refused_rather_than_going_negative(): void
    {
        $item = $this->stocked('Warehouse Case', $this->main, 3);
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));

        \Livewire\Livewire::test(\App\Filament\Resources\InventoryItemResource\Pages\ManageStock::class, ['record' => $item->id])
            ->call('setOperation', \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::TRANSFER)
            ->set('fromLocationId', $this->main->id)
            ->set('toLocationId', $this->mine->id)
            ->set('moveQuantity', '99')
            ->call('submit');

        $this->assertEqualsWithDelta(3.0, $this->qtyAt($item, $this->main), 0.01);
        $this->assertNull($this->qtyAt($item, $this->mine));
    }

    public function test_report_overrides_do_not_change_what_inventory_views_show(): void
    {
        // The per-streamer report override decides which locations a show
        // report may pick from. It neither hides their shelf nor widens the
        // inventory screens past it.
        $this->actingAs($this->streamerUser);
        $this->stocked('In Main', $this->main, 3);
        $owned = $this->stocked('On My Shelf', $this->mine, 9);
        Setting::set('streamer_inventory_locations:'.$this->streamerUser->streamer->id, json_encode([$this->main->id]));
        $this->assertSame([$this->mine->id], InventoryVisibility::locationIdsFor($this->streamerUser));
        $this->assertSame([$this->main->id], InventoryVisibility::reportLocationIdsForStreamer($this->streamerUser->streamer->id));
        $this->assertSame(9.0, (float) InventoryStock::sum('quantity'));
        $this->assertSame([$owned->id], InventoryItemResource::getEloquentQuery()->orderBy('id')->pluck('id')->all());
    }


    public function test_hidden_location_movements_are_excluded_and_transfer_endpoints_are_masked(): void
    {
        $item = $this->stocked('Shared item', $this->mine);
        $hidden = \App\Models\InventoryMovement::create(['inventory_item_id' => $item->id,
            'to_location_id' => $this->theirs->id, 'quantity' => 100, 'movement_type' => 'opening']);
        $transfer = \App\Models\InventoryMovement::create(['inventory_item_id' => $item->id,
            'from_location_id' => $this->theirs->id, 'to_location_id' => $this->mine->id,
            'quantity' => 1, 'movement_type' => 'transfer']);
        $this->actingAs($this->streamerUser);
        $this->assertNull(\App\Models\InventoryMovement::find($hidden->id));
        $visible = \App\Models\InventoryMovement::with(['fromLocation', 'toLocation'])->findOrFail($transfer->id);
        $this->assertNull($visible->fromLocation);
        $this->assertSame($this->mine->id, $visible->toLocation->id);
    }

}

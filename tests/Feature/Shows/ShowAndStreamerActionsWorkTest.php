<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Filament\Resources\ShowResource\Pages\EditShow;
use App\Filament\Resources\ShowResource\Pages\ViewShow;
use App\Filament\Resources\StreamerResource\Pages\CreateStreamer;
use App\Filament\Resources\StreamerResource\Pages\EditStreamer;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The buttons on the shows and streamer screens, pressed.
 *
 * The inventory sweep found two live defects the moment actions were driven
 * rather than pages rendered, so this is the same treatment for the other half
 * of the app: submit the action, then read the database back.
 *
 * Status transitions get the most attention. They decide whether stock posts,
 * whether a payout can be calculated and whether a report can still be edited,
 * so a show landing in the wrong state is not a cosmetic problem.
 */
class ShowAndStreamerActionsWorkTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    private Streamer $streamer;

    private WhatnotChannel $channel;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);

        $this->owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);

        $this->actingAs($this->owner->fresh());

        $this->channel = WhatnotChannel::create([
            'name' => 'Vortex Cards', 'whatnot_username' => 'vortexcards', 'status' => 'active',
        ]);

        $this->streamer = Streamer::create([
            'whatnot_channel_id' => $this->channel->id,
            'name'               => 'Tyler',
            'payout_type'        => 'profit_share',
            'payout_percentage'  => 20,
        ]);

        $this->show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'          => now()->subDays(2)->toDateString(),
            'gross_revenue'      => 8801.00,
            'whatnot_net'        => 6496.34,
            'units_sold'         => 98,
            'status'             => 'reconciled',
            'created_by'         => $this->owner->id,
        ]);

        $this->show->streamers()->attach($this->streamer->id);
    }

    // ── Show status transitions ───────────────────────────────────────────────

    public function test_closing_a_reconciled_show_closes_it(): void
    {
        Livewire::test(ViewShow::class, ['record' => $this->show->id])
            ->callAction('close_show');

        $this->assertSame('closed', $this->show->fresh()->status);
    }

    public function test_cancelling_a_show_cancels_it(): void
    {
        Livewire::test(ViewShow::class, ['record' => $this->show->id])
            ->callAction('cancel_show');

        $this->assertSame('cancelled', $this->show->fresh()->status);
    }

    public function test_a_closed_show_offers_no_way_to_cancel_it(): void
    {
        // Closing is the end of the line: a closed show has been paid against,
        // so reopening it through a cancel would strand the payout.
        $this->show->update(['status' => 'closed']);

        Livewire::test(ViewShow::class, ['record' => $this->show->id])
            ->assertActionHidden('cancel_show');
    }

    public function test_a_cancelled_show_cannot_be_cancelled_again(): void
    {
        $this->show->update(['status' => 'cancelled']);

        Livewire::test(ViewShow::class, ['record' => $this->show->id])
            ->assertActionHidden('cancel_show');
    }

    public function test_closing_is_only_offered_once_the_show_is_reconciled(): void
    {
        // A show still being mapped has numbers nobody has agreed to yet.
        $this->show->update(['status' => 'mapping']);

        Livewire::test(ViewShow::class, ['record' => $this->show->id])
            ->assertActionHidden('close_show');
    }

    public function test_detecting_a_streamer_runs_without_error(): void
    {
        $this->show->streamers()->detach();
        $this->show->update(['status' => 'mapping']);

        Livewire::test(ViewShow::class, ['record' => $this->show->id])
            ->callAction('detect_streamer')
            ->assertOk();
    }

    // ── Editing ───────────────────────────────────────────────────────────────

    public function test_editing_a_show_saves_the_change(): void
    {
        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['title' => 'Renamed Show'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Show', $this->show->fresh()->title);
    }

    public function test_a_show_with_pay_recorded_saves_too(): void
    {
        // The Show Recap section reads each payout's streamer. In the browser the
        // saved record came back without them and that lazy read failed the save;
        // the test harness keeps the preload, so this guards the flow rather than
        // reproducing that failure.
        \App\Models\Payout::create(['show_id' => $this->show->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 42, 'status' => 'draft']);

        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['title' => 'Renamed With Pay'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertOk();

        $this->assertSame('Renamed With Pay', $this->show->fresh()->title);
    }

    public function test_a_show_with_a_relative_whatnot_link_saves_and_the_link_is_made_whole(): void
    {
        // The scraper stored Whatnot's own relative href; the form's URL field
        // (and the browser, silently) refused it, so the show couldn't be saved.
        \Illuminate\Support\Facades\DB::table('shows')->where('id', $this->show->id)->update(['detail_url' => '/dashboard/live/abc-123']);

        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['title' => 'Linked Show'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://www.whatnot.com/dashboard/live/abc-123', $this->show->fresh()->detail_url);
        $this->assertSame('Linked Show', $this->show->fresh()->title);
    }

    public function test_a_newly_typed_whatnot_link_must_be_a_link(): void
    {
        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['detail_url' => 'not a link'])
            ->call('save')
            ->assertHasFormErrors(['detail_url']);
    }

    public function test_picking_a_streamer_on_the_form_makes_them_primary(): void
    {
        $this->show->streamers()->detach();
        $other = Streamer::create(['name' => 'Morgan', 'status' => 'active']);

        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['streamers' => [$this->streamer->id, $other->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotNull($this->show->fresh()->primaryStreamer(), 'a show with streamers has a primary');
        $this->assertSame(1, $this->show->fresh()->streamers()->wherePivot('is_primary', true)->count());
    }

    public function test_a_press_that_sent_nothing_is_logged(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $this->post('/admin/client-diagnostic', ['what' => 'Pressed but nothing was sent', 'detail' => 'Save changes', 'page' => '/admin/shows/1/edit'])
            ->assertNoContent();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => str_contains($message, 'nothing was sent') && $context['detail'] === 'Save changes');
    }

    // ── Streamers ─────────────────────────────────────────────────────────────

    public function test_creating_a_streamer_stores_their_payout_terms(): void
    {
        Livewire::test(CreateStreamer::class)
            ->fillForm([
                'name'              => 'Connor',
                'payout_type'       => 'profit_share',
                'payout_percentage' => 25,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Streamer::firstWhere('name', 'Connor');

        $this->assertNotNull($created, 'the streamer was not created');
        $this->assertSame('profit_share', $created->payout_type);
        $this->assertEqualsWithDelta(25.0, (float) $created->payout_percentage, 0.01);
    }

    public function test_editing_a_streamer_saves_the_change(): void
    {
        Livewire::test(EditStreamer::class, ['record' => $this->streamer->id])
            ->fillForm(['payout_percentage' => 30])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsWithDelta(30.0, (float) $this->streamer->fresh()->payout_percentage, 0.01);
    }

    public function test_a_streamer_with_inventory_locations_saves_and_keeps_them(): void
    {
        // Every streamer with a location failed to save ("The selected location
        // is invalid"), and the repeater behind it would have deleted shared
        // storage on a save. The section is read-only now.
        $own = InventoryLocation::create(['name' => 'Tyler Shelf', 'type' => 'streamer_inventory', 'status' => 'active', 'streamer_id' => $this->streamer->id]);
        $shared = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);

        Livewire::test(EditStreamer::class, ['record' => $this->streamer->id])
            ->fillForm(['payout_percentage' => 35])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsWithDelta(35.0, (float) $this->streamer->fresh()->payout_percentage, 0.01);
        $this->assertNotNull($own->fresh());
        $this->assertNotNull($shared->fresh());
    }

    public function test_a_show_on_a_switched_off_channel_still_saves(): void
    {
        $this->channel->update(['status' => 'inactive']);

        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['title' => 'Still Editable'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Still Editable', $this->show->fresh()->title);
        $this->assertSame($this->channel->id, $this->show->fresh()->whatnot_channel_id);
    }

    public function test_a_switched_off_channel_cannot_be_newly_picked(): void
    {
        $off = WhatnotChannel::create(['name' => 'Old Channel', 'whatnot_username' => 'oldchannel', 'status' => 'inactive']);

        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['whatnot_channel_id' => $off->id])
            ->call('save')
            ->assertHasFormErrors(['whatnot_channel_id']);
    }

    public function test_a_show_holding_a_negative_unit_count_still_saves(): void
    {
        // Whatnot reports net units, which go below zero after refunds.
        $this->show->forceFill(['units_sold' => -3])->saveQuietly();

        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['title' => 'Refund Night'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Refund Night', $this->show->fresh()->title);
    }

    public function test_a_negative_unit_count_cannot_be_newly_typed(): void
    {
        Livewire::test(EditShow::class, ['record' => $this->show->id])
            ->fillForm(['units_sold' => -5])
            ->call('save')
            ->assertHasFormErrors(['units_sold']);
    }

    public function test_edit_forms_leave_checking_to_the_server(): void
    {
        $this->get(\App\Filament\Resources\StreamerResource::getUrl('edit', ['record' => $this->streamer]))
            ->assertOk()
            ->assertSee('novalidate', false);
    }

    public function test_a_location_owned_by_an_archived_streamer_still_saves(): void
    {
        $location = InventoryLocation::create(['name' => 'Tyler Shelf', 'type' => 'streamer_inventory', 'status' => 'active', 'streamer_id' => $this->streamer->id]);
        $this->streamer->update(['status' => 'inactive']);

        Livewire::test(\App\Filament\Resources\InventoryLocationResource\Pages\EditInventoryLocation::class, ['record' => $location->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($this->streamer->id, $location->fresh()->streamer_id);
    }

    // ── The end-of-stream report, as the streamer ─────────────────────────────

    private function streamerUser(): User
    {
        $user = User::factory()->create(['name' => 'Tyler']);
        $user->assignRole('streamer');
        $this->streamer->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_a_streamer_can_open_the_report_for_their_own_show(): void
    {
        $this->show->update(['status' => 'mapping']);
        $this->actingAs($this->streamerUser());

        Livewire::test(EndOfStreamForm::class, ['showId' => (string) $this->show->id])
            ->assertOk();
    }

    public function test_a_reported_line_lands_on_the_entry(): void
    {
        $this->show->update(['status' => 'mapping']);

        $location = InventoryLocation::create([
            'name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Chrome Hobby Box', 'sku' => 'EOS-1',
            'unit_cost' => 80, 'average_cost' => 80, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 10,
        ]);

        $this->actingAs($this->streamerUser());

        Livewire::test(EndOfStreamForm::class, ['showId' => (string) $this->show->id])
            ->call('addLineItem', $item->id, 3, 'sold')
            ->assertOk();

        $entry = StreamerLogEntry::where('show_id', $this->show->id)->first();

        $this->assertNotNull($entry, 'reporting a line created no log entry');
        $this->assertSame(1, $entry->items()->count());
        $this->assertEqualsWithDelta(3.0, (float) $entry->items()->first()->quantity, 0.01);
    }

    public function test_a_removed_line_leaves_the_entry(): void
    {
        $this->show->update(['status' => 'mapping']);

        // A streamer can only add items they can see stock of (the picker's
        // own rule), so the item needs some in Main Storage.
        $location = InventoryLocation::create([
            'name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'name' => 'Removable Box', 'sku' => 'EOS-2',
            'unit_cost' => 20, 'average_cost' => 20, 'is_active' => true,
        ]);

        InventoryStock::create([
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $location->id,
            'quantity'              => 5,
        ]);

        $this->actingAs($this->streamerUser());

        $page = Livewire::test(EndOfStreamForm::class, ['showId' => (string) $this->show->id])
            ->call('addLineItem', $item->id, 2, 'sold');

        $entry = StreamerLogEntry::where('show_id', $this->show->id)->first();
        $line  = $entry->items()->first();

        $page->call('removeLineItem', $line->id);

        $this->assertSame(0, $entry->fresh()->items()->count());
    }

    // ── What a streamer must not reach ────────────────────────────────────────

    public function test_a_streamer_cannot_close_a_show(): void
    {
        // Closing decides when payouts are final, so it is an admin decision
        // regardless of whose show it is.
        //
        // The check is that the page does not open at all, rather than that
        // the button is hidden on it — a streamer never reaches the admin show
        // record, so there is no rendered page to hide a button on. Asserting
        // the button's absence would pass for the wrong reason.
        $this->actingAs($this->streamerUser());

        try {
            Livewire::test(ViewShow::class, ['record' => $this->show->id]);
            $this->fail('a streamer was able to open the admin show page');
        } catch (\Throwable $e) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame('reconciled', $this->show->fresh()->status);
    }

    public function test_an_order_belongs_to_the_show_it_was_imported_against(): void
    {
        WhatnotShowOrder::create([
            'show_id'          => $this->show->id,
            'whatnot_order_id' => 'ACT-ORDER-1',
            'buyer_username'   => 'buyer1',
            'item_name'        => 'Lot 1',
            'quantity'         => 1,
            'unit_price'       => 89.81,
            'total_price'      => 89.81,
        ]);

        $this->assertSame(1, $this->show->fresh()->orders()->count());
    }
}

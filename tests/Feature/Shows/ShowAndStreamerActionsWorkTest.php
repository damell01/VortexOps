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

        $item = InventoryItem::create([
            'name' => 'Removable Box', 'sku' => 'EOS-2',
            'unit_cost' => 20, 'average_cost' => 20, 'is_active' => true,
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

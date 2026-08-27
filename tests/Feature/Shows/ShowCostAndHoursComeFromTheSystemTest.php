<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What a show cost and how long it ran, taken from what the system already
 * knows rather than from the keyboard.
 *
 * Both feed the profit share — product cost is subtracted from gross revenue,
 * hours and shipments make up the burden — so a figure typed from memory is a
 * figure somebody's pay is wrong by.
 *
 * Product cost used to be stamped onto each line from the item's average_cost
 * the moment it was added. An item nobody had received yet has no average, so
 * it stamped 0.00 and the show read as though its inventory had been free.
 */
class ShowCostAndHoursComeFromTheSystemTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;
    private User $user;
    private Streamer $streamer;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('streamer', 'web');

        $this->user = User::factory()->create(['email' => 'cost-source@example.test']);
        $this->user->assignRole('streamer');

        $channel = WhatnotChannel::create(['name' => 'Test Channel', 'status' => 'active']);

        $this->streamer = Streamer::create([
            'user_id'           => $this->user->id,
            'name'              => 'Test Streamer',
            'email'             => 'cost-source@example.test',
            'status'            => 'active',
            'payout_type'       => 'profit_share',
            'payout_percentage' => 8,
            'include_tips'      => false,
        ]);

        $this->location = InventoryLocation::create([
            'name'        => 'Streamer Shelf',
            'is_active'   => true,
            'streamer_id' => $this->streamer->id,
        ]);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Break Night',
            'show_date'          => today()->toDateString(),
            'gross_revenue'      => 7371.10,
            'show_duration'      => 267,   // 4.45 hrs
            'status'             => 'mapping',
        ]);

        $this->show->streamers()->attach($this->streamer->id);
    }

    private function form()
    {
        return Livewire::actingAs($this->user->fresh())
            ->test(EndOfStreamForm::class)
            ->call('selectShow', (string) $this->show->id);
    }

    private function item(array $attributes = [], int $quantity = 20): Product
    {
        $product = Product::create(array_merge([
            'name'      => 'Topps Chrome Hobby Box',
            'is_active' => true,
        ], $attributes));

        InventoryStock::create([
            'inventory_item_id'     => $product->id,
            'inventory_location_id' => $this->location->id,
            'quantity'              => $quantity,
        ]);

        return $product;
    }

    private function entry(): StreamerLogEntry
    {
        return StreamerLogEntry::where('show_id', $this->show->id)->firstOrFail();
    }

    // ── Product cost ───────────────────────────────────────────────────────

    public function test_an_item_never_received_is_costed_at_its_list_price(): void
    {
        // The case that made a show's inventory look free: unit_cost is set,
        // average_cost is not, because nothing has come in through receiving.
        $item = $this->item(['unit_cost' => 89.50]);

        $this->form()->call('addLineItem', $item->id, 4);

        $line = $this->entry()->items()->firstOrFail();

        $this->assertSame(89.50, $line->effectiveUnitCost());
        $this->assertSame(358.00, $line->total_cost);
    }

    public function test_a_received_average_wins_over_the_list_price(): void
    {
        $item = $this->item(['unit_cost' => 89.50, 'average_cost' => 102.25]);

        $this->form()->call('addLineItem', $item->id, 2);

        $this->assertSame(204.50, $this->entry()->items()->firstOrFail()->total_cost);
    }

    public function test_a_line_follows_the_catalogue_until_the_report_is_filed(): void
    {
        // Receiving lands after the line was added. The draft picks it up,
        // because nothing was frozen onto the line when it was created.
        $item = $this->item(['unit_cost' => 89.50]);

        $this->form()->call('addLineItem', $item->id, 1);

        $item->forceFill(['average_cost' => 95.00])->save();

        $this->assertSame(95.00, $this->entry()->items()->firstOrFail()->fresh()->total_cost);
    }

    public function test_the_report_carries_its_cost_before_it_is_submitted(): void
    {
        $item = $this->item(['unit_cost' => 89.50]);

        $this->form()->call('addLineItem', $item->id, 4);

        $this->assertSame('358.00', (string) $this->entry()->product_cost);
    }

    public function test_a_typed_cost_wins_and_can_be_handed_back(): void
    {
        $item = $this->item(['unit_cost' => 89.50]);
        $page = $this->form();
        $page->call('addLineItem', $item->id, 2);

        $lineId = $this->entry()->items()->value('id');

        $page->call('setLineCost', $lineId, 70.00);
        $this->assertSame(140.00, $this->entry()->items()->firstOrFail()->fresh()->total_cost);
        $this->assertFalse($this->entry()->items()->firstOrFail()->fresh()->costIsFromInventory());

        $page->call('clearLineCost', $lineId);
        $line = $this->entry()->items()->firstOrFail()->fresh();

        $this->assertTrue($line->costIsFromInventory());
        $this->assertSame(179.00, $line->total_cost);
    }

    public function test_submitting_freezes_the_cost_the_report_was_filed_on(): void
    {
        // Otherwise a receipt next week silently restates a show payroll has
        // already been paid on.
        $item = $this->item(['unit_cost' => 89.50]);
        $page = $this->form();
        $page->call('addLineItem', $item->id, 2);
        $page->call('submit');

        $item->forceFill(['average_cost' => 500.00])->save();

        $line = $this->entry()->items()->firstOrFail()->fresh();

        $this->assertSame('89.50', (string) $line->unit_cost);
        $this->assertSame(179.00, $line->total_cost);
        $this->assertSame('179.00', (string) $this->entry()->product_cost);
    }

    public function test_an_item_that_is_not_in_the_catalogue_is_still_typed(): void
    {
        $this->form()->call('addManualLineItem', 'Loose singles lot', 3, 12.00);

        $line = $this->entry()->items()->firstOrFail();

        $this->assertSame(36.00, $line->total_cost);
        $this->assertFalse($line->isMatched());
    }

    // ── Hours and shipments ────────────────────────────────────────────────

    public function test_hours_are_filled_in_from_the_length_of_the_show(): void
    {
        $this->form()->assertSet('hoursStreamed', '4.45');

        $this->assertSame('4.45', (string) $this->entry()->hours_streamed);
    }

    public function test_shipments_are_counted_off_the_show(): void
    {
        foreach (range(1, 3) as $n) {
            Shipment::create(['show_id' => $this->show->id, 'buyer_username' => "buyer{$n}"]);
        }

        $this->form()->assertSet('shipments', '3');

        $this->assertSame(3, $this->entry()->number_of_shipments);
    }

    public function test_the_fields_are_on_the_step_the_streamer_fills_in(): void
    {
        $this->form()
            ->call('goToStep', 2)
            ->assertSee('Hours streamed')
            ->assertSee('Show length on Whatnot: 4.45 hrs')
            ->assertSee('shipments: 0');
    }

    public function test_the_streamer_can_type_over_the_suggestion_and_it_sticks(): void
    {
        $page = $this->form();

        $page->set('hoursStreamed', '5.25')->call('saveDetails');

        $this->assertSame('5.25', (string) $this->entry()->hours_streamed);

        // Reopening does not put the show's recorded length back over it.
        $this->form()->assertSet('hoursStreamed', '5.25');
        $this->assertSame('5.25', (string) $this->entry()->hours_streamed);
    }

    public function test_nothing_is_suggested_when_the_show_length_is_unknown(): void
    {
        // A suggestion of zero hours is worse than no suggestion: it reads as
        // an answer, and the burden is short by however long the show ran.
        $this->show->forceFill(['show_duration' => null])->save();

        $this->form()->assertSet('hoursStreamed', '');

        $this->assertNull($this->entry()->hours_streamed);
    }
}

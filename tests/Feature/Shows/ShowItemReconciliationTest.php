<?php

namespace Tests\Feature\Shows;

use App\Filament\Widgets\ShowItemReconciliationWidget;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whatnot's count of a show against the streamer's.
 *
 * Two independent records of the same night, and nothing in the app compared
 * them. Whatnot counts what buyers paid for; the End of Stream report counts
 * what the streamer physically handed over, giveaways and promos included —
 * things Whatnot has no order for.
 *
 * A difference is not a fault on either side. It usually means stock left the
 * shelf without being logged, which is what makes a later count look wrong,
 * and it is answerable on the night in a way it is not a month later.
 */
class ShowItemReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    private Streamer $streamer;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);

        $this->actingAs($owner->fresh());

        $channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        $this->streamer = Streamer::create([
            'whatnot_channel_id' => $channel->id,
            'name'               => 'Tyler',
            'payout_type'        => 'profit_share',
            'payout_percentage'  => 20,
        ]);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'          => now()->subDay()->toDateString(),
            'gross_revenue'      => 8801.00,
            'units_sold'         => 98,
            'giveaways_count'    => 59,
            'created_by'         => $owner->id,
        ]);

        $this->show->streamers()->attach($this->streamer->id);
    }

    private function report(bool $submitted = true): StreamerLogEntry
    {
        return StreamerLogEntry::create([
            'show_id'      => $this->show->id,
            'streamer_id'  => $this->streamer->id,
            'status'       => $submitted ? 'pending' : 'draft',
            'submitted_at' => $submitted ? now() : null,
        ]);
    }

    private function logged(StreamerLogEntry $entry, string $disposition, int $quantity): void
    {
        StreamerLogItem::create([
            'streamer_log_entry_id' => $entry->id,
            'item_name'             => 'Chrome Hobby Box',
            'quantity'              => $quantity,
            'disposition'           => $disposition,
        ]);
    }

    /** @return array<string, array<string, mixed>> keyed by disposition */
    private function rows(): array
    {
        return collect($this->show->fresh()->itemReconciliation())->keyBy('key')->all();
    }

    public function test_it_reports_whatnots_number_and_the_logged_number(): void
    {
        $entry = $this->report();
        $this->logged($entry, 'sold', 94);

        $sold = $this->rows()['sold'];

        $this->assertSame(98, $sold['whatnot']);
        $this->assertSame(94, $sold['logged']);
    }

    public function test_a_shortfall_is_negative(): void
    {
        // Four items sold on Whatnot that nobody logged — stock that left the
        // shelf without a record, which is what makes the next count wrong.
        $entry = $this->report();
        $this->logged($entry, 'sold', 94);

        $this->assertSame(-4, $this->rows()['sold']['difference']);
    }

    public function test_logging_more_than_was_sold_is_positive(): void
    {
        $entry = $this->report();
        $this->logged($entry, 'sold', 101);

        $this->assertSame(3, $this->rows()['sold']['difference']);
    }

    public function test_quantities_of_the_same_disposition_are_summed(): void
    {
        // A report is filed line by line as the streamer works through the
        // boxes, so one disposition arrives across many rows.
        $entry = $this->report();
        $this->logged($entry, 'sold', 50);
        $this->logged($entry, 'sold', 48);

        $this->assertSame(98, $this->rows()['sold']['logged']);
        $this->assertSame(0, $this->rows()['sold']['difference']);
    }

    public function test_giveaways_are_compared_too(): void
    {
        $entry = $this->report();
        $this->logged($entry, 'giveaway', 55);

        $giveaway = $this->rows()['giveaway'];

        $this->assertSame(59, $giveaway['whatnot']);
        $this->assertSame(55, $giveaway['logged']);
        $this->assertSame(-4, $giveaway['difference']);
    }

    public function test_promo_has_no_whatnot_side_to_compare_against(): void
    {
        // Whatnot has no order for a promo, so it reports no figure. Showing a
        // zero there would be a claim Whatnot never made, and a difference
        // computed from it would be an invented shortfall.
        $entry = $this->report();
        $this->logged($entry, 'promo', 7);

        $promo = $this->rows()['promo'];

        $this->assertNull($promo['whatnot']);
        $this->assertSame(7, $promo['logged']);
        $this->assertNull($promo['difference']);
    }

    public function test_order_rows_are_preferred_over_whatnots_own_total(): void
    {
        // Once orders are imported they are the finer-grained truth, and they
        // are what the money is computed from.
        foreach ([3, 2] as $quantity) {
            WhatnotShowOrder::create([
                'show_id'          => $this->show->id,
                'whatnot_order_id' => 'ORD-' . uniqid(),
                'item_name'        => 'Lot',
                'quantity'         => $quantity,
                'unit_price'       => 20,
                'total_price'      => 20 * $quantity,
            ]);
        }

        $this->assertSame(5, $this->rows()['sold']['whatnot']);
    }

    public function test_a_show_with_no_report_logs_nothing_rather_than_failing(): void
    {
        $sold = $this->rows()['sold'];

        $this->assertSame(98, $sold['whatnot']);
        $this->assertSame(0, $sold['logged']);
        $this->assertFalse($this->show->fresh()->itemReportIsFiled());
    }

    // ── What the widget says about it ─────────────────────────────────────────

    private function widget(): ShowItemReconciliationWidget
    {
        $widget = new ShowItemReconciliationWidget;
        $widget->record = $this->show->fresh();

        return $widget;
    }

    public function test_an_unfiled_report_is_not_reported_as_a_discrepancy(): void
    {
        // Nothing logged because nobody has filed yet is the ordinary state of
        // a show that ended an hour ago. Calling that a 98-item shortfall
        // would put every fresh show in the red.
        $verdict = $this->widget()->getVerdictProperty();

        $this->assertSame('idle', $verdict['tone']);
        $this->assertStringContainsString('No End of Stream report', $verdict['text']);
    }

    public function test_a_matching_report_says_so_plainly(): void
    {
        $entry = $this->report();
        $this->logged($entry, 'sold', 98);
        $this->logged($entry, 'giveaway', 59);

        $verdict = $this->widget()->getVerdictProperty();

        $this->assertSame('match', $verdict['tone']);
    }

    public function test_a_difference_is_described_in_words(): void
    {
        // The number alone leaves the reader to work out which direction it
        // runs in, and which way round it is decides what to do about it.
        $entry = $this->report();
        $this->logged($entry, 'sold', 94);
        $this->logged($entry, 'giveaway', 59);

        $verdict = $this->widget()->getVerdictProperty();

        $this->assertSame('differs', $verdict['tone']);
        $this->assertStringContainsString('4 items sold on Whatnot but not logged', $verdict['text']);
    }

    public function test_a_single_item_difference_reads_as_one_item(): void
    {
        $entry = $this->report();
        $this->logged($entry, 'sold', 97);
        $this->logged($entry, 'giveaway', 59);

        $this->assertStringContainsString('1 item ', $this->widget()->getVerdictProperty()['text']);
    }
}

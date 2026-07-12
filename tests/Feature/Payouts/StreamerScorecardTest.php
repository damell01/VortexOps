<?php

namespace Tests\Feature\Payouts;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamerScorecardTest extends TestCase
{
    use RefreshDatabase;

    public function test_scorecard_aggregates_shows_gross_margin_and_rating(): void
    {
        $creator  = User::factory()->create()->id;
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active', 'payout_type' => 'flat_rate']);

        $showA = Show::create([
            'title' => 'A', 'show_date' => now()->toDateString(), 'status' => 'reconciled',
            'created_by' => $creator, 'gross_revenue' => 1000, 'whatnot_net' => 900,
            'tips' => 50, 'avg_order_rating' => 4.0,
        ]);
        $showB = Show::create([
            'title' => 'B', 'show_date' => now()->toDateString(), 'status' => 'reconciled',
            'created_by' => $creator, 'gross_revenue' => 500, 'whatnot_net' => 450,
            'tips' => 0, 'avg_order_rating' => 5.0,
        ]);
        $streamer->shows()->attach($showA->id, ['is_primary' => true]);
        $streamer->shows()->attach($showB->id, ['is_primary' => true]);

        // A payout on show A reduces that show's margin.
        Payout::create([
            'streamer_id' => $streamer->id, 'show_id' => $showA->id, 'payout_type' => 'flat_rate',
            'calculated_payout' => 200, 'gross_show_revenue' => 1000, 'status' => 'approved',
        ]);

        $card = $streamer->scorecard();

        $this->assertTrue($card['has_data']);
        $this->assertEquals(2, $card['shows']);
        $this->assertEqualsWithDelta(1500.0, $card['gross'], 0.01);
        // margin A = (900+50)-0-200 = 750 ; margin B = 450-0 = 450 ; total 1200
        $this->assertEqualsWithDelta(1200.0, $card['margin'], 0.01);
        $this->assertEqualsWithDelta(4.5, $card['avg_rating'], 0.01);
        $this->assertEquals(2, $card['rated_shows']);
    }

    public function test_scorecard_is_empty_for_a_streamer_with_no_shows(): void
    {
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active', 'payout_type' => 'flat_rate']);

        $card = $streamer->scorecard();
        $this->assertFalse($card['has_data']);
        $this->assertEquals(0, $card['shows']);
        $this->assertNull($card['avg_rating']);
    }
}

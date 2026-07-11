<?php

namespace Tests\Feature\Shows;

use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowProfitTest extends TestCase
{
    use RefreshDatabase;

    private function makeShow(): Show
    {
        return Show::create([
            'title'         => 'P&L Show',
            'show_date'     => now()->toDateString(),
            'gross_revenue' => 1000,
            'whatnot_net'   => 900,   // Whatnot took $100 in fees
            'tips'          => 50,
            'status'        => 'reconciled',
            'created_by'    => User::factory()->create()->id,
        ]);
    }

    public function test_margin_is_net_plus_tips_minus_cogs_and_payouts(): void
    {
        $show     = $this->makeShow();
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active', 'payout_type' => 'flat_rate']);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        // COGS: two deduction lines totalling $200
        $item = InventoryItem::create(['name' => 'Card', 'is_active' => true]);
        $loc  = InventoryLocation::create(['name' => 'Main']);
        $dr = DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'approved']);
        $lineBase = ['deduction_request_id' => $dr->id, 'inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id, 'quantity_suggested' => 1, 'quantity_approved' => 1, 'unit_cost_snapshot' => 0];
        DeductionRequestLine::create($lineBase + ['raw_description' => 'A', 'line_total' => 120]);
        DeductionRequestLine::create($lineBase + ['raw_description' => 'B', 'line_total' => 80]);

        // Payout: $300
        Payout::create([
            'streamer_id' => $streamer->id, 'show_id' => $show->id, 'payout_type' => 'flat_rate',
            'calculated_payout' => 300, 'gross_show_revenue' => 1000, 'status' => 'draft',
        ]);

        $pl = $show->fresh()->profitAndLoss();

        // (900 net + 50 tips) − 200 cogs − 300 payouts = 450
        $this->assertEqualsWithDelta(450.0, $pl['margin'], 0.01);
        $this->assertEqualsWithDelta(200.0, $pl['cogs'], 0.01);
        $this->assertEqualsWithDelta(300.0, $pl['payouts'], 0.01);
        // margin % of base (950): 450/950 ≈ 47.4%
        $this->assertEqualsWithDelta(47.4, $pl['margin_pct'], 0.1);
    }

    public function test_margin_is_negative_when_costs_exceed_revenue(): void
    {
        $show     = $this->makeShow();
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active', 'payout_type' => 'flat_rate']);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        Payout::create([
            'streamer_id' => $streamer->id, 'show_id' => $show->id, 'payout_type' => 'flat_rate',
            'calculated_payout' => 2000, 'gross_show_revenue' => 1000, 'status' => 'draft',
        ]);

        $this->assertLessThan(0, $show->fresh()->profitAndLoss()['margin']);
        $this->assertLessThan(0, $show->fresh()->net_profit); // accessor agrees
    }

    public function test_margin_handles_show_with_no_costs(): void
    {
        $show = $this->makeShow();

        // No deductions, no payouts → margin = net + tips = 950
        $this->assertEqualsWithDelta(950.0, $show->profitAndLoss()['margin'], 0.01);
    }
}

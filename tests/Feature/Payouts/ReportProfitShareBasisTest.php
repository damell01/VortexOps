<?php

namespace Tests\Feature\Payouts;

use App\Models\{Setting, Show, Streamer, StreamerLogEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportProfitShareBasisTest extends TestCase
{
    use RefreshDatabase;

    private function report(?float $earnings): StreamerLogEntry
    {
        $user = User::factory()->create();
        $streamer = Streamer::create(['name' => 'Share host', 'payout_type' => 'profit_share', 'payout_percentage' => 8, 'status' => 'active']);
        $show = Show::create(['title' => 'Earnings basis', 'show_date' => today(), 'created_by' => $user->id, 'gross_revenue' => 10000, 'completed_earnings' => $earnings]);
        return StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'gross_revenue' => 10000, 'product_cost' => 3392, 'hours_streamed' => 4.45, 'number_of_shipments' => 80]);
    }

    public function test_report_uses_earnings_and_the_same_working_as_payroll(): void
    {
        Setting::set('payroll_burden_per_shipment', '2.10');
        Setting::set('payroll_burden_per_hour', '80');
        $this->assertSame(276.41, $this->report(7371.10)->profitShareAmount());
    }

    public function test_missing_earnings_never_falls_back_to_sales(): void
    {
        $this->assertNull($this->report(null)->profitShareAmount());
    }

    public function test_confirmed_zero_earnings_preserves_the_loss(): void
    {
        Setting::set('payroll_burden_per_shipment', '');
        Setting::set('payroll_burden_per_hour', '');
        $this->assertSame(-271.36, $this->report(0)->profitShareAmount());
    }
}

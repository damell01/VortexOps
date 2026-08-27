<?php

namespace Tests\Feature\Payouts;

use App\Models\Setting;
use App\Support\ProfitShareFormula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The streamer profit-share formula, checked against the paperwork it came from.
 *
 * Lifted from the Calculations tab every streamer fills in after a show, whose
 * cell H998 reads ROUND(shipments * 2.1 + 80 * hours, 2) beside a cell labelled
 * "Burden Rate". Until this existed, VortexOps deducted no burden at all and
 * would have paid roughly $42 too much on the show below — about 15% over.
 */
class ProfitShareFormulaTest extends TestCase
{
    use RefreshDatabase;

    /** The signed show from 8/13/26: Caylen Campbell, Free Storm Emeralda. */
    public function test_it_reproduces_the_signed_paperwork_exactly(): void
    {
        $working = ProfitShareFormula::forShow(
            grossRevenue: 7371.10,
            productCost:  3392.00,
            hours:        4.45,
            shipments:    80,
            percentage:   8.0,
        );

        $this->assertSame(524.00, $working['burden']);
        $this->assertSame(3455.10, $working['net_revenue']);
        $this->assertSame(276.41, $working['earnings']);
    }

    public function test_without_the_burden_the_answer_is_wrong(): void
    {
        // Guards against the burden being quietly dropped again: gross minus
        // product cost alone gives $318.33, which is what payroll would have
        // had to correct by hand every week.
        $naive = round((7371.10 - 3392.00) * 0.08, 2);

        $this->assertSame(318.33, $naive);
        $this->assertNotSame($naive, ProfitShareFormula::forShow(7371.10, 3392.00, 4.45, 80, 8.0)['earnings']);
    }

    public function test_the_rates_come_from_settings(): void
    {
        Setting::set('payroll_burden_per_shipment', '3.00');
        Setting::set('payroll_burden_per_hour', '100.00');

        // 10 × 3.00 + 2 × 100.00
        $this->assertSame(230.00, ProfitShareFormula::burden(10, 2));
    }

    public function test_it_falls_back_to_the_sheets_rates_when_nothing_is_set(): void
    {
        $this->assertSame(2.10, ProfitShareFormula::ratePerShipment());
        $this->assertSame(80.00, ProfitShareFormula::ratePerHour());
    }

    public function test_a_show_that_lost_money_says_so(): void
    {
        // Not floored at zero. Whether a loss reduces somebody's pay is a
        // decision for whoever reviews the pay run, and hiding it behind a
        // break-even reading takes that decision away from them.
        $working = ProfitShareFormula::forShow(1000.00, 2000.00, 5, 20, 8.0);

        $this->assertLessThan(0, $working['net_revenue']);
        $this->assertLessThan(0, $working['earnings']);
    }

    public function test_the_working_reads_as_a_person_would_check_it(): void
    {
        $explained = ProfitShareFormula::explain(
            ProfitShareFormula::forShow(7371.10, 3392.00, 4.45, 80, 8.0),
        );

        $this->assertStringContainsString('80 shipments × $2.10', $explained);
        $this->assertStringContainsString('4.45 hrs × $80.00', $explained);
        $this->assertStringContainsString('$524.00', $explained);
        $this->assertStringContainsString('$3,455.10', $explained);
        $this->assertStringContainsString('8% = $276.41', $explained);
    }
}

<?php

namespace Tests\Feature\Payouts;

use App\Models\Setting;
use App\Support\ProfitShareFormula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The burden the Calculations tab charges is charged here too, without anybody
 * having to go and set it up.
 *
 * The rates were once constants the code fell back to, so a burden applied
 * whether or not anybody had configured one. Making them optional was right —
 * a charge nobody asked for should not come off somebody's pay — but on its own
 * it left an install that had never opened Settings quietly not charging a
 * burden the sheet has always charged, which is roughly 15% too much paid out
 * on a show like the signed one.
 */
class BurdenRatesAreConfiguredOutOfTheBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sheets_rates_are_set_after_migrating(): void
    {
        $this->assertSame(2.10, ProfitShareFormula::ratePerShipment());
        $this->assertSame(80.00, ProfitShareFormula::ratePerHour());
        $this->assertTrue(ProfitShareFormula::hasBurden());
    }

    public function test_a_fresh_install_reproduces_the_signed_paperwork(): void
    {
        // Nothing configured by hand — this is what the app does on day one.
        $working = ProfitShareFormula::forShow(7371.10, 3392.00, 4.45, 80, 8.0);

        $this->assertSame(524.00, $working['burden']);
        $this->assertSame(276.41, $working['earnings']);
    }

    public function test_a_rate_that_has_been_cleared_stays_cleared(): void
    {
        // The seed only fills a key that has never been written. Somebody who
        // deliberately empties a rate must not find it back next deploy.
        Setting::set('payroll_burden_per_hour', '');

        $this->artisan('migrate', ['--force' => true]);

        $this->assertNull(ProfitShareFormula::ratePerHour());
        $this->assertSame(2.10, ProfitShareFormula::ratePerShipment());
    }
}

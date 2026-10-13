<?php

namespace Tests\Feature\Payroll;

use App\Filament\Pages\PayrollOverview;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollOverviewUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 18:00:00');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_period_navigation_changes_the_view_without_creating_pay_runs(): void
    {
        WeeklyPayoutBatch::create(['week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'status' => 'draft']);
        Livewire::test(PayrollOverview::class)->assertSee('Payout trend')
            ->call('changePeriod', -1)->assertSet('period_date', '2026-10-11')
            ->call('changePeriod', 1)->assertSet('period_date', '2026-10-12');
        $this->assertSame(1, WeeklyPayoutBatch::count());
    }

    public function test_trend_uses_paid_run_totals_and_excludes_drafts(): void
    {
        WeeklyPayoutBatch::create(['week_start' => '2026-09-28', 'week_end' => '2026-10-04', 'status' => 'paid', 'total_payout' => 2200]);
        WeeklyPayoutBatch::create(['week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'status' => 'draft', 'total_payout' => 500]);
        $page = Livewire::test(PayrollOverview::class);
        $points = $page->instance()->payoutTrend();
        $this->assertCount(8, $points);
        $this->assertEquals(2200, array_sum(array_column($points, 'total')));
    }
}

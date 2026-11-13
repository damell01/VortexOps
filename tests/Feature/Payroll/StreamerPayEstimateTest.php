<?php

namespace Tests\Feature\Payroll;

use App\Filament\Widgets\StreamerPayPeriodWidget;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Services\StreamerPayEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A streamer sees what this pay period is on track to pay them, show by show. */
class StreamerPayEstimateTest extends TestCase
{
    use RefreshDatabase;

    private Streamer $streamer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-13 18:00:00');
        $this->user = User::factory()->create();
        $this->streamer = Streamer::create(['name' => 'Ana', 'member_type' => 'streamer', 'streamer_type' => 'in_house', 'status' => 'active',
            'compensation_override_fields' => [], 'payout_type' => 'flat_rate', 'package_rate' => 50, 'user_id' => $this->user->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $title, string $date, string $status = 'reconciled'): Show
    {
        $show = Show::create(['title' => $title, 'show_date' => $date, 'status' => $status, 'is_operational' => true, 'gross_revenue' => 500, 'whatnot_net' => 400]);
        $show->streamers()->attach($this->streamer->id, ['is_primary' => true]);

        return $show;
    }

    public function test_the_period_lists_each_aired_show_with_its_pay(): void
    {
        [$start, $end] = \App\Support\PaySchedule::periodFor(now());
        $this->show('Estimated', $start->copy()->toDateString());
        $onRun = $this->show('On a run', $start->copy()->addDay()->toDateString());
        Payout::create(['show_id' => $onRun->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'flat_rate', 'calculated_payout' => 75, 'status' => 'draft']);
        $paid = $this->show('Paid already', now()->toDateString());
        Payout::create(['show_id' => $paid->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'flat_rate', 'calculated_payout' => 60, 'status' => 'paid']);
        $this->show('Cancelled', now()->toDateString(), 'cancelled');
        $this->show('Last period', $start->copy()->subDays(2)->toDateString());

        $e = app(StreamerPayEstimate::class)->forPeriod($this->streamer);

        $this->assertSame(3, $e['shows']);
        $this->assertSame(50.0, $e['estimate']);
        $this->assertSame(75.0, $e['confirmed']);
        $this->assertSame(60.0, $e['paid']);
        $this->assertSame(185.0, $e['total']);
        $this->assertSame('Paid already', $e['rows'][0]['title']);   // newest first
        $this->assertSame('paid', $e['rows'][0]['state']);
    }

    public function test_the_streamer_sees_it_on_their_dashboard_and_can_step_back(): void
    {
        Role::findOrCreate('streamer', 'web');
        $this->user->assignRole('streamer');
        [$start] = \App\Support\PaySchedule::periodFor(now());
        $this->show('Tuesday Break', $start->copy()->toDateString());
        $this->show('Old Break', $start->copy()->subDays(3)->toDateString());

        $this->actingAs($this->user);
        $this->assertTrue(StreamerPayPeriodWidget::canView());
        Livewire::test(StreamerPayPeriodWidget::class)->assertSee('This pay period')->assertSee('Tuesday Break')->assertSee('$50.00')->assertDontSee('Old Break')
            ->call('shift', -1)->assertSee('Last pay period')->assertSee('Old Break')->assertDontSee('Tuesday Break');
    }
}

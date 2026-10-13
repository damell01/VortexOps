<?php

namespace Tests\Feature\Payouts;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\CreateWeeklyPayoutBatch;
use App\Models\Business;
use App\Models\Payout;
use App\Models\PayRunType;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayRunAutomationService;
use App\Support\AdminModules;
use App\Support\PaymentStructure;
use App\Support\PayRunSchedules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Profit share on its own (monthly) runs while hourly pay and other wages
 * stay weekly; runs of any type for any dates; nothing changes until on.
 */
class PayRunSchedulesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $person;
    private Streamer $streamer;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 18:00:00');
        Setting::set('hours_tracking_since', '2026-09-01');
        Setting::set('show_reports_required_from', '');
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->owner);
        $this->person = User::factory()->create();
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        $defaults = ['payout_type' => 'profit_share', 'payout_cadence' => 'weekly', 'payout_percentage' => 10, 'include_tips' => false, 'hourly_rate' => 15];
        PaymentStructure::saveDefaults('streamer', $defaults);
        foreach (Business::all() as $b) {
            PaymentStructure::saveDefaults('streamer', $defaults, $b->id);
        }
        $this->streamer = Streamer::create([
            'name' => 'Sam', 'user_id' => $this->person->id, 'member_type' => 'streamer',
            'streamer_type' => 'in_house', 'status' => 'active', 'compensation_override_fields' => [],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $date): Show
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id, 'title' => 'Show '.$date, 'show_date' => $date,
            'gross_revenue' => 1000, 'whatnot_net' => 900, 'tips' => 0, 'units_sold' => 10,
            'status' => 'reconciled', 'is_operational' => true, 'created_by' => $this->owner->id,
        ]);
        $show->streamers()->attach($this->streamer->id, ['is_primary' => true]);
        StreamerLogEntry::create([
            'show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => 'admin_approved', 'approval_status' => 'approved',
            'submitted_at' => now(), 'reviewed_at' => now(), 'hours_streamed' => 2, 'hours_review' => 'report', 'product_cost' => 0,
        ]);

        return $show;
    }

    public function test_manual_is_default_even_when_the_old_global_switch_is_on(): void
    {
        Setting::set('payroll_auto_setup_enabled', '1');
        $this->show('2026-10-14');
        $this->artisan('payroll:sync-pay-runs')->assertSuccessful();
        $this->artisan('payroll:sync-pay-runs', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, WeeklyPayoutBatch::count());
        $this->assertSame(0, PayRunType::where('auto_create', true)->count());
    }

    public function test_only_explicitly_opted_in_types_create_runs(): void
    {
        $wages = PayRunType::where('kind', 'wages')->sole();
        $wages->update(['schedule_enabled' => true, 'auto_create' => true]);
        $this->show('2026-10-14');
        $service = app(PayRunAutomationService::class);
        $first = $service->syncAllForWeek('2026-10-14', true, scheduled: true);
        $second = $service->syncAllForWeek('2026-10-14', true, scheduled: true);
        $run = WeeklyPayoutBatch::sole();
        $this->assertSame($wages->id, $run->pay_run_type_id);
        $this->assertSame('wages', $run->kind);
        $this->assertSame(['show_hours'], $run->payouts()->pluck('payout_type')->all(), json_encode(['first' => $first, 'second' => $second, 'lines' => Payout::all()->toArray()]));
        $this->assertEquals(30, (float) $run->total_payout);
    }

    public function test_combined_run_keeps_wages_and_share_as_separate_lines(): void
    {
        $show = $this->show('2026-10-14');
        Livewire::test(CreateWeeklyPayoutBatch::class)
            ->fillForm(['kind' => '', 'week_start' => '2026-10-10', 'week_end' => '2026-10-20'])
            ->call('create')->assertHasNoFormErrors();
        $run = WeeklyPayoutBatch::sole();
        $this->assertNull($run->kind);
        $this->assertEqualsCanonicalizing(['profit_share', 'show_hours'], $run->payouts()->pluck('payout_type')->all());
        $this->assertSame('2026-10-20', $run->week_end->toDateString());
    }

    public function test_named_type_snapshots_coverage_and_name_when_created(): void
    {
        $type = PayRunType::create(['name' => 'October commissions', 'kind' => 'share']);
        $this->show('2026-10-14');
        Livewire::test(CreateWeeklyPayoutBatch::class)
            ->fillForm(['pay_run_type_id' => $type->id, 'week_start' => '2026-10-01', 'week_end' => '2026-10-20'])
            ->call('create')->assertHasNoFormErrors();
        $run = WeeklyPayoutBatch::sole();
        $type->update(['name' => 'Renamed', 'kind' => 'wages']);
        $this->assertSame('share', $run->fresh()->kind);
        $this->assertSame('October commissions', $run->kindLabel());
        $this->assertSame(['profit_share'], $run->payouts()->pluck('payout_type')->all());
    }

    public function test_finalized_pay_is_not_recalculated_by_another_run(): void
    {
        $show = $this->show('2026-10-14');
        $service = app(PayRunAutomationService::class);
        $run = $service->syncWeek('2026-10-14', true, null, 'share')['batch'];
        $run->update(['status' => 'finalized']);
        $line = $run->payouts()->sole();
        $before = $line->getAttributes();
        $show->update(['whatnot_net' => 5000]);
        $service->syncWeek('2026-10-14', true, null, 'wages');
        $this->assertSame($before, $line->fresh()->getAttributes());
    }

    public function test_an_admin_creates_a_run_of_any_type_for_custom_dates(): void
    {
        $this->show('2026-10-03');
        $this->show('2026-10-14');

        Livewire::test(CreateWeeklyPayoutBatch::class)
            ->fillForm(['kind' => 'share', 'dates_mode' => 'custom', 'week_start' => '2026-10-01', 'week_end' => '2026-10-10'])
            ->call('create')
            ->assertHasNoFormErrors();

        $run = WeeklyPayoutBatch::where('kind', 'share')->sole();
        $this->assertSame('2026-10-10', $run->week_end->toDateString());
        $this->assertSame(1, $run->payouts()->count(), 'only the show inside the custom dates');

        // The same type cannot overlap; another type can.
        $this->expectException(\RuntimeException::class);
        WeeklyPayoutBatch::create(['kind' => 'share', 'week_start' => '2026-10-05', 'week_end' => '2026-10-20', 'status' => 'draft']);
    }

    public function test_a_wages_run_may_overlap_a_share_run(): void
    {
        WeeklyPayoutBatch::create(['kind' => 'share', 'week_start' => '2026-10-01', 'week_end' => '2026-10-31', 'status' => 'draft']);
        $wages = WeeklyPayoutBatch::create(['kind' => 'wages', 'week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'status' => 'draft']);

        $this->assertTrue($wages->exists);
    }

    public function test_admin_can_save_a_manual_type_and_disable_its_automation(): void
    {
        Livewire::test(\App\Filament\Pages\PaymentStructures::class)
            ->call('addRunType')
            ->set('run_types.3.name', 'Bonus wages')
            ->set('run_types.3.auto_create', true)
            ->call('save')->assertHasNoErrors();
        $type = PayRunType::where('name', 'Bonus wages')->sole();
        $this->assertFalse($type->schedule_enabled);
        $this->assertFalse($type->auto_create, 'Automatic creation requires a schedule');
        $this->assertSame('wages', $type->kind);
    }

    public function test_schedule_suggests_dates_but_custom_dates_still_work(): void
    {
        $type = PayRunType::where('kind', 'share')->sole();
        $type->update(['schedule_enabled' => true]);
        Livewire::test(CreateWeeklyPayoutBatch::class)
            ->fillForm(['pay_run_type_id' => $type->id, 'dates_mode' => 'schedule', 'week_start' => '2026-09-14'])
            ->call('create')->assertHasNoFormErrors();
        $run = WeeklyPayoutBatch::sole();
        $this->assertSame(['2026-09-01', '2026-09-30'], [$run->week_start->toDateString(), $run->week_end->toDateString()]);
        Livewire::test(CreateWeeklyPayoutBatch::class)
            ->fillForm(['pay_run_type_id' => $type->id, 'week_start' => '2026-10-02', 'week_end' => '2026-10-09'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('2026-10-09', WeeklyPayoutBatch::orderByDesc('id')->first()->week_end->toDateString());
        $this->assertFalse($type->auto_create);
    }

    public function test_schedule_periods(): void
    {
        $this->assertSame(['2026-10-15', '2026-11-14'], $this->period('share', '2026-10-20', ['frequency' => 'monthly', 'anchor' => '2026-01-15']));
        $this->assertSame(['2026-09-15', '2026-10-14'], $this->period('share', '2026-10-02', ['frequency' => 'monthly', 'anchor' => '2026-01-15']));
        $this->assertSame(['2026-10-16', '2026-10-31'], $this->period('share', '2026-10-20', ['frequency' => 'semimonthly']));
        $a = $this->period('wages', '2026-10-13', ['frequency' => 'biweekly', 'anchor' => '2026-10-05']);
        $b = $this->period('wages', '2026-10-17', ['frequency' => 'biweekly', 'anchor' => '2026-10-05']);
        $this->assertSame(['2026-10-05', '2026-10-18'], $a);
        $this->assertSame($a, $b);
    }

    private function period(string $kind, string $date, array $cfg): array
    {
        return array_map(fn ($d) => $d->toDateString(), PayRunSchedules::standardPeriod($kind, $date, $cfg));
    }
}

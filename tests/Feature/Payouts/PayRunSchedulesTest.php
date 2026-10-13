<?php

namespace Tests\Feature\Payouts;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\CreateWeeklyPayoutBatch;
use App\Models\Business;
use App\Models\Payout;
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

    private function separate(array $extra = []): void
    {
        PayRunSchedules::save(array_replace_recursive([
            'separate' => true,
            'wages' => ['frequency' => 'weekly', 'auto' => true],
            'share' => ['frequency' => 'monthly', 'auto' => true],
        ], $extra));
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

    public function test_off_by_default_one_run_pays_everything_as_before(): void
    {
        $this->show('2026-10-14');
        $batch = app(PayRunAutomationService::class)->syncAllForWeek('2026-10-14')[0]['batch'];

        $this->assertNull($batch->kind);
        $this->assertSame(['profit_share'], $batch->payouts()->pluck('payout_type')->all());
        $this->assertStringContainsString('hourly ($15/hr × 2hrs)', $batch->payouts()->first()->calculation_notes);
    }

    public function test_separate_runs_pay_hours_weekly_and_profit_share_monthly(): void
    {
        $this->separate();
        $this->show('2026-10-06');
        $this->show('2026-10-14');
        TimeEntry::create(['user_id' => $this->person->id, 'reason' => 'cleanup', 'status' => 'ok', 'clocked_in_at' => '2026-10-13 14:00:00', 'clocked_out_at' => '2026-10-13 15:00:00']);

        $service = app(PayRunAutomationService::class);
        $service->syncAllForWeek('2026-10-14');
        $service->syncWeek('2026-10-06', true, null, 'wages');

        $share = WeeklyPayoutBatch::where('kind', 'share')->sole();
        $this->assertSame(['2026-10-01', '2026-10-31'], [$share->week_start->toDateString(), $share->week_end->toDateString()]);
        $this->assertSame(['profit_share', 'profit_share'], $share->payouts()->pluck('payout_type')->all());
        $this->assertStringNotContainsString('hourly', $share->payouts()->first()->calculation_notes);

        $week = WeeklyPayoutBatch::where('kind', 'wages')->whereDate('week_start', '2026-10-12')->sole();
        $this->assertEqualsCanonicalizing(['show_hours', 'clocked_time'], $week->payouts()->pluck('payout_type')->all());
        $this->assertEquals(30.0, (float) $week->payouts()->where('payout_type', 'show_hours')->value('calculated_payout'));

        $this->assertSame(3, WeeklyPayoutBatch::count(), 'two weekly wage runs and one monthly share run');
    }

    public function test_turning_it_on_converts_this_weeks_draft_run_and_moves_share_lines(): void
    {
        $this->show('2026-10-14');
        $service = app(PayRunAutomationService::class);
        $legacy = $service->syncAllForWeek('2026-10-14')[0]['batch'];

        $this->separate();
        $service->syncAllForWeek('2026-10-14');

        $legacy->refresh();
        $this->assertSame('wages', $legacy->kind);
        $this->assertSame(['show_hours'], $legacy->payouts()->pluck('payout_type')->all());
        $this->assertSame(['profit_share'], WeeklyPayoutBatch::where('kind', 'share')->sole()->payouts()->pluck('payout_type')->all());
    }

    public function test_a_type_set_not_to_auto_create_waits_for_an_admin(): void
    {
        $this->separate(['share' => ['auto' => false]]);
        $this->show('2026-10-14');

        app(PayRunAutomationService::class)->syncAllForWeek('2026-10-14', true, scheduled: true);

        $this->assertSame(0, WeeklyPayoutBatch::where('kind', 'share')->count());
        $this->assertSame(1, WeeklyPayoutBatch::where('kind', 'wages')->count());
    }

    public function test_an_admin_creates_a_run_of_any_type_for_custom_dates(): void
    {
        $this->separate(['share' => ['auto' => false]]);
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

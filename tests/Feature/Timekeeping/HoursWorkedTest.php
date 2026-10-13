<?php

namespace Tests\Feature\Timekeeping;

use App\Filament\Pages\TimeAttendance;
use App\Livewire\ClockWidget;
use App\Models\Business;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\TimeEntry;
use App\Models\TimeEntryChange;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use App\Services\PayRunAutomationService;
use App\Services\TimeTrackingService;
use App\Support\PaymentStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hours Worked: payroll pays approved hours only, never a show's Whatnot
 * length; clock and report are compared, not added; anything edited, added
 * by hand, left open or overlapping waits for an admin.
 */
class HoursWorkedTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $person;
    private Streamer $streamer;
    private WhatnotChannel $channel;
    private TimeTrackingService $time;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 18:00:00');
        Setting::set('hours_tracking_since', '2026-10-01');
        Setting::set('show_reports_required_from', '');

        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->person = User::factory()->create(['name' => 'Sam Streamer', 'timezone' => 'America/Chicago']);
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        PaymentStructure::saveDefaults('streamer', [
            'payout_type' => 'profit_share', 'payout_cadence' => 'weekly', 'payout_percentage' => 10,
            'include_tips' => false, 'hourly_rate' => 15,
        ]);
        foreach (Business::all() as $business) {
            PaymentStructure::saveDefaults('streamer', [
                'payout_type' => 'profit_share', 'payout_cadence' => 'weekly', 'payout_percentage' => 10,
                'include_tips' => false, 'hourly_rate' => 15,
            ], $business->id);
        }

        $this->streamer = Streamer::create([
            'name' => 'Sam', 'user_id' => $this->person->id, 'member_type' => 'streamer',
            'streamer_type' => 'in_house', 'status' => 'active', 'compensation_override_fields' => [],
        ]);
        $this->time = app(TimeTrackingService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(array $extra = []): Show
    {
        $show = Show::create($extra + [
            'whatnot_channel_id' => $this->channel->id,
            'title' => 'Tuesday Breaks',
            'show_date' => '2026-10-14',
            'gross_revenue' => 1000,
            'whatnot_net' => 900,
            'tips' => 0,
            'units_sold' => 10,
            'show_duration' => 240,
            'status' => 'reconciled',
            'created_by' => $this->owner->id,
        ]);
        $show->streamers()->attach($this->streamer->id, ['is_primary' => true]);

        return $show;
    }

    private function report(Show $show, ?float $hours): StreamerLogEntry
    {
        return StreamerLogEntry::create([
            'show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => 'admin_approved',
            'hours_streamed' => $hours, 'submitted_at' => now(), 'product_cost' => 0,
        ]);
    }

    private function clocked(?Show $show, string $in, string $out, array $extra = []): TimeEntry
    {
        return TimeEntry::create($extra + [
            'user_id' => $this->person->id, 'show_id' => $show?->id, 'source' => 'clock', 'status' => 'ok',
            'clocked_in_at' => $in, 'clocked_out_at' => $out,
        ]);
    }

    public function test_clocking_in_with_a_show_and_out_is_paid_as_clocked(): void
    {
        $show = $this->show();
        $entry = $this->time->clockIn($this->person, $show->id);
        Carbon::setTestNow(now()->addMinutes(150));
        $entry = $this->time->clockOut($this->person);

        $this->assertSame('ok', $entry->status);
        $this->assertSame(150, $entry->payableMinutes());
        $this->assertSame($show->id, $entry->show_id);
        $this->assertSame(2, TimeEntryChange::where('time_entry_id', $entry->id)->count());
    }

    public function test_a_person_cannot_link_a_show_that_is_not_theirs(): void
    {
        $other = Show::create(['whatnot_channel_id' => $this->channel->id, 'title' => 'Not mine', 'show_date' => '2026-10-14', 'status' => 'reconciled', 'created_by' => $this->owner->id]);

        $this->expectException(ValidationException::class);
        $this->time->clockIn($this->person, $other->id);
    }

    public function test_editing_your_own_time_is_logged_and_flagged_with_an_optional_reason(): void
    {
        $entry = $this->clocked(null, '2026-10-14 14:00:00', '2026-10-14 16:00:00', ['reason' => 'packing']);

        // No reason given: still allowed, still logged and flagged.
        $quiet = $this->time->edit($entry, $this->person, Carbon::parse('2026-10-14 14:00:00'), Carbon::parse('2026-10-14 16:15:00'), '  ');
        $this->assertSame('flagged', $quiet->status);
        $this->assertNull(TimeEntryChange::where('time_entry_id', $entry->id)->where('action', 'edited')->latest('id')->value('reason'));

        $entry = $this->time->edit($entry->fresh(), $this->person, Carbon::parse('2026-10-14 13:00:00'), Carbon::parse('2026-10-14 16:00:00'), 'Forgot to clock in when I started packing', null, 'packing');

        $this->assertSame('flagged', $entry->status);
        $this->assertTrue($entry->hasFlag('edited'));
        $this->assertSame(0, $entry->payableMinutes(), 'flagged time is not paid until reviewed');
        $change = TimeEntryChange::where('time_entry_id', $entry->id)->where('action', 'edited')->latest('id')->firstOrFail();
        $this->assertSame(135, $change->old_values['minutes']);
        $this->assertSame(180, $change->new_values['minutes']);
        $this->assertSame('Forgot to clock in when I started packing', $change->reason);
        $this->assertSame($this->person->id, $change->user_id);
    }

    public function test_an_admin_review_approves_a_corrected_time(): void
    {
        $entry = $this->clocked(null, '2026-10-14 14:00:00', '2026-10-14 17:00:00', ['status' => 'flagged', 'flags' => ['edited']]);

        $entry = $this->time->review($entry, $this->owner, 'corrected', 150, 'Lunch was not taken off');

        $this->assertSame('approved', $entry->status);
        $this->assertSame(150, $entry->payableMinutes());
        $this->assertSame($this->owner->id, $entry->approved_by);
    }

    public function test_overlapping_entries_are_both_flagged(): void
    {
        $first = $this->clocked(null, '2026-10-14 14:00:00', '2026-10-14 16:00:00', ['reason' => 'packing']);
        $second = $this->time->addManual($this->person, $this->person, Carbon::parse('2026-10-14 15:00:00'), Carbon::parse('2026-10-14 17:00:00'), 'Meeting I forgot to clock', null, 'meeting');

        $this->assertTrue($second->hasFlag('overlap'));
        $this->assertTrue($second->hasFlag('manual'));
        $this->assertTrue($first->fresh()->hasFlag('overlap'));
    }

    public function test_an_entry_open_past_twelve_hours_is_flagged_once_and_people_are_told(): void
    {
        $entry = TimeEntry::create(['user_id' => $this->person->id, 'clocked_in_at' => now()->subHours(13), 'source' => 'clock', 'status' => 'ok']);

        $this->artisan('timekeeping:missed-clock-outs')->assertSuccessful();
        $this->artisan('timekeeping:missed-clock-outs')->assertSuccessful();

        $entry->refresh();
        $this->assertSame('flagged', $entry->status);
        $this->assertTrue($entry->hasFlag('missed_clock_out'));
        $this->assertSame(1, $this->person->notifications()->count());

        // The admin settles it by entering the time actually worked.
        $entry = $this->time->review($entry, $this->owner, 'corrected', 300, 'Worked 5 hours, forgot to clock out');
        $this->assertSame(300, $entry->payableMinutes());
        $this->assertNotNull($entry->clocked_out_at);
    }

    public function test_report_hours_and_clock_are_compared_and_an_admin_settles_the_gap(): void
    {
        $show = $this->show();
        $report = $this->report($show, 4.0);
        $this->clocked($show, '2026-10-14 19:00:00', '2026-10-14 22:00:00');

        $issues = $this->time->showHoursIssues();
        $this->assertCount(1, $issues);
        $this->assertSame('mismatch', $issues[0]['kind']);

        $this->time->resolveShowHours($report, $this->owner, 'clock', null, 'Clock is right');

        $this->assertEquals(3.0, (float) $report->fresh()->hours_streamed);
        $this->assertCount(0, $this->time->showHoursIssues());

        // A later change to the hours puts it back in front of an admin.
        $report->fresh()->update(['hours_streamed' => 5]);
        $this->assertCount(1, $this->time->showHoursIssues());
    }

    public function test_a_small_difference_needs_no_review(): void
    {
        $show = $this->show();
        $this->report($show, 3.0);
        $this->clocked($show, '2026-10-14 19:00:00', '2026-10-14 22:10:00');

        $this->assertCount(0, $this->time->showHoursIssues());
    }

    public function test_payroll_never_pays_the_whatnot_show_length(): void
    {
        $show = $this->show(); // 240 minutes on Whatnot
        $this->report($show, null);

        [$payout] = app(PayoutService::class)->calculateForShow($show->load('streamers'));

        $this->assertNull($payout->hours_worked);
        $this->assertStringContainsString('Hours missing', $payout->calculation_notes);
        $this->assertStringNotContainsString('hourly ($15/hr', $payout->calculation_notes);
    }

    public function test_clocked_time_on_the_show_is_paid_when_the_report_has_no_hours(): void
    {
        $show = $this->show();
        $this->report($show, null);
        $this->clocked($show, '2026-10-14 19:00:00', '2026-10-14 21:30:00');

        [$payout] = app(PayoutService::class)->calculateForShow($show->load('streamers'));

        $this->assertEquals(2.5, (float) $payout->hours_worked);
        $line = \App\Models\Payout::where('show_id', $show->id)->where('payout_type', 'show_hours')->sole();
        $this->assertEquals(37.5, (float) $line->calculated_payout);
        $this->assertStringNotContainsString('hourly', $payout->calculation_notes);
    }

    public function test_sign_off_names_unsettled_hours_and_flagged_time(): void
    {
        $show = $this->show();
        $this->report($show, null);
        $this->clocked(null, '2026-10-14 14:00:00', '2026-10-14 15:00:00', ['status' => 'flagged', 'flags' => ['manual'], 'reason' => 'cleanup']);

        $batch = app(PayRunAutomationService::class)->syncWeek('2026-10-14')['batch'];
        $problems = app(PayoutService::class)->signOffProblems($batch);

        $this->assertTrue(collect($problems)->contains(fn ($p) => str_contains($p, 'Hours Worked: Hours missing')));
        $this->assertTrue(collect($problems)->contains(fn ($p) => str_contains($p, '1 time entry in this period waits for review')));
    }

    public function test_time_not_on_a_show_becomes_one_hourly_line_on_the_pay_run(): void
    {
        $show = $this->show();
        $this->report($show, 3.0);
        $this->clocked(null, '2026-10-13 14:00:00', '2026-10-13 15:30:00', ['reason' => 'preparation']);
        $this->clocked(null, '2026-10-14 23:00:00', '2026-10-14 23:45:00', ['reason' => 'cleanup']);
        $this->clocked(null, '2026-10-15 10:00:00', '2026-10-15 11:00:00', ['reason' => 'packing', 'status' => 'flagged', 'flags' => ['manual']]);
        $this->clocked($show, '2026-10-14 19:00:00', '2026-10-14 22:00:00');

        $service = app(PayRunAutomationService::class);
        $batch = $service->syncWeek('2026-10-14')['batch'];

        $line = Payout::where('weekly_payout_batch_id', $batch->id)->where('payout_type', 'clocked_time')->sole();
        $this->assertNull($line->show_id);
        $this->assertEquals(2.25, (float) $line->hours_worked); // flagged packing hour stays out
        $this->assertEquals(33.75, (float) $line->calculated_payout);
        $this->assertStringContainsString('Preparation 1h 30m', $line->calculation_notes);

        // Syncing again updates the one line rather than adding another.
        $service->syncWeek('2026-10-14');
        $this->assertSame(1, Payout::where('payout_type', 'clocked_time')->count());
    }

    public function test_the_clock_widget_clocks_in_with_a_reason_and_out(): void
    {
        $this->actingAs($this->person);

        Livewire::test(ClockWidget::class)
            ->set('reason', 'preparation')->set('note', 'Sorting slabs')
            ->call('clockIn')
            ->assertSee('Clocked in');

        $entry = TimeEntry::sole();
        $this->assertSame('preparation', $entry->reason);
        $this->assertSame('Sorting slabs', $entry->notes);

        Carbon::setTestNow(now()->addHour());
        Livewire::test(ClockWidget::class)->call('clockOut');
        $this->assertNotNull($entry->fresh()->clocked_out_at);
    }

    public function test_the_report_form_does_not_fill_hours_from_whatnot(): void
    {
        $show = $this->show();
        $this->actingAs($this->owner);

        $this->report($show, null)->update(['status' => 'pending', 'submitted_at' => null]);
        $page = Livewire::test(\App\Filament\Pages\EndOfStreamForm::class, ['showId' => (string) $show->id]);
        $this->assertSame($show->id, $page->get('show')?->id);
        $page->call('loadDetails');

        $this->assertSame('', $page->get('hoursStreamed'));
        $this->assertNull(StreamerLogEntry::where('show_id', $show->id)->value('hours_streamed'));
    }

    public function test_the_review_queue_settles_an_item_from_the_side_panel(): void
    {
        $entry = $this->clocked(null, '2026-10-14 14:00:00', '2026-10-14 16:00:00', ['status' => 'flagged', 'flags' => ['edited'], 'reason' => 'cleanup']);
        $this->actingAs($this->owner);

        Livewire::test(TimeAttendance::class)
            ->assertSee('Sam Streamer')
            ->call('openPanel', 'entry:'.$entry->id)
            ->assertSee('Approve clock time')
            ->set('decision', 'clock')
            ->call('saveReview')
            ->assertHasNoErrors();

        $this->assertSame('approved', $entry->fresh()->status);
        $this->assertSame(0, TimeAttendance::reviewCount());
    }

    public function test_streamers_cannot_open_time_and_attendance(): void
    {
        $this->actingAs($this->person);

        $this->assertFalse(TimeAttendance::canAccess());
    }

    public function test_the_show_picker_lists_only_your_shows_and_admins_can_see_all(): void
    {
        $mine = $this->show();
        $other = Show::create(['whatnot_channel_id' => $this->channel->id, 'title' => 'Someone else', 'show_date' => '2026-10-14', 'status' => 'reconciled', 'created_by' => $this->owner->id]);

        $forStreamer = collect($this->time->pickerShows($this->person));
        $this->assertSame([$mine->id], $forStreamer->pluck('id')->all());
        $this->assertSame('Yesterday', $forStreamer->first()['day']);

        $forAdmin = collect($this->time->pickerShows($this->owner))->keyBy('id');
        $this->assertTrue($forAdmin->has($other->id));
        $this->assertFalse($forAdmin[$other->id]['mine']);

        $this->actingAs($this->person);
        Livewire::test(ClockWidget::class)
            ->call('pickShow', $mine->id)
            ->assertSee('Tuesday Breaks')
            ->call('clockIn');
        $this->assertSame($mine->id, TimeEntry::sole()->show_id);
    }

    public function test_admins_can_delete_an_entry_and_it_is_logged(): void
    {
        $entry = $this->clocked(null, '2026-10-14 14:00:00', '2026-10-14 15:00:00', ['reason' => 'cleanup']);

        $this->actingAs($this->owner);
        Livewire::test(\App\Filament\Pages\Timekeeping::class)
            ->callAction('deleteEntry', arguments: ['entry' => $entry->id]);

        $this->assertModelMissing($entry);
        $this->assertSame('deleted', TimeEntryChange::latest('id')->value('action'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->time->delete($this->clocked(null, '2026-10-14 16:00:00', '2026-10-14 17:00:00'), $this->person);
    }
    public function test_streamer_can_select_and_clock_in_to_an_assigned_upcoming_show(): void
    {
        $upcoming = $this->show();
        $upcoming->update(['show_date' => '2026-11-20', 'status' => 'draft']);
        $other = \App\Models\Show::create([
            'whatnot_channel_id' => $upcoming->whatnot_channel_id, 'title' => 'Someone else’s upcoming show',
            'show_date' => '2026-11-21', 'status' => 'draft',
        ]);
        $this->actingAs($this->person);
        $ids = collect($this->time->pickerShows($this->person))->pluck('id')->all();
        $this->assertContains($upcoming->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $entry = $this->time->clockIn($this->person, $upcoming->id);
        $this->assertSame($upcoming->id, $entry->show_id);
    }

}

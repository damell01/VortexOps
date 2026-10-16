<?php

namespace Tests\Feature\Timekeeping;

use App\Filament\Pages\Timekeeping;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Hours written on show reports show up on Timekeeping beside clocked time, for any period. */
class ReportHoursOnTimekeepingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $jayUser;
    private Streamer $jay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-16 12:00'));
        config(['app.owner_email' => 'owner@vortex.com']);
        Setting::set('enabled_admin_modules', json_encode(['timekeeping']));
        AdminModules::flushMemo();
        $this->owner = User::factory()->create(['email' => 'owner@vortex.com']);
        $this->jayUser = User::factory()->create(['name' => 'Jay']);
        $this->jay = Streamer::create(['user_id' => $this->jayUser->id, 'name' => 'Jay', 'member_type' => 'streamer', 'status' => 'active']);
    }

    private function report(string $date, float $hours, string $title): StreamerLogEntry
    {
        $show = Show::create(['title' => $title, 'show_date' => $date, 'start_time' => '19:00', 'status' => 'draft']);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->jay->id, 'status' => 'admin_approved', 'hours_streamed' => $hours]);
        $entry->forceFill(['submitted_at' => now()])->save();

        return $entry;
    }

    public function test_report_hours_sit_beside_clocked_time_for_the_chosen_period(): void
    {
        $this->report('2026-10-14', 3.5, 'Wednesday Breaks');
        $this->report('2026-10-02', 2, 'Old Friday Show');
        TimeEntry::create(['user_id' => $this->jayUser->id, 'clocked_in_at' => '2026-10-13 15:00:00', 'clocked_out_at' => '2026-10-13 17:00:00']);

        $page = Livewire::actingAs($this->owner)->test(Timekeeping::class);
        $page->assertSee('Wednesday Breaks')->assertSee('From the show report');
        $this->assertSame(2, $page->instance()->entries->total(), 'this week: one clock entry and one report');

        $page->call('setPeriodMode', 'this_month');
        $this->assertSame('this_month', $page->get('periodMode'));
        $this->assertSame(3, $page->instance()->entries->total());
        $this->assertContains('Old Friday Show', $page->instance()->entries->getCollection()->map(fn ($row) => $row['report']?->show?->title)->filter()->all());
    }

    public function test_team_hours_add_report_hours_to_clocked_hours(): void
    {
        $this->report('2026-10-14', 3.5, 'Wednesday Breaks');
        TimeEntry::create(['user_id' => $this->jayUser->id, 'clocked_in_at' => '2026-10-13 15:00:00', 'clocked_out_at' => '2026-10-13 17:00:00']);

        $team = Livewire::actingAs($this->owner)->test(Timekeeping::class)->call('setTab', 'team')->instance()->teamHours;

        $this->assertCount(1, $team, 'one person, not two rows for the same Jay');
        $this->assertSame(['Jay', 120, 210, 330], [$team[0]['name'], $team[0]['minutes'], $team[0]['report_minutes'], $team[0]['total']]);
    }

    public function test_a_streamer_sees_only_their_own_report_hours(): void
    {
        $this->report('2026-10-14', 3.5, 'Wednesday Breaks');
        $other = Streamer::create(['name' => 'Sam', 'member_type' => 'streamer', 'status' => 'active']);
        $show = Show::create(['title' => 'Sams Show', 'show_date' => '2026-10-14', 'status' => 'draft']);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $other->id, 'status' => 'admin_approved', 'hours_streamed' => 2])->forceFill(['submitted_at' => now()])->save();

        $this->actingAs($this->jayUser);
        $titles = app(Timekeeping::class)->reportHours('2026-10-12', '2026-10-18')->map(fn ($r) => $r->show->title)->all();
        $this->assertSame(['Wednesday Breaks'], $titles);
    }
}

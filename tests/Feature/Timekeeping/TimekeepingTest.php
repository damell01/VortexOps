<?php

namespace Tests\Feature\Timekeeping;

use App\Filament\Pages\Timekeeping;
use App\Models\Setting;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimekeepingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $owner;
    private User $regular;

    protected function setUp(): void
    {
        parent::setUp();

        AdminModules::flushMemo();

        config(['app.owner_email' => 'owner@vortex.com']);

        Setting::set('enabled_admin_modules', json_encode(['timekeeping']));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->owner   = User::factory()->create(['email' => 'owner@vortex.com']);
        $this->admin   = User::factory()->create(['email' => 'admin@vortex.com']);
        $this->regular = User::factory()->create(['email' => 'employee@vortex.com']);

        $this->admin->assignRole('admin');
    }

    // ── canAccess ─────────────────────────────────────────────────────────────

    public function test_admin_can_access_timekeeping_when_module_enabled(): void
    {
        $this->actingAs($this->admin);
        $this->assertTrue(Timekeeping::canAccess());
    }

    public function test_owner_can_access_timekeeping_when_module_enabled(): void
    {
        // Owner check is email-based (isOwner()), independent of Spatie roles
        $this->actingAs($this->owner);
        $this->assertTrue(Timekeeping::canAccess());
    }

    public function test_regular_user_cannot_access_timekeeping(): void
    {
        $this->actingAs($this->regular);
        $this->assertFalse(Timekeeping::canAccess());
    }

    public function test_admin_cannot_access_when_module_disabled(): void
    {
        Setting::set('enabled_admin_modules', json_encode([]));
        AdminModules::flushMemo();

        $this->actingAs($this->admin);
        $this->assertFalse(Timekeeping::canAccess());
    }

    // ── TimeEntry model ───────────────────────────────────────────────────────

    public function test_format_minutes_shows_hours_and_minutes(): void
    {
        $this->assertEquals('1h 30m', TimeEntry::formatMinutes(90));
        $this->assertEquals('2h 0m',  TimeEntry::formatMinutes(120));
        $this->assertEquals('45m',    TimeEntry::formatMinutes(45));
        $this->assertEquals('0m',     TimeEntry::formatMinutes(0));
    }

    public function test_duration_minutes_attribute_computed_from_clocked_times(): void
    {
        $entry = TimeEntry::create([
            'user_id'        => $this->admin->id,
            'clocked_in_at'  => Carbon::parse('2026-07-01 09:00:00'),
            'clocked_out_at' => Carbon::parse('2026-07-01 10:30:00'),
        ]);

        $this->assertEquals(90, $entry->duration_minutes);
    }

    public function test_duration_minutes_is_null_when_still_open(): void
    {
        $entry = TimeEntry::create([
            'user_id'       => $this->admin->id,
            'clocked_in_at' => now(),
        ]);

        $this->assertNull($entry->duration_minutes);
    }

    public function test_is_open_attribute(): void
    {
        $open   = TimeEntry::create(['user_id' => $this->admin->id, 'clocked_in_at' => now()]);
        $closed = TimeEntry::create(['user_id' => $this->admin->id, 'clocked_in_at' => now()->subHour(), 'clocked_out_at' => now()]);

        $this->assertTrue($open->is_open);
        $this->assertFalse($closed->is_open);
    }

    // ── clock in / out ────────────────────────────────────────────────────────

    public function test_clock_in_creates_time_entry(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Timekeeping::class)
            ->set('note', 'Packing orders')
            ->call('clockIn');

        $this->assertDatabaseHas('time_entries', [
            'user_id'        => $this->admin->id,
            'notes'          => 'Packing orders',
            'clocked_out_at' => null,
        ]);
    }

    public function test_clock_in_clears_note_after_clocking_in(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(Timekeeping::class)
            ->set('note', 'Some task')
            ->call('clockIn');

        $component->assertSet('note', '');
    }

    public function test_clock_in_prevents_double_clock_in(): void
    {
        $this->actingAs($this->admin);

        TimeEntry::create([
            'user_id'       => $this->admin->id,
            'clocked_in_at' => now()->subHour(),
        ]);

        Livewire::test(Timekeeping::class)->call('clockIn');

        $this->assertEquals(1, TimeEntry::where('user_id', $this->admin->id)->count());
    }

    public function test_clock_out_closes_open_entry(): void
    {
        $this->actingAs($this->admin);

        TimeEntry::create([
            'user_id'       => $this->admin->id,
            'clocked_in_at' => now()->subHour(),
        ]);

        Livewire::test(Timekeeping::class)->call('clockOut');

        $entry = TimeEntry::where('user_id', $this->admin->id)->first();
        $this->assertNotNull($entry->clocked_out_at);
    }

    public function test_clock_out_when_not_clocked_in_does_not_create_entry(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Timekeeping::class)->call('clockOut');

        $this->assertEquals(0, TimeEntry::count());
    }

    // ── entry scoping ─────────────────────────────────────────────────────────

    public function test_admin_sees_the_whole_teams_entries(): void
    {
        $other = User::factory()->create();

        TimeEntry::create(['user_id' => $this->admin->id, 'clocked_in_at' => now()->subHour(), 'clocked_out_at' => now()]);
        TimeEntry::create(['user_id' => $other->id,       'clocked_in_at' => now()->subHour(), 'clocked_out_at' => now()]);

        $this->actingAs($this->admin);

        $component = Livewire::test(Timekeeping::class);
        $entries   = $component->instance()->getEntriesProperty();

        $this->assertEquals(2, $entries->total());
    }

    public function test_owner_sees_all_entries(): void
    {
        TimeEntry::create(['user_id' => $this->admin->id,   'clocked_in_at' => now()->subHour(), 'clocked_out_at' => now()]);
        TimeEntry::create(['user_id' => $this->regular->id, 'clocked_in_at' => now()->subHour(), 'clocked_out_at' => now()]);

        $this->actingAs($this->owner);

        $component = Livewire::test(Timekeeping::class);
        $entries   = $component->instance()->getEntriesProperty();

        $this->assertEquals(2, $entries->total());
    }

    public function test_team_summary_returned_for_owner_and_admin(): void
    {
        // Anchor inside the current week so the summary's week filter can't drop
        // it when the suite runs just after midnight on the first day of the week.
        $in = now()->startOfWeek()->addHours(6);
        TimeEntry::create(['user_id' => $this->admin->id, 'clocked_in_at' => $in, 'clocked_out_at' => $in->copy()->addHour()]);

        $this->actingAs($this->admin);
        $adminSummary = Livewire::test(Timekeeping::class)->instance()->getTeamSummaryProperty();
        $this->assertNotEmpty($adminSummary);

        $this->actingAs($this->owner);
        $ownerSummary = Livewire::test(Timekeeping::class)->instance()->getTeamSummaryProperty();
        $this->assertNotEmpty($ownerSummary);
    }
}

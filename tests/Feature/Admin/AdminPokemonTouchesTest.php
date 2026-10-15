<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** Eevee/Snorlax when nothing needs attention, and the inbox Poké Ball. */
class AdminPokemonTouchesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_eevee_cheers_on_a_weekday_and_snorlax_naps_at_the_weekend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 11:00', config('app.timezone')));
        Livewire::test(NeedsAttentionWidget::class)->assertSee('eevee.gif')->assertSee('Eevee approves');

        Carbon::setTestNow(Carbon::parse('2026-10-17 11:00', config('app.timezone')));
        Livewire::test(NeedsAttentionWidget::class)->assertSee('snorlax.gif')->assertSee('taking a nap');
    }

    public function test_the_inbox_ball_counts_this_weeks_cleared_reports_against_those_waiting(): void
    {
        $streamer = Streamer::create(['name' => 'Jay', 'status' => 'active', 'member_type' => 'streamer']);
        $report = function (string $status, ?string $reviewedAt = null) use ($streamer) {
            $show = Show::create(['title' => 'Show ' . uniqid(), 'show_date' => now()->toDateString(), 'status' => 'draft']);
            return StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => $status, 'reviewed_at' => $reviewedAt, 'submitted_at' => now()]);
        };

        $page = Livewire::test(ListStreamerLogEntries::class);
        $this->assertNull($page->instance()->catchProgress(), 'Nothing either way, so no ball');

        $report('admin_approved', now()->subDay());
        $report('admin_approved', now()->subDays(20)); // too long ago to count
        $report('streamer_reviewed');
        $report('streamer_reviewed');

        $this->assertSame(['cleared' => 1, 'waiting' => 2, 'total' => 3, 'percent' => 33], Livewire::test(ListStreamerLogEntries::class)->instance()->catchProgress());
        Livewire::test(ListStreamerLogEntries::class)->assertSee('1/3 caught');

        StreamerLogEntry::where('status', 'streamer_reviewed')->update(['status' => 'admin_approved', 'reviewed_at' => now()]);
        Livewire::test(ListStreamerLogEntries::class)->assertSee('All caught!');
    }
}

<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** A show is live between its start and end — the same rule the Shows calendar uses. */
class LiveShowTest extends TestCase
{
    use RefreshDatabase;

    private function show(array $attributes): Show
    {
        $owner = User::firstWhere('email', config('app.owner_email')) ?? User::factory()->create(['email' => config('app.owner_email')]);
        $channel = WhatnotChannel::firstOrCreate(['name' => 'Vortex Breaks'], ['status' => 'active']);

        return Show::create($attributes + [
            'whatnot_channel_id' => $channel->id, 'title' => 'Friday Break', 'gross_revenue' => 0, 'whatnot_net' => 0,
            'tips' => 0, 'status' => 'pending_review', 'is_operational' => true, 'created_by' => $owner->id,
        ]);
    }

    public function test_live_between_start_and_end(): void
    {
        $show = $this->show(['show_date' => '2026-10-14', 'start_time' => '2026-10-14 18:00:00', 'end_time' => '2026-10-14 20:00:00']);

        $this->assertSame('upcoming', $show->scheduleStateAt(Carbon::parse('2026-10-14 17:59:00')));
        $this->assertTrue($show->isLive(Carbon::parse('2026-10-14 18:30:00')));
        $this->assertSame('done', $show->scheduleStateAt(Carbon::parse('2026-10-14 20:01:00')));
    }

    public function test_a_late_night_stream_is_live_after_midnight_on_its_show_date(): void
    {
        // Show date Oct 14, starting 1am — that is the night of the 14th, i.e. early on the 15th.
        $show = $this->show(['show_date' => '2026-10-14', 'start_time' => '2026-10-14 01:00:00', 'show_duration' => 120]);

        $this->assertTrue($show->isLive(Carbon::parse('2026-10-15 01:30:00')));
        $this->assertFalse($show->isLive(Carbon::parse('2026-10-14 01:30:00')));
    }

    public function test_cancelled_and_untimed_shows_are_never_live(): void
    {
        $now = Carbon::parse('2026-10-14 18:30:00');

        $this->assertFalse($this->show(['show_date' => '2026-10-14', 'start_time' => '2026-10-14 18:00:00', 'status' => 'cancelled'])->isLive($now));
        $this->assertFalse($this->show(['show_date' => '2026-10-14'])->isLive($now));
    }

    public function test_the_dashboard_counts_shows_live_now(): void
    {
        Carbon::setTestNow('2026-10-14 18:30:00');
        $owner = User::factory()->create(['email' => config('app.owner_email'), 'timezone' => 'UTC']);
        $this->show(['show_date' => '2026-10-14', 'start_time' => '2026-10-14 18:00:00', 'end_time' => '2026-10-14 20:00:00']);

        $this->actingAs($owner)->get('/admin')->assertOk()->assertSee('Live now')->assertSee('vx-live-dot', false);

        Carbon::setTestNow();
    }
}

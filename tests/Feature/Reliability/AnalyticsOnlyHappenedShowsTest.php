<?php

namespace Tests\Feature\Reliability;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Analytics backfills only look at shows from today and earlier. end_time and
 * start_time are TIME columns; comparing them with a full date-time is a text
 * comparison that is always true, so every future show with a time was
 * treated as ended (production picked "TBD With Matt" on Dec 31).
 */
class AnalyticsOnlyHappenedShowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_shows_that_have_happened_are_ready_for_analytics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 21:30', 'America/Chicago')->utc()); // 9:30pm Central
        $ch = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        $make = fn ($title, $date, $start = null, $end = null) => Show::create(['whatnot_channel_id' => $ch->id, 'title' => $title,
            'show_date' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => 'draft']);

        $yesterday = $make('Yesterday', '2026-10-11', '20:00:00', '22:00:00');
        $endedToday = $make('Ended today', '2026-10-12', '18:00:00', '20:00:00');
        $laterToday = $make('Later today', '2026-10-12', '22:00:00', '23:30:00');
        $future = $make('TBD With Matt', '2026-12-31', '19:00:00', '21:00:00');
        $futureNoTime = $make('Next week', '2026-10-19');

        $ready = Show::query()->readyForAnalytics()->pluck('title')->sort()->values()->all();

        $this->assertSame(['Ended today', 'Yesterday'], $ready);
        Carbon::setTestNow();
    }
}

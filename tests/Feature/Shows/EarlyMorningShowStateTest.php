<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * show_date is the calendar day a show starts, as Whatnot dates it. A show
 * Seller Hub lists as "10/20/2026 • 4:07 AM" was read as 4:07 AM on Oct 21
 * (the "late end of Oct 20"), so hours after it ended it still said Upcoming.
 */
class EarlyMorningShowStateTest extends TestCase
{
    use RefreshDatabase;

    private function show(string $date, string $start, ?int $minutes = null, ?string $end = null): Show
    {
        return Show::create(['title' => 'BIG GIVEYS', 'show_date' => $date, 'start_time' => "{$date} {$start}", 'end_time' => $end ? "{$date} {$end}" : null, 'show_duration' => $minutes, 'status' => 'draft']);
    }

    public function test_an_early_morning_show_is_on_its_own_calendar_day(): void
    {
        $show = $this->show('2026-10-20', '04:07:00', 231); // 3h 51m, to 7:58 AM
        $tz = 'America/Chicago';

        $this->assertSame('upcoming', $show->scheduleStateAt(Carbon::parse('2026-10-20 03:00', $tz)));
        $this->assertSame('live', $show->scheduleStateAt(Carbon::parse('2026-10-20 05:00', $tz)));
        $this->assertSame('done', $show->scheduleStateAt(Carbon::parse('2026-10-20 12:00', $tz)));
        $this->assertSame('2026-10-20 04:07', $show->startsAt($tz)->format('Y-m-d H:i'));
    }

    public function test_a_show_running_past_midnight_ends_the_next_day(): void
    {
        $show = $this->show('2026-10-19', '22:00:00', null, '01:30:00');
        $tz = 'America/Chicago';

        $this->assertSame([1320, 1530], $show->scheduleMinutes());
        $this->assertSame('live', $show->scheduleStateAt(Carbon::parse('2026-10-20 00:45', $tz)));
        $this->assertSame('done', $show->scheduleStateAt(Carbon::parse('2026-10-20 02:00', $tz)));
    }
}

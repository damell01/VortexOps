<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\WhatnotReportingReconciler;
use App\Services\WhatnotScraper;
use App\Support\ShowTimeText;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Most Seller Hub imports read "Time TBD": the row's text named the time
 * ("Sun, Oct 12 · 8:00 PM") but only the date was ever read from it.
 */
class ShowStartTimeFromTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_time_is_read_from_row_text(): void
    {
        $this->assertSame('20:00:00', ShowTimeText::time('Mojo Break #47 Sun, Oct 12, 2026 · 8:00 PM 2h 30m $1,240'));
        $this->assertSame('09:15:00', ShowTimeText::time('Morning rips Oct 12 9:15am'));
        $this->assertSame('00:30:00', ShowTimeText::time('Late night Oct 12 12:30 AM'));
        $this->assertSame('12:00:00', ShowTimeText::time('Lunch break Oct 12 12 PM'));
        $this->assertSame('19:00:00', ShowTimeText::time('Oct 12 7 p.m. CDT'));
    }

    public function test_durations_and_titles_are_not_mistaken_for_the_start(): void
    {
        $this->assertNull(ShowTimeText::time('Big rip Oct 12, 2026 Duration 2:30:00 $900'));
        $this->assertSame('20:30:00', ShowTimeText::time('Friday 6PM Breaks Oct 17 8:30 PM', 'Friday 6PM Breaks'));
        $this->assertNull(ShowTimeText::time(''));
        $this->assertNull(ShowTimeText::fromPayload(['text' => 'Oct 12 8 PM'], null));
        $this->assertSame('2026-10-12 20:00:00', ShowTimeText::fromPayload(['_seller_hub_text' => 'Oct 12 8 PM'], '2026-10-12'));
    }

    public function test_discovery_fills_a_missing_time_from_the_row_text_but_keeps_a_set_one(): void
    {
        $this->actingAs(User::factory()->create());
        $channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'whatnot_username' => 'vortexbreaks', 'status' => 'active']);
        $kept = Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'Hand timed', 'show_date' => '2026-10-11', 'start_time' => '2026-10-11 18:45:00', 'status' => 'draft', 'import_source' => 'auto_whatnot', 'whatnot_show_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb']);

        $this->mock(WhatnotScraper::class, fn ($mock) => $mock->shouldReceive('fetchSellerHubIndex')->once()->andReturn(['past' => [
            ['live_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'title' => 'Sunday Singles', 'show_date' => '2026-10-12', 'text' => 'Sunday Singles Sun, Oct 12, 2026 · 8:00 PM 2h'],
            ['live_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'title' => 'Hand timed', 'show_date' => '2026-10-11', 'text' => 'Hand timed Sat, Oct 11, 2026 · 6:00 PM'],
        ]]));

        app(WhatnotReportingReconciler::class)->discoverShows($channel, null, Carbon::parse('2026-10-01'));

        $this->assertSame('20:00', Show::where('title', 'Sunday Singles')->first()->start_time->format('H:i'));
        $this->assertSame('18:45', $kept->fresh()->start_time->format('H:i'));
    }

    public function test_the_backfill_fills_only_shows_without_a_time(): void
    {
        $this->actingAs(User::factory()->create());
        $tbd = Show::create(['title' => 'Old import', 'show_date' => '2026-09-01', 'status' => 'draft', 'import_source' => 'auto_whatnot', 'raw_import_payload' => ['text' => 'Old import Mon, Sep 1, 2026 · 7:30 PM']]);
        $timed = Show::create(['title' => 'Already timed', 'show_date' => '2026-09-02', 'start_time' => '2026-09-02 10:00:00', 'status' => 'draft', 'import_source' => 'auto_whatnot', 'raw_import_payload' => ['text' => 'Already timed Sep 2 · 9:00 PM']]);
        $none = Show::create(['title' => 'No time anywhere', 'show_date' => '2026-09-03', 'status' => 'draft', 'import_source' => 'auto_whatnot', 'raw_import_payload' => ['text' => 'No time anywhere Sep 3']]);

        $this->artisan('shows:fill-start-times', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($tbd->fresh()->start_time, 'a dry run changes nothing');

        $this->artisan('shows:fill-start-times')->assertSuccessful()->expectsOutputToContain('Filled 1 start times');

        $this->assertSame('19:30', $tbd->fresh()->start_time->format('H:i'));
        $this->assertSame('10:00', $timed->fresh()->start_time->format('H:i'));
        $this->assertNull($none->fresh()->start_time);
    }
}

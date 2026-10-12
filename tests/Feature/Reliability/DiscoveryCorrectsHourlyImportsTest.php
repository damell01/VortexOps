<?php

namespace Tests\Feature\Reliability;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\WhatnotReportingReconciler;
use App\Services\WhatnotScraper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A show the hourly import saved with a UTC date (a day late) carries
 * Whatnot's live id only in its link. Discovery must correct that show, not
 * add a second, correctly dated copy beside it.
 */
class DiscoveryCorrectsHourlyImportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wrongly_dated_hourly_import_is_corrected_in_place(): void
    {
        $this->actingAs(User::factory()->create());
        $channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'whatnot_username' => 'vortexbreaks', 'status' => 'active']);
        $uuid = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $saved = Show::create([
            'whatnot_channel_id' => $channel->id, 'title' => 'Late night rips', 'show_date' => '2026-10-11',
            'start_time' => '02:00:00', 'status' => 'draft', 'import_source' => 'auto_whatnot',
            'detail_url' => "https://www.whatnot.com/dashboard/live/{$uuid}",
        ]);

        $this->mock(WhatnotScraper::class, fn ($mock) => $mock->shouldReceive('fetchSellerHubIndex')->once()->andReturn(['past' => [[
            'live_id' => $uuid, 'title' => 'Late night rips', 'show_date' => '2026-10-10', 'start_time' => '21:00:00',
        ]]]));

        app(WhatnotReportingReconciler::class)->discoverShows($channel, null, Carbon::parse('2026-10-01'));

        $this->assertSame(1, Show::count(), 'no second copy');
        $saved->refresh();
        $this->assertSame('2026-10-10', $saved->show_date->toDateString());
        $this->assertSame('21:00:00', $saved->start_time->format('H:i:s'));
        $this->assertSame($uuid, $saved->whatnot_show_id);
    }
}

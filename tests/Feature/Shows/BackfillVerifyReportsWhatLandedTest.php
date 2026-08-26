<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * --verify takes one show through and says what actually arrived.
 *
 * A backfill is hours of scraping against a session that may have lapsed and
 * pages whose markup Whatnot changes without notice. Discovering either on show
 * 300 is expensive. The scraping itself needs a browser, so what is checked
 * here is the reporting around it: that a failed run is called a failure rather
 * than reported as a clean pass.
 */
class BackfillVerifyReportsWhatLandedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = WhatnotChannel::create([
            'name'              => 'Vortex Breaks',
            'status'            => 'active',
            'include_in_import' => true,
        ]);

        Show::create([
            'whatnot_channel_id' => $channel->id,
            'whatnot_show_id'    => (string) str()->uuid(),
            'title'              => 'Break #900',
            'show_date'          => now()->subDays(5)->toDateString(),
            'status'             => 'mapping',
        ]);
    }

    public function test_a_scraper_that_could_not_run_is_reported_as_a_failure(): void
    {
        // No channel record on the scraper's side, no browser here: the inner
        // command cannot succeed. The point is that --verify says so instead of
        // reporting an empty pass, which would send someone into a six-hour run
        // on a lapsed session.
        $this->artisan('whatnot:backfill-history --verify')
            ->expectsOutputToContain('Verifying on one show')
            ->assertFailed();
    }

    public function test_it_says_so_when_there_is_nothing_to_verify_against(): void
    {
        Show::query()->update([
            'last_analytics_synced_at' => now(),
            'last_shipments_synced_at' => now(),
        ]);

        $this->artisan('whatnot:backfill-history --verify')
            ->expectsOutputToContain('Nothing outstanding')
            ->assertSuccessful();
    }
}

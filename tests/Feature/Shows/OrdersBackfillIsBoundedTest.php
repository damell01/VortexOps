<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The scheduled orders backfill has to give the lock back.
 *
 * Every Whatnot job drives one Chromium profile behind one lock. This command
 * takes that lock per show and, unbounded, walks every show without orders —
 * hundreds of them, hours at a stretch — re-acquiring it on the next iteration
 * faster than any waiting job could win the race.
 *
 * So whatnot:refresh-recent, scheduled at :07 and :37, lost that race nearly
 * every time, and analytics were never fetched for half the catalogue. The
 * coverage report showed it as a clean ~50/50 split between shows with figures
 * and shows with none, which reads like a broken scrape and was really a
 * starved one.
 */
class OrdersBackfillIsBoundedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);

        foreach (range(1, 6) as $n) {
            Show::create([
                'whatnot_channel_id' => $channel->id,
                'whatnot_show_id'    => (string) str()->uuid(),
                'detail_url'         => 'https://www.whatnot.com/dashboard/live/show-' . $n,
                'title'              => 'Break #' . $n,
                'show_date'          => now()->subDays($n)->toDateString(),
                'status'             => 'draft',
            ]);
        }
    }

    public function test_the_scheduled_backfill_is_bounded(): void
    {
        $command = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'whatnot:import-orders'));

        $this->assertNotNull($command, 'the orders backfill is no longer scheduled');

        $this->assertStringContainsString(
            '--limit',
            $command->command,
            'the scheduled orders backfill is unbounded again — it will hold the browser lock for '
            . 'hours and starve whatnot:refresh-recent of analytics',
        );
    }

    public function test_a_limited_run_says_how_many_it_left_behind(): void
    {
        // Hold the lock so no scraping is attempted; the selection and the
        // reporting are what matter here.
        Cache::lock('whatnot:browser', 60)->get();

        $this->artisan('whatnot:import-orders --new-only --limit=2')
            ->expectsOutputToContain('2 of 6 outstanding shows this run')
            ->assertSuccessful();
    }

    public function test_an_unlimited_run_is_still_unlimited(): void
    {
        // A person running this by hand wants the whole job done.
        Cache::lock('whatnot:browser', 60)->get();

        $this->artisan('whatnot:import-orders --new-only')
            ->doesntExpectOutputToContain('outstanding shows this run')
            ->assertSuccessful();
    }
}

<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Every scheduled whatnot:* job drives one Chromium profile behind one lock, so
 * while any of them runs a manual run just queues. WHATNOT_SCHEDULE_ENABLED
 * pauses them as a group without taking down backups, health checks or reports.
 */
class WhatnotSchedulePauseTest extends TestCase
{
    /** @return array<int, \Illuminate\Console\Scheduling\Event> */
    private function events(): array
    {
        // Resolved fresh so the closure reads the config set by each test.
        return app(Schedule::class)->events();
    }

    /** @return array<int, \Illuminate\Console\Scheduling\Event> */
    private function whatnotEvents(): array
    {
        return array_values(array_filter(
            $this->events(),
            // The Seller API tick has its own production-only switches (below),
            // separate from the scraper schedule this flag pauses.
            fn ($e) => str_contains((string) $e->command, 'whatnot:')
                && ! str_contains((string) $e->command, 'whatnot:api-sync-tick'),
        ));
    }

    public function test_the_seller_api_tick_stays_off_until_its_own_switches_are_on(): void
    {
        config(['vortex.whatnot.schedule_enabled' => true]);

        $ticks = array_filter(
            $this->events(),
            fn ($e) => str_contains((string) $e->command, 'whatnot:api-sync-tick'),
        );

        $this->assertNotEmpty($ticks);
        foreach ($ticks as $event) {
            $this->assertFalse($event->filtersPass($this->app), 'the Seller API tick ran outside production with its switches off');
        }
    }

    public function test_there_are_whatnot_jobs_to_guard(): void
    {
        // A floor, not a fixed count. The two tests below already catch a new
        // whatnot job added without ->skip(), because an unguarded one still
        // appears here and fails to pause — so pinning the exact number added
        // nothing except a failure every time the schedule legitimately changed.
        // What they cannot catch is an empty list, which would let both pass
        // while asserting nothing at all.
        $this->assertNotEmpty($this->whatnotEvents());
    }

    public function test_they_run_when_the_scraper_schedule_is_on(): void
    {
        config(['vortex.whatnot.schedule_enabled' => true, 'vortex.whatnot.scraper_schedule' => true]);

        foreach ($this->whatnotEvents() as $event) {
            $this->assertTrue($event->filtersPass($this->app), "{$event->command} was skipped while enabled");
        }
    }

    public function test_browser_scraper_jobs_are_off_by_default_and_database_jobs_still_run(): void
    {
        config(['vortex.whatnot.schedule_enabled' => true, 'vortex.whatnot.scraper_schedule' => false]);

        $scraper = ['whatnot:sync-reporting', 'whatnot:backfill-nightly', 'whatnot:analytics-catchup', 'whatnot:sync-tips-batch'];
        $checked = 0;
        foreach ($this->whatnotEvents() as $event) {
            $isScraper = collect($scraper)->contains(fn ($c) => str_contains((string) $event->command, $c));
            $this->assertSame(! $isScraper, $event->filtersPass($this->app), "{$event->command} " . ($isScraper ? 'ran with the scraper off' : 'was skipped though it does not use the browser'));
            $checked += (int) $isScraper;
        }
        $this->assertGreaterThan(0, $checked);
    }

    public function test_disabling_the_flag_pauses_every_one_of_them(): void
    {
        config(['vortex.whatnot.schedule_enabled' => false]);

        foreach ($this->whatnotEvents() as $event) {
            $this->assertFalse($event->filtersPass($this->app), "{$event->command} still ran while paused");
        }
    }

    public function test_it_does_not_pause_anything_else(): void
    {
        config(['vortex.whatnot.schedule_enabled' => false]);

        $others = array_filter(
            $this->events(),
            fn ($e) => ! str_contains((string) $e->command, 'whatnot:')
                && str_contains((string) $e->command, 'db:backup'),
        );

        $this->assertNotEmpty($others, 'expected the backup job to be scheduled');

        foreach ($others as $event) {
            $this->assertTrue($event->filtersPass($this->app), 'pausing Whatnot also paused the database backup');
        }
    }
}

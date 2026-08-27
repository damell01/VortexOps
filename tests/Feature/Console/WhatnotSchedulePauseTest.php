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
            fn ($e) => str_contains((string) $e->command, 'whatnot:'),
        ));
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

    public function test_they_run_by_default(): void
    {
        config(['vortex.whatnot.schedule_enabled' => true]);

        foreach ($this->whatnotEvents() as $event) {
            $this->assertTrue($event->filtersPass($this->app), "{$event->command} was skipped while enabled");
        }
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

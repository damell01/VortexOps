<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * activitylog:clean asks "are you sure?" in production unless --force is
 * passed. The scheduler cannot answer, so every weekly run exited 1.
 */
class ActivityLogCleanRunsUnattendedTest extends TestCase
{
    public function test_the_scheduled_clean_passes_force(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'activitylog:clean'));

        $this->assertNotNull($event, 'activitylog:clean is no longer scheduled');
        $this->assertStringContainsString('--force', (string) $event->command);
    }
}

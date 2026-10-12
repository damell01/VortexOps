<?php

namespace Tests\Feature\Reliability;

use App\Support\BusinessTime;
use Filament\Support\Facades\FilamentTimezone;
use Tests\TestCase;

/**
 * Shows after 8pm and early in the morning were landing on the wrong date: the
 * server works in UTC, where an 8pm Central show is already the next day.
 */
class BusinessTimeTest extends TestCase
{
    public function test_a_late_show_keeps_its_local_date(): void
    {
        // 9pm Central on Oct 12 = 02:00 UTC on Oct 13.
        $this->assertSame('2026-10-12 21:00', BusinessTime::fromWhatnot('2026-10-13T02:00:00Z')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 21:00', BusinessTime::fromWhatnot(1791856800000)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 21:00', BusinessTime::fromWhatnot('1791856800')->format('Y-m-d H:i'));
        // A plain local time is already Central and is not shifted.
        $this->assertSame('2026-10-12 21:00', BusinessTime::fromWhatnot('2026-10-12 21:00')->format('Y-m-d H:i'));
    }

    public function test_the_app_shows_times_in_central_and_the_scraper_runs_in_it(): void
    {
        $this->assertSame('America/Chicago', BusinessTime::zone());
        $this->assertSame('America/Chicago', FilamentTimezone::get());
        $this->assertSame('America/Chicago', config('vortex.whatnot.timezone'));
        foreach (['whatnot-runner.cjs', 'whatnot-production-sync.cjs', 'whatnot-scraper.cjs'] as $script) {
            $this->assertStringContainsString("process.env.TZ = process.env.WHATNOT_TIMEZONE || 'America/Chicago'", file_get_contents(base_path('scripts/'.$script)));
        }
        foreach (['whatnot-scrapling.py', 'whatnot-scrapling-stealthy.py'] as $script) {
            $this->assertStringContainsString('os.environ["TZ"]', file_get_contents(base_path('scripts/'.$script)));
        }
    }
}

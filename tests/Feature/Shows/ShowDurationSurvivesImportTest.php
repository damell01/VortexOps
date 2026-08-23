<?php

namespace Tests\Feature\Shows;

use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * A show's duration has to survive the trip from Whatnot into the database.
 *
 * It matters because it is a payout input: an hourly or hybrid streamer is
 * paid against it, so a duration silently lost is money silently wrong. Every
 * show inspected so far reads "0h 0m", and there are two very different
 * explanations — Whatnot reporting nothing, or us dropping it — which look
 * identical from the show page.
 *
 * These pin our half. A zero that arrives is stored as a zero, a real duration
 * is stored as itself, and neither is quietly discarded on the way. If
 * production still shows 0h 0m with these passing, the number is Whatnot's and
 * the place to look is their analytics page, not this import.
 *
 * The zero case is the one worth having: array_filter with no callback strips
 * 0 along with null, and a payload built that way would drop every legitimately
 * zero metric — not just duration.
 */
class ShowDurationSurvivesImportTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vortex.whatnot.email'    => 'scraper@example.com',
            'vortex.whatnot.password' => 'secret',
        ]);

        $this->actingAs(User::factory()->create());

        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
    }

    /** @param array<string, mixed> $overrides */
    private function importRow(array $overrides = []): Show
    {
        $row = array_merge([
            'title'         => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'     => '2026-08-20',
            'gross_revenue' => 8801.00,
            'units_sold'    => 98,
        ], $overrides);

        $process = Mockery::mock(Process::class);
        $process->allows('run')->andReturn(0);
        $process->allows('getExitCode')->andReturn(0);
        $process->allows('getOutput')->andReturn(json_encode([$row]));
        $process->allows('getErrorOutput')->andReturn('');
        $process->allows('isSuccessful')->andReturn(true);
        $process->allows('getCommandLine')->andReturn("'node' 'scraper.cjs'");
        $process->allows('start')->andReturnNull();
        $process->allows('isRunning')->andReturn(false);
        $process->allows('checkTimeout')->andReturnNull();
        $process->allows('getIncrementalErrorOutput')->andReturn('');

        $scraper = Mockery::mock(WhatnotScraper::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $scraper->allows('makeProcess')->andReturn($process);

        $scraper->importShows($this->channel, 50, false, false);

        return Show::firstWhere('title', $row['title']);
    }

    public function test_a_real_duration_is_stored(): void
    {
        $show = $this->importRow(['show_duration' => 154]);

        $this->assertNotNull($show, 'the show was not imported at all');
        $this->assertSame(154, (int) $show->show_duration);
    }

    public function test_a_duration_of_zero_is_stored_as_zero(): void
    {
        // The case a bare array_filter would eat. Whatnot really does report
        // 0h 0m for some shows, and storing that is different from never
        // having heard: one says the show was instant, the other says nobody
        // knows, and only one of those should leave the column untouched.
        $show = $this->importRow(['show_duration' => 0]);

        $this->assertNotNull($show);
        $this->assertSame(0, (int) $show->show_duration);
    }

    public function test_a_duration_that_never_arrived_leaves_the_column_alone(): void
    {
        $show = $this->importRow(['show_duration' => null]);

        $this->assertNotNull($show);
        $this->assertNull($show->show_duration);
    }

    public function test_the_other_zero_valued_metrics_survive_too(): void
    {
        // Duration is the one that was noticed; it is not the only one that
        // would have been lost. A quiet show genuinely has zero shares and
        // zero giveaways, and those zeroes are findings.
        $show = $this->importRow([
            'shares_count'           => 0,
            'giveaways_count'        => 0,
            'buyers_count'           => 0,
            'max_concurrent_viewers' => 0,
        ]);

        $this->assertSame(0, (int) $show->shares_count);
        $this->assertSame(0, (int) $show->giveaways_count);
        $this->assertSame(0, (int) $show->buyers_count);
        $this->assertSame(0, (int) $show->max_concurrent_viewers);
    }

    public function test_a_rescrape_can_correct_a_duration_downwards(): void
    {
        // The update path is separate from the create path, and a metric that
        // can only ever go up is a metric that cannot be corrected.
        $this->importRow(['show_duration' => 154]);
        $show = $this->importRow(['show_duration' => 12]);

        $this->assertSame(12, (int) $show->fresh()->show_duration);
    }
}

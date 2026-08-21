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
 * A scraped show with no parsed date used to be thrown away outright.
 *
 * That is how a whole import came back "0 created, 0 updated, 4 skipped" while
 * the scraper had in fact reached Whatnot, switched channel, and fetched real
 * shows — every row arrived with a title, a status and a raw timestamp the
 * JavaScript side had failed to read into show_date.
 *
 * show_date is NOT NULL, so skipping is the right call when there is genuinely
 * no date. It is the wrong call when the date is sitting in show_date_raw in a
 * format PHP parses perfectly well.
 */
class WhatnotShowDateFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'vortex.whatnot.email'    => 'scraper@example.com',
            'vortex.whatnot.password' => 'secret',
        ]);

        $this->actingAs(User::factory()->create());
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function importing(array $rows): array
    {
        $process = Mockery::mock(Process::class);
        $process->allows('run')->andReturn(0);
        $process->allows('getExitCode')->andReturn(0);
        $process->allows('getOutput')->andReturn(json_encode($rows));
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

        $channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        return $scraper->importShows($channel, 50, false, false);
    }

    public function test_an_iso_timestamp_in_the_raw_field_still_creates_the_show(): void
    {
        // The exact shape the live run produced: a real show, a real title, and
        // a startTime that never made it into show_date.
        $result = $this->importing([[
            'title'         => 'TGIF RIPS AND SINGLES w/Connor',
            'show_date'     => null,
            'show_date_raw' => '2026-08-21T23:00:00Z',
        ]]);

        $this->assertSame(1, $result['created']);
        $this->assertSame('2026-08-21', Show::first()->show_date->toDateString());
    }

    public function test_the_time_is_recovered_from_the_same_value(): void
    {
        // If the scraper could not read the date out of the timestamp, it did
        // not read the time out of it either — both come from the one field.
        $this->importing([[
            'title'         => 'FREE PACKS FREAKY FRIDAY WITH MATT',
            'show_date'     => null,
            'show_date_raw' => '2026-08-21T23:30:00Z',
        ]]);

        $this->assertSame('23:30:00', Show::first()->start_time->format('H:i:s'));
    }

    public function test_a_date_the_scraper_did_parse_is_left_alone(): void
    {
        // The fallback must not second-guess a value that already arrived.
        $this->importing([[
            'title'         => 'Monday Night Rip',
            'show_date'     => '2026-06-15',
            'show_date_raw' => 'some unparseable garbage',
            'start_time'    => '19:00:00',
        ]]);

        $show = Show::first();
        $this->assertSame('2026-06-15', $show->show_date->toDateString());
        $this->assertSame('19:00:00', $show->start_time->format('H:i:s'));
    }

    public function test_a_row_with_no_readable_date_at_all_is_still_skipped(): void
    {
        // show_date is NOT NULL — a row that genuinely has no date has to be
        // refused rather than crash the import or invent a date for it.
        $result = $this->importing([[
            'title'         => 'Mystery Show',
            'show_date'     => null,
            'show_date_raw' => 'not a date in any format',
        ]]);

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, Show::count());
    }
}

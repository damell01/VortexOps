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
 * Vortex Shop shows were turning up under Vortex Breaks. The import matched a
 * scraped show to any saved show with the same title and date, on any
 * channel — or, with no title, to any show that day — so a Shop show with the
 * same name as a Breaks show the same day was folded into the Breaks one.
 */
class ImportKeepsChannelsApartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vortex.whatnot.email' => 'scraper@example.com', 'vortex.whatnot.password' => 'secret']);
        $this->actingAs(User::factory()->create());
    }

    private function import(WhatnotChannel $channel, array $rows): array
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

        $scraper = Mockery::mock(WhatnotScraper::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $scraper->allows('makeProcess')->andReturn($process);

        return $scraper->importShows($channel, 50, false, false);
    }

    public function test_same_title_same_day_on_two_channels_stays_two_shows(): void
    {
        $breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'whatnot_username' => 'vortexbreaks', 'status' => 'active']);
        $shop = WhatnotChannel::create(['name' => 'Vortex Shop', 'whatnot_username' => 'vortexshop', 'status' => 'active']);

        $this->import($breaks, [[
            'title' => 'Sunday Night Rips', 'show_date' => '2026-10-11', 'gross_revenue' => 500,
            'detail_url' => 'https://www.whatnot.com/dashboard/live/11111111-1111-4111-8111-111111111111',
        ]]);
        $this->import($shop, [[
            'title' => 'Sunday Night Rips', 'show_date' => '2026-10-11', 'gross_revenue' => 200,
            'detail_url' => 'https://www.whatnot.com/dashboard/live/22222222-2222-4222-8222-222222222222',
        ]]);

        $this->assertSame(2, Show::count());
        $shopShow = Show::where('whatnot_channel_id', $shop->id)->sole();
        $this->assertEquals(200, (float) $shopShow->gross_revenue);
        $this->assertSame('22222222-2222-4222-8222-222222222222', $shopShow->whatnot_show_id);
        $this->assertEquals(500, (float) Show::where('whatnot_channel_id', $breaks->id)->sole()->gross_revenue);
    }

    public function test_the_same_live_id_updates_the_saved_show_and_is_flagged_if_the_channel_differs(): void
    {
        $breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'whatnot_username' => 'vortexbreaks', 'status' => 'active']);
        $shop = WhatnotChannel::create(['name' => 'Vortex Shop', 'whatnot_username' => 'vortexshop', 'status' => 'active']);
        $row = ['title' => 'Pokemon Night', 'show_date' => '2026-10-11', 'gross_revenue' => 300,
            'detail_url' => 'https://www.whatnot.com/dashboard/live/33333333-3333-4333-8333-333333333333'];

        $this->import($breaks, [$row]);
        $this->import($shop, [['gross_revenue' => 350] + $row]);

        $show = Show::sole();
        $this->assertEquals(350, (float) $show->gross_revenue);
        $this->assertTrue((bool) $show->channel_attribution_suspect, 'one show seen under two channels goes to Review show channels');
    }

    public function test_a_row_without_a_title_does_not_attach_to_another_channels_show_that_day(): void
    {
        $breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'whatnot_username' => 'vortexbreaks', 'status' => 'active']);
        $shop = WhatnotChannel::create(['name' => 'Vortex Shop', 'whatnot_username' => 'vortexshop', 'status' => 'active']);
        $this->import($breaks, [['title' => 'Breaks Show', 'show_date' => '2026-10-11', 'gross_revenue' => 500]]);

        $this->import($shop, [['title' => null, 'show_date' => '2026-10-11', 'gross_revenue' => 999]]);

        $this->assertEquals(500, (float) Show::where('whatnot_channel_id', $breaks->id)->sole()->gross_revenue);
    }
}

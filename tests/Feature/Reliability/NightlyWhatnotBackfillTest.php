<?php

namespace Tests\Feature\Reliability;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Services\WhatnotReportingReconciler;
use App\Services\WhatnotScraper;
use App\Support\WhatnotPipelineLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NightlyWhatnotBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function channel(): WhatnotChannel
    {
        return WhatnotChannel::create(['name' => 'Cards', 'whatnot_username' => 'cards', 'status' => 'active', 'include_in_import' => true]);
    }

    private function show(int $channelId, array $data = []): Show
    {
        return Show::withoutEvents(fn () => Show::create(array_merge([
            'title' => 'Historical show', 'show_date' => '2026-07-05', 'status' => 'draft',
            'raw_import_payload' => ['_seller_hub_state' => 'past'], 'whatnot_channel_id' => $channelId,
        ], $data)));
    }

    public function test_daytime_and_dry_runs_do_not_open_browser_or_change_data(): void
    {
        Carbon::setTestNow('2026-10-09 12:00:00 America/Chicago');
        $channel = $this->channel(); $this->show($channel->id);
        $this->mock(WhatnotReportingReconciler::class, fn ($mock) => $mock->shouldNotReceive('discoverShows', 'backfillAnalytics'));
        $this->artisan('whatnot:backfill-nightly')->expectsOutput('Outside the overnight window; no browser work started.')->assertSuccessful();
        $this->artisan('whatnot:backfill-nightly --dry-run')->expectsOutput('Dry run: no browser, locks, or data changes.')->assertSuccessful();
        $this->assertNull(WhatnotPipelineLock::holder());
        $this->assertSame(0, ShowIngestionLog::count());
    }

    public function test_night_discovers_first_then_attempts_every_due_show_once_in_small_batches(): void
    {
        Carbon::setTestNow('2026-10-09 02:00:00 America/Chicago');
        $channel = $this->channel();
        $ids = [];
        for ($i = 0; $i < 12; $i++) $ids[] = $this->show($channel->id)->id;
        $this->show($channel->id, ['show_date' => '2026-06-30']);
        $this->show($channel->id, ['status' => 'cancelled']);
        $attempted = []; $discovered = false;
        $this->mock(WhatnotReportingReconciler::class, function ($mock) use (&$attempted, &$discovered, $channel) {
            $mock->shouldReceive('discoverShows')->once()->andReturnUsing(function ($actual, $progress, $since) use (&$discovered, $channel) {
                $this->assertSame($channel->id, $actual->id);
                $this->assertSame('2026-07-01', $since->toDateString());
                $this->assertGreaterThan(microtime(true), config('vortex.whatnot.runtime_deadline'));
                $discovered = true;
                return ['created' => 0, 'updated' => 0, 'skipped' => 0];
            });
            $mock->shouldReceive('backfillAnalytics')->twice()->andReturnUsing(function ($actual, $since, $limit, $progress, $batch) use (&$attempted, &$discovered) {
                $this->assertTrue($discovered);
                $this->assertLessThanOrEqual(10, count($batch));
                $this->assertSame([], array_intersect($attempted, $batch));
                $attempted = array_merge($attempted, $batch);
                // Leave every row incomplete: the command must not repeatedly retry it.
                return ['updated' => 0, 'failed' => 0, 'skipped' => count($batch)];
            });
        });
        $this->artisan('whatnot:backfill-nightly')->assertSuccessful();
        sort($ids); sort($attempted);
        $this->assertSame($ids, $attempted);
        $this->assertSame(3, ShowIngestionLog::where('source', 'whatnot_nightly_backfill')->count());
        $this->assertNull(WhatnotPipelineLock::holder());
        $this->assertNull(config('vortex.whatnot.runtime_deadline'));
    }

    public function test_busy_coordinator_skips_without_scraping(): void
    {
        Carbon::setTestNow('2026-10-09 02:00:00 America/Chicago');
        $this->channel();
        $lock = Cache::lock(WhatnotPipelineLock::KEY, 60); $lock->get();
        $this->mock(WhatnotReportingReconciler::class, fn ($mock) => $mock->shouldNotReceive('discoverShows', 'backfillAnalytics'));
        try { $this->artisan('whatnot:backfill-nightly')->assertSuccessful(); }
        finally { $lock->release(); }
    }

    public function test_browser_process_timeout_is_capped_by_remaining_night_window(): void
    {
        config(['vortex.whatnot.runtime_deadline' => microtime(true) + 30]);
        $scraper = new class extends WhatnotScraper {
            public function process(): \Symfony\Component\Process\Process { return $this->makeProcess([], 3600); }
        };
        $this->assertLessThanOrEqual(30, $scraper->process()->getTimeout());
        config(['vortex.whatnot.runtime_deadline' => microtime(true) - 1]);
        $this->expectException(\RuntimeException::class);
        try { $scraper->process(); }
        finally { config(['vortex.whatnot.runtime_deadline' => null]); }
    }
}

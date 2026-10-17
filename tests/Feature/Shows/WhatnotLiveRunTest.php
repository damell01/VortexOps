<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\WhatnotSyncPage;
use App\Jobs\RunWhatnotReportingJob;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\WhatnotLiveLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Starting a scraper run from Scraper Status and watching it: the log streams
 * while the run is going, and each show it writes to lands in a feed.
 */
class WhatnotLiveRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_output_is_written_through_as_it_happens_and_keeps_only_the_tail(): void
    {
        $log = new WhatnotLiveLog();
        $log->writeln('Coordinator lock acquired.');
        $log->flush();
        $this->assertSame(['Coordinator lock acquired.'], WhatnotLiveLog::lines());

        for ($i = 0; $i < WhatnotLiveLog::MAX_LINES + 50; $i++) {
            $log->writeln("line {$i}");
        }
        $log->flush();
        $lines = WhatnotLiveLog::lines();
        $this->assertCount(WhatnotLiveLog::MAX_LINES, $lines);
        $this->assertSame('line ' . (WhatnotLiveLog::MAX_LINES + 49), end($lines));
        $this->assertStringContainsString('Coordinator lock acquired.', $log->all());
    }

    public function test_catch_up_queues_one_background_run_and_refuses_a_second(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        Livewire::test(WhatnotSyncPage::class)->call('runReporting', 'catchup');
        Queue::assertPushed(RunWhatnotReportingJob::class, fn ($job) => $job->mode === 'catchup');

        Livewire::test(WhatnotSyncPage::class)->call('runReporting', 'freshness');
        Queue::assertPushed(RunWhatnotReportingJob::class, 1);
    }

    public function test_the_page_shows_the_live_log_and_the_shows_pulled_this_run(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'whatnot_username' => 'vortex', 'status' => 'active']);
        Setting::set('whatnot_ui_job', json_encode(['mode' => 'catchup', 'status' => 'running', 'started_at' => now()->subMinutes(5)->toIso8601String()]));
        Setting::set(WhatnotLiveLog::KEY, "historical-analytics [1/3]: collected uuid=abc gross=812\nanalytics backfill failed: session expired");

        $show = fn (array $data, $syncedAt) => Show::withoutEvents(fn () => Show::create($data + ['status' => 'draft', 'whatnot_channel_id' => $channel->id])
            ->forceFill(['last_analytics_synced_at' => $syncedAt])->saveQuietly());
        $show(['title' => 'Friday Night Breaks', 'show_date' => today(), 'gross_revenue' => 812, 'whatnot_net' => 690], now()->subMinute());
        $show(['title' => 'Last Week Show', 'show_date' => today()->subWeek()], now()->subDay());

        Livewire::test(WhatnotSyncPage::class)
            ->assertOk()
            ->assertSee('Live run')
            ->assertSee('collected uuid=abc gross=812')
            ->assertSee('session expired')
            ->assertSee('Friday Night Breaks')
            ->assertSee('$812')
            ->assertDontSee('Last Week Show');
    }
}

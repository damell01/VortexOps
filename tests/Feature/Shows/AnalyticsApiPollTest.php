<?php

namespace Tests\Feature\Shows;

use App\Jobs\RetryShowAnalytics;
use App\Models\Show;
use App\Models\WhatnotChannel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsApiPollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'America/Chicago']);
        $this->travelTo(CarbonImmutable::parse('2026-10-16 13:00', 'America/Chicago'));
    }

    private function show(array $overrides = []): Show
    {
        $channel = WhatnotChannel::firstOrCreate(['name' => 'Vortex Breaks'], [
            'status' => 'active', 'include_in_import' => true, 'whatnot_username' => 'vortexbreaks',
        ]);

        return Show::create(array_merge([
            'title' => 'Analytics poll test '.Str::random(8),
            'show_date' => '2026-10-15', 'start_time' => '19:00', 'end_time' => '21:00',
            'whatnot_channel_id' => $channel->id, 'whatnot_show_id' => (string) Str::uuid(),
            'status' => 'draft',
        ], $overrides));
    }

    public function test_it_queues_only_missing_analytics_for_recent_ended_shows(): void
    {
        Queue::fake();
        $due = $this->show();
        $recentAttempt = $this->show(['raw_import_payload' => [
            '_analytics_last_attempt_at' => now()->subMinutes(3)->toIso8601String(),
        ]]);
        $complete = $this->show(['analytics_sync_status' => 'complete', 'gross_revenue' => 100, 'completed_earnings' => 80]);
        $future = $this->show(['show_date' => '2026-10-17']);
        $cancelled = $this->show(['status' => 'cancelled']);

        $this->artisan('whatnot:analytics-api-poll')->assertSuccessful();

        Queue::assertPushed(RetryShowAnalytics::class, 1);
        Queue::assertPushed(RetryShowAnalytics::class, fn ($job) => $job->showId === $due->id);
        Queue::assertNotPushed(RetryShowAnalytics::class, fn ($job) => in_array($job->showId, [
            $recentAttempt->id, $complete->id, $future->id, $cancelled->id,
        ], true));
    }

    public function test_dry_run_does_not_dispatch_any_jobs(): void
    {
        Queue::fake();
        $this->show();

        $this->artisan('whatnot:analytics-api-poll --dry-run')->assertSuccessful();

        Queue::assertNotPushed(RetryShowAnalytics::class);
    }
}

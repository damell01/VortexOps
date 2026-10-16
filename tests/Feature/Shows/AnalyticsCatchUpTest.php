<?php

namespace Tests\Feature\Shows;

use App\Console\Commands\CatchUpWhatnotAnalytics;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Notifications\VortexAlert;
use App\Services\WhatnotReportingReconciler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

/** A show that aired never goes more than about four hours without its analytics. */
class AnalyticsCatchUpTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'America/Chicago']);
        $this->travelTo(CarbonImmutable::parse('2026-10-16 13:00', 'America/Chicago'));
        $this->owner = User::factory()->create(['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']);
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active', 'include_in_import' => true, 'whatnot_username' => 'vortexbreaks']);
    }

    private function show(array $overrides = []): Show
    {
        return Show::create(array_merge([
            'title' => 'Show '.uniqid(), 'show_date' => '2026-10-15', 'start_time' => '19:00', 'end_time' => '21:00',
            'whatnot_channel_id' => $this->channel->id, 'whatnot_show_id' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'draft',
        ], $overrides));
    }

    private function dueIds(): array
    {
        return app(CatchUpWhatnotAnalytics::class)->due()->map(fn ($row) => $row[0]->id)->all();
    }

    public function test_yesterdays_and_todays_ended_shows_without_analytics_are_due(): void
    {
        $yesterday = $this->show();
        $partial = $this->show(['analytics_sync_status' => 'partial', 'last_analytics_synced_at' => now()->subHours(2)]);
        $morning = $this->show(['show_date' => '2026-10-16', 'start_time' => '09:00', 'end_time' => '11:00']);
        $notOver = $this->show(['show_date' => '2026-10-16', 'start_time' => '12:00', 'end_time' => '14:00']);
        $done = $this->show(['analytics_sync_status' => 'complete', 'gross_revenue' => 100, 'completed_earnings' => 80]);
        $cancelled = $this->show(['status' => 'cancelled']);
        $noShow = $this->show(['notes' => Show::NO_ACTIVITY_FLAG]);
        $older = $this->show(['show_date' => '2026-10-12']);

        $due = $this->dueIds();
        foreach ([$yesterday, $partial, $morning] as $show) $this->assertContains($show->id, $due);
        foreach ([$notOver, $done, $cancelled, $noShow, $older] as $show) $this->assertNotContains($show->id, $due);
    }

    public function test_it_checks_exactly_those_shows_and_skips_an_old_cooldown(): void
    {
        $show = $this->show();
        Cache::put('whatnot-analytics-retry:'.$this->channel->id.':'.strtolower($show->whatnot_show_id), true, now()->addHour());

        $reconciler = Mockery::mock(WhatnotReportingReconciler::class);
        $reconciler->shouldReceive('backfillAnalytics')->once()
            ->withArgs(fn ($channel, $since, $limit, $progress, $ids) => $channel->is($this->channel) && $ids === [$show->id])
            ->andReturn(['updated' => 1, 'failed' => 0, 'skipped' => 0]);
        $this->app->instance(WhatnotReportingReconciler::class, $reconciler);
        Notification::fake();

        $this->artisan('whatnot:analytics-catchup')->assertSuccessful();

        $this->assertFalse(Cache::has('whatnot-analytics-retry:'.$this->channel->id.':'.strtolower($show->whatnot_show_id)), 'ended 16 hours ago: no more waiting');
    }

    public function test_the_owner_hears_about_a_show_four_hours_overdue_once(): void
    {
        $overdue = $this->show();
        $recent = $this->show(['show_date' => '2026-10-16', 'start_time' => '10:00', 'end_time' => '11:00']); // 2h ago: not yet

        $reconciler = Mockery::mock(WhatnotReportingReconciler::class);
        $reconciler->shouldReceive('backfillAnalytics')->andReturn(['updated' => 0, 'failed' => 2, 'skipped' => 0]);
        $this->app->instance(WhatnotReportingReconciler::class, $reconciler);
        Notification::fake();

        $this->artisan('whatnot:analytics-catchup')->assertSuccessful();
        Notification::assertSentTo($this->owner, VortexAlert::class, function (VortexAlert $n) use ($overdue, $recent) {
            $body = $n->body;

            return str_contains($body, $overdue->title) && ! str_contains($body, $recent->title);
        });

        $this->artisan('whatnot:analytics-catchup')->assertSuccessful();
        Notification::assertSentToTimes($this->owner, VortexAlert::class, 1);
    }

    public function test_nothing_happens_when_every_show_has_its_analytics(): void
    {
        $this->show(['analytics_sync_status' => 'complete', 'gross_revenue' => 100, 'completed_earnings' => 80]);
        $reconciler = Mockery::mock(WhatnotReportingReconciler::class);
        $reconciler->shouldNotReceive('backfillAnalytics');
        $this->app->instance(WhatnotReportingReconciler::class, $reconciler);
        Notification::fake();

        $this->artisan('whatnot:analytics-catchup')->assertSuccessful();
        Notification::assertNothingSent();
    }
}

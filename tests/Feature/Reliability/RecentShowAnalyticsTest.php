<?php
namespace Tests\Feature\Reliability;

use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecentShowAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(array $data): Show
    {
        return Show::withoutEvents(fn () => Show::create(array_merge(['title' => 'Analytics test', 'show_date' => today(), 'status' => 'draft'], $data)));
    }

    public function test_known_ends_and_confirmed_completed_shows_are_eligible_same_day(): void
    {
        Carbon::setTestNow('2026-10-08 18:00:00');
        $ended = $this->show(['end_time' => now()->subMinutes(45)]);
        $justEnded = $this->show(['raw_import_payload'=>['_seller_hub_state'=>'past'], 'end_time'=>now()->subMinutes(10)]);
        $completed = $this->show(['raw_import_payload'=>['_seller_hub_state'=>'past']]);
        $upcoming = $this->show(['end_time'=>now()->addHour()]);
        $cancelled = $this->show(['status'=>'cancelled', 'end_time'=>now()->subHour()]);
        $ids = Show::readyForAnalytics()->pluck('id')->all();
        $this->assertContains($ended->id, $ids);
        $this->assertContains($completed->id, $ids);
        $this->assertNotContains($justEnded->id, $ids);
        $this->assertNotContains($upcoming->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
    }

    public function test_zero_duration_cannot_cancel_a_show_with_sales(): void
    {
        Carbon::setTestNow('2026-10-09 18:00:00');
        $channel = \App\Models\WhatnotChannel::create(['name'=>'Cards','whatnot_username'=>'cards','status'=>'active']);
        $show = $this->show(['show_date'=>'2026-10-05','whatnot_channel_id'=>$channel->id,'whatnot_show_id'=>'729c7c5c-f4d9-4401-9e06-4c053b72e99d']);
        $this->mock(\App\Services\WhatnotScraper::class, function ($mock) use ($show) {
            $mock->shouldReceive('fetchHistoricalAnalytics')->once()->andReturn([[
                'whatnot_live_id'=>$show->whatnot_show_id, 'show_duration'=>0, 'gross_revenue'=>7394, 'whatnot_net'=>4864,
            ]]);
        });
        app(\App\Services\WhatnotReportingReconciler::class)->backfillAnalytics($channel, \Carbon\Carbon::parse('2026-07-01'), 10);
        $this->assertNotSame('cancelled', $show->fresh()->status);
        $this->assertSame('7394.00', $show->fresh()->gross_revenue);
    }

    public function test_recent_unavailable_and_partial_shows_retry_without_waiting_days(): void
    {
        Carbon::setTestNow('2026-10-08 18:00:00');
        $unavailable = $this->show(['show_date'=>today()->subDays(2), 'analytics_sync_status'=>'unavailable', 'analytics_unavailable_at'=>now()->subHours(2)]);
        $partial = $this->show(['show_date'=>today()->subDay(), 'analytics_sync_status'=>'partial', 'last_analytics_synced_at'=>now()->subHours(2)]);
        $cooling = $this->show(['analytics_sync_status'=>'unavailable', 'analytics_unavailable_at'=>now()->subMinutes(20)]);
        $historical = $this->show(['show_date'=>today()->subDays(30), 'analytics_sync_status'=>'unavailable', 'analytics_unavailable_at'=>now()->subDays(2)]);
        $ids = Show::missingAnalytics()->pluck('id')->all();
        $this->assertContains($unavailable->id, $ids);
        $this->assertContains($partial->id, $ids);
        $this->assertNotContains($cooling->id, $ids);
        $this->assertNotContains($historical->id, $ids);
    }
}

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
        // Show times are local (Central) times of day.
        $local = fn (int $minutes) => \App\Support\BusinessTime::local()->addMinutes($minutes)->format('H:i:s');
        $ended = $this->show(['end_time' => $local(-45)]);
        $justEnded = $this->show(['raw_import_payload'=>['_seller_hub_state'=>'past'], 'end_time'=>$local(-10)]);
        $completed = $this->show(['raw_import_payload'=>['_seller_hub_state'=>'past']]);
        $upcoming = $this->show(['end_time'=>$local(60)]);
        $cancelled = $this->show(['status'=>'cancelled', 'end_time'=>now()->subHour()]);
        $ids = Show::readyForAnalytics()->pluck('id')->all();
        $this->assertContains($ended->id, $ids);
        $this->assertContains($completed->id, $ids);
        // Analytics start as soon as a show ends (Seller Hub is scraped immediately on show end).
        $this->assertContains($justEnded->id, $ids);
        $this->assertNotContains($upcoming->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
    }

    public function test_a_show_counts_as_ended_when_the_shows_overview_says_it_ended(): void
    {
        Carbon::setTestNow('2026-10-08 18:00:00'); // 1:00 pm Central
        $local = fn (int $minutes) => \App\Support\BusinessTime::local()->addMinutes($minutes)->format('H:i:s');
        // No end time recorded (common for shows made from the Whatnot schedule): start + 3 hours.
        $noEndOver = $this->show(['start_time' => $local(-240)]);
        $noEndLive = $this->show(['start_time' => $local(-60)]);
        // A known length is used when there is no end time.
        $shortShow = $this->show(['start_time' => $local(-90), 'show_duration' => 60]);
        $ids = Show::readyForAnalytics()->pluck('id')->all();
        $this->assertContains($noEndOver->id, $ids);
        $this->assertNotContains($noEndLive->id, $ids);
        $this->assertContains($shortShow->id, $ids);
        foreach ([$noEndOver, $noEndLive, $shortShow] as $show) {
            $this->assertSame(in_array($show->id, $ids, true), $show->fresh()->scheduleStateAt(\App\Support\BusinessTime::local()) === 'done', 'readyForAnalytics and the overview disagree about show '.$show->id);
        }
    }

    public function test_whatnot_saying_a_show_ended_beats_the_schedule(): void
    {
        Carbon::setTestNow('2026-10-08 18:00:00'); // 1:00 pm Central
        $local = fn (int $minutes) => \App\Support\BusinessTime::local()->addMinutes($minutes)->format('H:i:s');
        // Ended early: still "scheduled" until 3 pm, but Whatnot says it's over.
        $endedEarly = $this->show(['start_time' => $local(-60), 'end_time' => $local(120), 'raw_import_payload' => ['_whatnot_api_state' => 'ended']]);
        // Running long: the schedule says it's over, Whatnot still has it going.
        $overrunning = $this->show(['start_time' => $local(-300), 'end_time' => $local(-60), 'raw_import_payload' => ['_whatnot_api_state' => 'scheduled']]);
        $live = $this->show(['start_time' => $local(-300), 'raw_import_payload' => ['_whatnot_api_state' => 'live']]);
        $ids = Show::readyForAnalytics()->pluck('id')->all();
        $this->assertContains($endedEarly->id, $ids);
        $this->assertNotContains($overrunning->id, $ids);
        $this->assertNotContains($live->id, $ids);
        $this->assertSame('done', $endedEarly->fresh()->scheduleStateAt(\App\Support\BusinessTime::local()));
        $this->assertSame('live', $live->fresh()->scheduleStateAt(\App\Support\BusinessTime::local()));
    }

    public function test_the_api_sync_records_that_a_show_ended_and_its_real_end_time(): void
    {
        Carbon::setTestNow('2026-10-08 18:00:00');
        app()->detectEnvironment(fn () => 'production');
        config()->set('services.whatnot_seller.live_requests_enabled', true);
        config()->set('services.whatnot_seller.contract_verified', true);
        config()->set('services.whatnot_seller.apply_enabled', true);
        $channel = \App\Models\WhatnotChannel::create(['name' => 'Cards', 'whatnot_username' => 'cards', 'include_in_import' => true, 'status' => 'active']);
        $id = '67e453cd-1324-43f8-84a1-7cb3cd9b91ff';
        $show = $this->show(['title' => 'Monday Break', 'whatnot_channel_id' => $channel->id, 'whatnot_show_id' => $id,
            'start_time' => '11:00:00', 'end_time' => '15:00:00', 'raw_import_payload' => ['_whatnot_api_state' => 'scheduled']]);
        $this->assertNotContains($show->id, Show::readyForAnalytics()->pluck('id')->all());

        $importer = app(\App\Services\WhatnotApiShowImporter::class);
        $importer->import($channel->id, ['id' => $id, 'title' => 'Monday Break', 'status' => 'ended',
            'scheduled_start_at' => '2026-10-08T16:00:00Z', 'ended_at' => '2026-10-08T17:42:00Z'], false);

        $show->refresh();
        $this->assertSame('ended', $show->raw_import_payload['_whatnot_api_state']);
        $this->assertSame('12:42', $show->end_time->format('H:i'));

        // A second sync with the same answer changes nothing and logs nothing.
        $logs = \App\Models\ShowChangeLog::where('show_id', $show->id)->count();
        $again = $importer->import($channel->id, ['id' => $id, 'title' => 'Monday Break', 'status' => 'ended',
            'scheduled_start_at' => '2026-10-08T16:00:00Z', 'ended_at' => '2026-10-08T17:42:00Z'], false);
        $this->assertSame('unchanged', $again['outcome']);
        $this->assertSame($logs, \App\Models\ShowChangeLog::where('show_id', $show->id)->count());
        $this->assertContains($show->id, Show::readyForAnalytics()->pluck('id')->all());
    }

    public function test_an_overnight_show_is_not_ended_before_midnight(): void
    {
        Carbon::setTestNow('2026-10-09 04:30:00'); // 11:30 pm Central on Oct 8
        $overnight = $this->show(['show_date' => '2026-10-08', 'start_time' => '23:00:00', 'end_time' => '01:00:00']);
        $this->assertNotContains($overnight->id, Show::readyForAnalytics()->pluck('id')->all());
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

<?php

namespace Tests\Feature\Reliability;

use App\Filament\Pages\DashboardImproved;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        Carbon::setTestNow('2026-10-12 12:00:00');
        ChannelContext::setActive(null);
        $owner=User::firstWhere('email',config('app.owner_email')) ?? User::factory()->create(['email'=>config('app.owner_email')]);
        $this->actingAs($owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        ChannelContext::flushMemo();
        parent::tearDown();
    }

    public function test_chart_uses_current_month_metrics_and_channel_context(): void
    {
        $a=WhatnotChannel::create(['name'=>'Channel A','status'=>'active']);
        $b=WhatnotChannel::create(['name'=>'Channel B','status'=>'active']);
        Show::withoutEvents(function () use ($a,$b) {
            Show::create(['title'=>'A show','whatnot_channel_id'=>$a->id,'show_date'=>'2026-10-12','status'=>'draft','gross_revenue'=>120,'completed_earnings'=>90,'whatnot_net'=>110,'show_duration'=>60,'units_sold'=>4]);
            Show::create(['title'=>'B show','whatnot_channel_id'=>$b->id,'show_date'=>'2026-10-03','status'=>'draft','gross_revenue'=>250,'completed_earnings'=>null,'whatnot_net'=>200,'show_duration'=>120,'units_sold'=>6]);
            Show::create(['title'=>'Last month','whatnot_channel_id'=>$a->id,'show_date'=>'2026-09-20','status'=>'draft','gross_revenue'=>5000,'completed_earnings'=>4000]);
            Show::create(['title'=>'Cancelled','whatnot_channel_id'=>$a->id,'show_date'=>'2026-10-12','status'=>'cancelled','gross_revenue'=>10000,'completed_earnings'=>9000]);
        });
        $overview=app(DashboardImproved::class)->getViewData()['dashboardOverview'];
        // Only the current month is charted; last month's show is left out.
        $this->assertSame([['key'=>'2026-10','label'=>'Oct']],$overview['months']);
        $this->assertSame('October 2026 so far',$overview['period']);
        $this->assertSame(120.0,$overview['series'][0]['values']['gross'][0]);
        $this->assertSame(90.0,$overview['series'][0]['values']['net'][0]);
        $this->assertSame(['gross','net'],array_keys($overview['series'][0]['values']));
        $this->assertSame(250.0,$overview['series'][1]['values']['gross'][0]);
        // Not settled yet: net falls back to Whatnot's estimated earnings.
        $this->assertSame(200.0,$overview['series'][1]['values']['net'][0]);
        $this->assertSame(1,$overview['shows_today']);
        ChannelContext::setActive($a->id);
        $scoped=app(DashboardImproved::class)->getViewData()['dashboardOverview'];
        $this->assertSame(['Channel A'],array_column($scoped['series'],'name'));
        $this->assertSame(1,$scoped['active_channels']);
        $this->get(DashboardImproved::getUrl())->assertOk()->assertSee('Channel Performance')->assertSee('Today at a glance');
    }
}

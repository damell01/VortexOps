<?php

namespace Tests\Feature\Shows;

use App\Livewire\StreamerHome;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamerPerformanceKpisTest extends TestCase
{
    use RefreshDatabase;

    private Streamer $mine;
    private Streamer $other;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00', 'America/Chicago'));
        $user = User::factory()->create(['timezone' => 'America/Chicago', 'buddy' => 'eevee']);
        $user->assignRole(Role::findOrCreate('streamer', 'web'));
        $this->mine = Streamer::create(['name' => 'Mine', 'user_id' => $user->id, 'status' => 'active']);
        $this->other = Streamer::create(['name' => 'Other', 'status' => 'active']);
        $this->channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $date = '2026-10-19', float $sales = 1000, int $minutes = 120, array $extra = []): Show
    {
        $show = Show::withoutEvents(fn () => Show::create($extra + ['title' => 'My show '.$date, 'show_date' => $date, 'start_time' => '18:00:00', 'show_duration' => $minutes, 'gross_revenue' => $sales, 'whatnot_net' => 999, 'completed_earnings' => 600, 'units_sold' => 20, 'status' => 'reconciled', 'whatnot_channel_id' => $this->channel->id]));
        $show->streamers()->attach($this->mine->id, ['is_primary' => true]);
        return $show;
    }

    private function pay(Show $show, float $amount, string $status, ?Streamer $host = null): Payout
    {
        return Payout::create(['show_id' => $show->id, 'streamer_id' => ($host ?? $this->mine)->id, 'payout_type' => 'flat_rate', 'calculated_payout' => $amount, 'status' => $status]);
    }

    public function test_totals_and_pay_buckets_stay_personal_even_on_cohosted_shows(): void
    {
        $show = $this->show();
        $show->streamers()->attach($this->other->id);
        $this->pay($show, 100, 'paid');
        $this->pay($show, 50, 'approved');
        $this->pay($show, 25, 'draft');
        $this->pay($show, 9999, 'paid', $this->other);
        $page = Livewire::test(StreamerHome::class)->assertSee('Your performance')->assertSee('Amount paid')->assertSee('Sales / streaming hour');
        $n = $page->instance()->numbers();
        $this->assertSame(1, $n['shows']);
        $this->assertSame(1000.0, $n['gross']);
        $this->assertSame(100.0, $n['paid']);
        $this->assertSame(50.0, $n['unpaid']);
        $this->assertSame(25.0, $n['draft_pay']);
        $this->assertSame(600.0, $n['earnings']);
        $this->assertSame(2.0, $n['hours']);
        $this->assertSame(500.0, $n['gross_per_hour']);
        $page->assertDontSee('9,999.00');
    }

    public function test_week_month_and_year_views_have_exact_show_date_and_pay_boundaries(): void
    {
        foreach (['2026-10-19', '2026-10-12', '2026-09-25', '2026-01-10', '2025-12-30'] as $date) $this->pay($this->show($date), 10, 'paid');
        $page = Livewire::test(StreamerHome::class);
        foreach (['week' => 1, 'last_week' => 1, 'month' => 2, 'last_month' => 1, 'year' => 4, 'last_year' => 1] as $period => $count) {
            $page->set('period', $period)->assertOk();
            $n = $page->instance()->numbers();
            $this->assertSame($count, $n['shows'], $period);
            $this->assertSame($count * 10.0, $n['paid'], $period);
        }
        $page->set('period', 'bad-value')->assertSet('period', 'week');
    }

    public function test_channel_scope_excludes_other_channel_pay_and_show_results(): void
    {
        $this->pay($this->show(), 40, 'paid');
        $other = WhatnotChannel::create(['name' => 'Cards', 'status' => 'active']);
        $this->pay($this->show(extra: ['whatnot_channel_id' => $other->id]), 900, 'approved');
        ChannelContext::setActive($this->channel->id);
        $n = app(StreamerHome::class)->numbers();
        $this->assertSame(1, $n['shows']);
        $this->assertSame(40.0, $n['paid']);
        $this->assertSame(0.0, $n['unpaid']);
    }

    public function test_unknown_earnings_and_duration_do_not_become_zeroes_or_distort_hourly_sales(): void
    {
        $this->show(sales: 100, minutes: 60, extra: ['completed_earnings' => 0]);
        $this->show(sales: 200, minutes: 240, extra: ['completed_earnings' => -50]);
        $this->show(sales: 9999, minutes: 0, extra: ['completed_earnings' => null, 'whatnot_net' => 0, 'analytics_sync_status' => null]);
        $n = app(StreamerHome::class)->numbers();
        $this->assertSame(-50.0, $n['earnings']);
        $this->assertSame(1, $n['earnings_pending']);
        $this->assertSame(60.0, $n['gross_per_hour']);
        $this->assertSame(5.0, $n['hours']);
        $this->assertSame(2, $n['timed_shows']);
    }

    public function test_fallback_hours_use_only_the_signed_in_streamers_report(): void
    {
        $show = $this->show(sales: 400, minutes: 0);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->mine->id, 'status' => 'pending', 'hours_streamed' => 4]);
        $otherReport = $this->show(sales: 9000, minutes: 0);
        StreamerLogEntry::create(['show_id' => $otherReport->id, 'streamer_id' => $this->other->id, 'status' => 'pending', 'hours_streamed' => 9]);
        $n = app(StreamerHome::class)->numbers();
        $this->assertSame(4.0, $n['hours']);
        $this->assertSame(100.0, $n['gross_per_hour']);
    }

    public function test_cancelled_future_and_other_peoples_shows_never_inflate_totals(): void
    {
        $this->show();
        $cancelled = $this->show(extra: ['status' => 'cancelled']);
        $this->pay($cancelled, 888, 'paid');
        $future = $this->show('2026-10-21');
        $this->pay($future, 999, 'draft');
        $this->show('2026-10-20', extra: ['start_time' => '23:00:00']);
        $other = $this->show();
        $other->streamers()->sync([$this->other->id]);
        $this->pay($other, 777, 'paid', $this->other);
        $n = app(StreamerHome::class)->numbers();
        $this->assertSame(1, $n['shows']);
        $this->assertSame(1000.0, $n['gross']);
        $this->assertSame(0.0, $n['paid']);
        $this->assertSame(0.0, $n['draft_pay']);
    }

    public function test_paid_history_remains_visible_after_assignment_removed_but_missing_profiles_see_nothing(): void
    {
        $show = $this->show();
        $this->pay($show, 125, 'paid');
        $show->streamers()->detach($this->mine->id);
        $this->assertSame(125.0, app(StreamerHome::class)->numbers()['paid']);
        $orphan = User::factory()->create();
        $orphan->assignRole('streamer');
        $this->actingAs($orphan);
        $n = app(StreamerHome::class)->numbers();
        $this->assertSame(0, $n['shows']);
        $this->assertSame(0.0, $n['paid']);
        $this->assertNull($n['earnings']);
        $this->assertNull($n['gross_per_hour']);
    }

    public function test_period_uses_central_calendar_even_when_utc_is_already_next_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 03:00', 'UTC')); // Oct 31, 10pm Central
        $this->show('2026-10-31');
        $page = app(StreamerHome::class);
        $page->period = 'month';
        $this->assertSame('2026-10-01', $page->performanceDates()[0]->toDateString());
        $this->assertSame(1, $page->numbers()['shows']);
    }
}

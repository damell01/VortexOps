<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\ShowPerformance;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\ProfitLossReport;
use App\Services\ShowPerformanceReport;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Show Performance: the heatmap and leaderboard use Business Profitability's
 * rows, and a show that isn't finalized never counts toward net.
 */
class ShowPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private WhatnotChannel $breaks;
    private WhatnotChannel $cards;
    private Streamer $alex;
    private Streamer $jordan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 18:00:00');
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->owner);
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();
        $this->breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $this->cards = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
        $this->alex = Streamer::create(['name' => 'Alex', 'status' => 'active']);
        $this->jordan = Streamer::create(['name' => 'Jordan', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(Streamer $host, string $date, float $net, float $cogs, float $pay, array $extra = [], bool $finalPay = true): Show
    {
        $show = Show::create($extra + [
            'whatnot_channel_id' => $this->breaks->id, 'title' => "{$host->name} {$date}", 'show_date' => $date,
            'start_time' => $date.' 20:00:00', 'gross_revenue' => $net + 100, 'whatnot_net' => $net, 'tips' => 0,
            'status' => 'reconciled', 'is_operational' => true, 'created_by' => $this->owner->id,
        ]);
        $show->streamers()->attach($host->id, ['is_primary' => true]);
        if ($cogs > 0) {
            $item = InventoryItem::create(['name' => 'Box '.$show->id, 'is_active' => true]);
            $loc = InventoryLocation::firstOrCreate(['name' => 'Main']);
            $dr = DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $host->id, 'status' => 'approved']);
            DeductionRequestLine::create(['deduction_request_id' => $dr->id, 'inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id, 'quantity_suggested' => 1, 'quantity_approved' => 1, 'unit_cost_snapshot' => $cogs, 'raw_description' => 'x', 'line_total' => $cogs]);
        }
        if ($pay > 0) {
            $batch = $finalPay ? (WeeklyPayoutBatch::whereDate('week_start', Carbon::parse($date)->startOfWeek()->toDateString())->first() ?? WeeklyPayoutBatch::create(['week_start' => Carbon::parse($date)->startOfWeek()->toDateString(), 'week_end' => Carbon::parse($date)->endOfWeek()->toDateString(), 'status' => 'finalized'])) : null;
            Payout::create(['streamer_id' => $host->id, 'show_id' => $show->id, 'payout_type' => 'profit_share', 'calculated_payout' => $pay, 'gross_show_revenue' => $net + 100, 'status' => $finalPay ? 'approved' : 'draft', 'weekly_payout_batch_id' => $batch?->id]);
        }

        return $show;
    }

    private function report(): array
    {
        $rows = app(ProfitLossReport::class)->rows(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-15'));
        $durations = Show::pluck('show_duration', 'id')->map(fn ($m) => (float) $m)->all();

        return [app(ShowPerformanceReport::class), app(ShowPerformanceReport::class)->enrich($rows, $durations)];
    }

    public function test_net_per_hour_is_profit_over_hours_streamed_for_the_slot(): void
    {
        // Tuesday 8 PM, two hours, $1000 earned − $300 COGS − $200 pay = $500.
        $this->show($this->alex, '2026-10-13', 1000, 300, 200, ['show_duration' => 120]);
        $this->show($this->jordan, '2026-10-06', 600, 100, 100, ['show_duration' => 60]);

        [$svc, $rows] = $this->report();
        $cell = $svc->heatmap($rows)['cells'][1][10];

        $this->assertSame(2, $cell['shows']);
        $this->assertSame(900.0, $cell['net']);
        $this->assertSame(300.0, $cell['net_hour']); // $900 over 3 hours, not an average of rates
        $this->assertSame(250.0, $rows->firstWhere('streamer', 'Alex')['net_hour']);
    }

    public function test_a_pending_show_counts_for_gross_but_never_for_net(): void
    {
        $this->show($this->alex, '2026-10-13', 1000, 300, 200, ['show_duration' => 120]);
        $this->show($this->jordan, '2026-10-14', 5000, 100, 80, ['show_duration' => 60], false); // pay not final

        [$svc, $rows] = $this->report();
        $t = $svc->totals($rows);

        $this->assertSame(1, $t['pending']);
        $this->assertSame(500.0, $t['net']);
        $this->assertSame(250.0, $t['net_hour']);
        $this->assertSame(['Alex 2026-10-13'], $svc->leaderboard($rows, 'net')->pluck('title')->all());
        $this->assertSame('Jordan 2026-10-14', $svc->leaderboard($rows, 'gross')->first()['title']);
        $this->assertNull($svc->heatmap($rows)['cells'][2][10]['net_hour']);
    }

    public function test_one_lucky_show_does_not_top_the_best_times_once_real_slots_exist(): void
    {
        $this->show($this->alex, '2026-10-13', 3000, 0, 100, ['show_duration' => 60, 'start_time' => '2026-10-13 14:00:00']);
        foreach (['2026-10-01', '2026-10-08', '2026-10-15'] as $thursday) {
            $this->show($this->jordan, $thursday, 600, 50, 50, ['show_duration' => 120]);
        }

        [$svc, $rows] = $this->report();
        $best = $svc->bestTimes($svc->heatmap($rows), 'net_hour');

        $this->assertFalse($best['thin']);
        $this->assertCount(1, $best['rows']);
        $this->assertSame([3, 10], [$best['rows'][0]['day'], $best['rows'][0]['block']]);
    }

    public function test_the_page_renders_crowns_a_champion_and_opens_a_slot(): void
    {
        $this->show($this->alex, '2026-10-13', 1000, 300, 200, ['show_duration' => 120]);

        $this->get(ShowPerformance::getUrl())->assertOk()->assertSee('Best Times to Stream')->assertSee('Champion Show');

        Livewire::test(ShowPerformance::class)
            ->assertSee('Alex 2026-10-13')
            ->call('openCell', 1, 10)
            ->assertSet('cell', '1-10')
            ->assertSee('Tuesdays, 8 PM – 10 PM')
            ->call('setBoard', 'gross')->assertSet('board', 'gross')
            ->call('setMetric', 'nonsense')->assertSet('metric', 'net_hour');
    }

    public function test_streamers_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(ShowPerformance::getUrl())->assertForbidden();
    }
}

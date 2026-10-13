<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\ProfitLoss;
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
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** The admin Profit & Loss report: per-show figures match the show's own P&L, and gaps are marked. */
class ProfitLossReportTest extends TestCase
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

    public function test_each_show_is_its_own_profit_and_loss_and_the_totals_add_up(): void
    {
        $a = $this->show($this->alex, '2026-10-13', 1000, 300, 200);
        $this->show($this->jordan, '2026-10-14', 400, 350, 150); // loses $100
        $this->show($this->jordan, '2026-10-20', 999, 0, 0); // not aired yet

        $report = app(ProfitLossReport::class);
        $rows = $report->rows(Carbon::parse('2026-10-12'), Carbon::parse('2026-10-18'));

        $this->assertCount(2, $rows);
        $this->assertSame($a->fresh()->profitAndLoss()['margin'], $rows[0]['profit']);
        $this->assertSame(500.0, $rows[0]['profit']);

        $t = $report->totals($rows);
        $this->assertSame(1400.0, $t['earnings']);
        $this->assertSame(650.0, $t['cogs']);
        $this->assertSame(350.0, $t['payouts']);
        $this->assertSame(400.0, $t['profit']);
        $this->assertSame(1, $t['losing']);

        $byStreamer = $report->breakdown($rows, 'streamer')->keyBy('label');
        $this->assertSame(500.0, $byStreamer['Alex']['profit']);
        $this->assertSame(-100.0, $byStreamer['Jordan']['profit']);
        $this->assertSame(['Evening (5–9pm)'], $report->breakdown($rows, 'slot')->pluck('label')->all());
    }

    public function test_a_show_missing_a_figure_is_marked_incomplete_with_the_reason(): void
    {
        $this->show($this->alex, '2026-10-13', 1000, 0, 0);
        $this->show($this->jordan, '2026-10-14', 500, 100, 80, [], false);
        $this->show($this->jordan, '2026-10-14', 0, 100, 80, ['gross_revenue' => 300]);

        $rows = app(ProfitLossReport::class)->rows(Carbon::parse('2026-10-12'), Carbon::parse('2026-10-18'));
        $alex = $rows->firstWhere('streamer', 'Alex');

        $this->assertContains('No show report yet', $alex['incomplete']);
        $this->assertContains('Pay not calculated', $alex['incomplete']);
        $this->assertSame(['Pay not final'], $rows->first(fn ($r) => $r['earnings'] == 500)['incomplete']);
        $this->assertContains('Earnings not in yet', $rows->first(fn ($r) => $r['sales'] == 300)['incomplete']);
    }

    public function test_the_page_shows_each_view_and_filters_by_channel(): void
    {
        $this->show($this->alex, '2026-10-13', 1000, 300, 200);
        $this->show($this->jordan, '2026-10-14', 400, 350, 150, ['whatnot_channel_id' => $this->cards->id]);

        $page = Livewire::test(ProfitLoss::class)
            ->assertSee('How streamers are doing')->assertSee('Alex')->assertSee('Jordan');

        foreach (['day', 'week', 'month', 'year'] as $view) {
            $page->call('setView', $view)->assertOk();
        }

        $page->call('setView', 'week')->set('channel', (string) $this->cards->id);
        $this->assertSame(['Jordan'], $page->instance()->rows()->pluck('streamer')->all());

        $page->set('channel', '')->set('showFilter', 'losing');
        $this->assertCount(1, $page->instance()->showRows());
    }

    public function test_the_previous_period_compares_like_for_like(): void
    {
        $this->show($this->alex, '2026-10-06', 600, 100, 100);
        $this->show($this->alex, '2026-10-13', 1000, 300, 200);

        $page = Livewire::test(ProfitLoss::class)->call('setView', 'week');

        $this->assertSame(500.0, $page->instance()->totals()['profit']);
        $this->assertSame(400.0, $page->instance()->previousTotals()['profit']);
        $this->assertSame(400.0, $page->instance()->streamerTrend()->first()['prev_profit']);
    }

    public function test_streamers_cannot_open_profit_and_loss(): void
    {
        $user = User::factory()->create();
        Streamer::create(['name' => 'S', 'user_id' => $user->id, 'status' => 'active']);
        $this->actingAs($user);

        $this->assertFalse(ProfitLoss::canAccess());
    }
}

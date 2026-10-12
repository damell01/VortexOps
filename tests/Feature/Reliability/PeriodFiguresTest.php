<?php

namespace Tests\Feature\Reliability;

use App\Filament\Pages\Reports;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\PeriodFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PeriodFiguresTest extends TestCase
{
    use RefreshDatabase;

    private Streamer $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 12:00:00');
        $this->streamer = Streamer::create(['name' => 'Niko', 'status' => 'active', 'payout_type' => 'flat_rate']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Gross 1000, net 900, tips 50, cogs 200, pay 300 → profit 450. */
    private function show(WhatnotChannel $channel, string $date, array $data = [], float $cogs = 200, float $pay = 300): Show
    {
        $show = Show::withoutEvents(fn () => Show::create(array_merge([
            'title' => 'Show', 'show_date' => $date, 'status' => 'reconciled', 'whatnot_channel_id' => $channel->id,
            'gross_revenue' => 1000, 'whatnot_net' => 900, 'tips' => 50, 'show_duration' => 120, 'units_sold' => 10, 'giveaway_spend' => 40,
        ], $data)));
        if ($cogs > 0) {
            $dr = DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => 'approved']);
            DeductionRequestLine::create(['deduction_request_id' => $dr->id, 'inventory_item_id' => InventoryItem::firstOrCreate(['name' => 'Box'], ['is_active' => true])->id,
                'inventory_location_id' => InventoryLocation::firstOrCreate(['name' => 'Main'])->id, 'quantity_suggested' => 1, 'quantity_approved' => 1,
                'unit_cost_snapshot' => $cogs, 'raw_description' => 'Box', 'line_total' => $cogs]);
        }
        if ($pay > 0) {
            Payout::create(['streamer_id' => $this->streamer->id, 'show_id' => $show->id, 'payout_type' => 'flat_rate', 'calculated_payout' => $pay, 'gross_show_revenue' => 1000, 'status' => 'draft']);
        }

        return $show;
    }

    public function test_channel_profit_adds_up_each_shows_profit(): void
    {
        $breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $shop = WhatnotChannel::create(['name' => 'Vortex Shop', 'status' => 'active']);
        $this->show($breaks, '2026-10-05');
        $this->show($breaks, '2026-10-06', ['completed_earnings' => 950]); // settled: net 950
        $this->show($shop, '2026-10-07', [], cogs: 900, pay: 300);          // loses money
        $this->show($breaks, '2026-10-08', ['status' => 'cancelled']);

        $rows = (new PeriodFigures)->byChannel(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-12'));

        $this->assertSame(['Vortex Breaks', 'Vortex Shop'], array_column($rows, 'channel'));
        [$b, $s] = $rows;
        $this->assertSame(2, $b['shows']);
        $this->assertSame(2000.0, $b['gross']);
        $this->assertSame(1850.0, $b['net']);
        $this->assertSame(150.0, $b['fees']);
        $this->assertSame(400.0, $b['cogs']);
        $this->assertSame(600.0, $b['pay']);
        $this->assertSame(950.0, $b['profit']);            // 450 + 500
        $this->assertSame(475.0, $b['profit_per_show']);
        $this->assertSame(80.0, $b['giveaways']);
        $this->assertSame(4.0, $b['hours']);
        $this->assertSame(-250.0, $s['profit']);           // 900 + 50 − 900 − 300
    }

    public function test_reports_compares_the_period_with_the_one_before_and_last_year(): void
    {
        $this->enableAdminModules();
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $c = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $this->show($c, '2026-10-10');                          // this week
        $this->show($c, '2026-10-02', ['gross_revenue' => 500]); // the week before
        $this->show($c, '2025-10-08', ['gross_revenue' => 2000]); // a year ago

        $page = Livewire::test(Reports::class)->call('setPeriod', '7');
        $cmp = $page->instance()->comparison;
        $gross = collect($cmp['rows'])->firstWhere('label', 'Gross sales');
        $this->assertSame('Oct 5, 2026 – Oct 12, 2026', $cmp['a_label']);
        $this->assertSame('Sep 27, 2026 – Oct 4, 2026', $cmp['b_label']);
        $this->assertSame([1000.0, 500.0, 500.0, 100.0, true], [$gross['a'], $gross['b'], $gross['change'], $gross['change_pct'], $gross['better']]);
        $cogs = collect($cmp['rows'])->firstWhere('label', 'Cost of goods');
        $this->assertNull($cogs['better']); // same cost both weeks

        $page->set('compareMode', 'last_year');
        $gross = collect($page->instance()->comparison['rows'])->firstWhere('label', 'Gross sales');
        $this->assertSame([1000.0, 2000.0, false], [$gross['a'], $gross['b'], $gross['better']]);

        $page->assertSee('Compare two periods')->assertSee('Channel profit')->assertSee('Vortex Breaks');
    }
}

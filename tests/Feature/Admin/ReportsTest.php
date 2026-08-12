<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Reports;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_revenue_summary_includes_margin_from_order_cogs(): void
    {
        $show = Show::create([
            'title' => 'Margin Show', 'show_date' => now()->toDateString(),
            'status' => 'reconciled', 'gross_revenue' => 1000, 'created_by' => auth()->id(),
        ]);

        WhatnotShowOrder::create([
            'show_id' => $show->id, 'buyer_username' => 'b', 'item_name' => 'Item',
            'quantity' => 2, 'unit_cost' => 100, 'unit_price' => 250, 'total_price' => 500,
            'status' => 'completed', 'show_date' => $show->show_date,
        ]);

        $rev = (new Reports)->getRevenueSummaryProperty();

        $this->assertEqualsWithDelta(1000.0, $rev['gross'], 0.01);
        $this->assertEqualsWithDelta(200.0, $rev['cogs'], 0.01);   // 2 * $100 unit_cost
        $this->assertEqualsWithDelta(800.0, $rev['margin'], 0.01); // 1000 gross − 200 cogs
        $this->assertEqualsWithDelta(80.0, $rev['margin_pct'], 0.1);
    }

    public function test_revenue_summary_margin_null_pct_when_no_gross(): void
    {
        $rev = (new Reports)->getRevenueSummaryProperty();

        $this->assertEqualsWithDelta(0.0, $rev['margin'], 0.01);
        $this->assertNull($rev['margin_pct']);
    }

    public function test_revenue_summary_excludes_cancelled_shows_from_margin(): void
    {
        $cancelled = Show::create([
            'title' => 'Cancelled Show', 'show_date' => now()->toDateString(),
            'status' => 'cancelled', 'gross_revenue' => 5000, 'created_by' => auth()->id(),
        ]);

        WhatnotShowOrder::create([
            'show_id' => $cancelled->id, 'buyer_username' => 'b', 'item_name' => 'Item',
            'quantity' => 1, 'unit_cost' => 50, 'unit_price' => 100, 'total_price' => 100,
            'status' => 'completed', 'show_date' => $cancelled->show_date,
        ]);

        $rev = (new Reports)->getRevenueSummaryProperty();

        $this->assertEqualsWithDelta(0.0, $rev['gross'], 0.01);
        $this->assertEqualsWithDelta(0.0, $rev['cogs'], 0.01);
    }
}

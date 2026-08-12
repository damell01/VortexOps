<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\StreamerAnalytics;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function rowFor(StreamerAnalytics $page, int $streamerId): ?array
    {
        return collect($page->getAnalyticsRowsProperty())->firstWhere('streamer_id', $streamerId);
    }

    public function test_row_includes_margin_from_order_cogs(): void
    {
        $streamer = Streamer::create(['name' => 'Margin Streamer', 'status' => 'active']);
        $show = Show::create([
            'title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled',
            'gross_revenue' => 1000, 'created_by' => auth()->id(),
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        WhatnotShowOrder::create([
            'show_id' => $show->id, 'buyer_username' => 'b', 'item_name' => 'Item',
            'quantity' => 3, 'unit_cost' => 20, 'unit_price' => 50, 'total_price' => 150,
            'status' => 'completed', 'show_date' => $show->show_date,
        ]);

        $page = new StreamerAnalytics;
        $page->dateFrom = now()->subDays(7)->toDateString();
        $page->dateTo   = now()->toDateString();

        $row = $this->rowFor($page, $streamer->id);

        $this->assertEqualsWithDelta(60.0, $row['cogs'], 0.01);    // 3 * $20
        $this->assertEqualsWithDelta(940.0, $row['margin'], 0.01); // 1000 − 60
    }

    public function test_row_includes_trend_vs_prior_equal_length_period(): void
    {
        $streamer = Streamer::create(['name' => 'Trend Streamer', 'status' => 'active']);

        $from = now()->subDays(7);
        $to   = now();

        $current = Show::create([
            'title' => 'Current', 'show_date' => $to->toDateString(), 'status' => 'reconciled',
            'gross_revenue' => 1000, 'created_by' => auth()->id(),
        ]);
        $current->streamers()->attach($streamer->id, ['is_primary' => true]);

        // Prior period is the 7 days immediately before $from.
        $prior = Show::create([
            'title' => 'Prior', 'show_date' => $from->copy()->subDays(3)->toDateString(), 'status' => 'reconciled',
            'gross_revenue' => 500, 'created_by' => auth()->id(),
        ]);
        $prior->streamers()->attach($streamer->id, ['is_primary' => true]);

        $page = new StreamerAnalytics;
        $page->dateFrom = $from->toDateString();
        $page->dateTo   = $to->toDateString();

        $row = $this->rowFor($page, $streamer->id);

        $this->assertEqualsWithDelta(100.0, $row['trend_gross'], 0.1); // (1000-500)/500 * 100
    }

    public function test_trend_null_when_no_prior_period_revenue(): void
    {
        $streamer = Streamer::create(['name' => 'No Prior', 'status' => 'active']);
        $show = Show::create([
            'title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled',
            'gross_revenue' => 1000, 'created_by' => auth()->id(),
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $page = new StreamerAnalytics;
        $page->dateFrom = now()->subDays(7)->toDateString();
        $page->dateTo   = now()->toDateString();

        $row = $this->rowFor($page, $streamer->id);

        $this->assertNull($row['trend_gross']);
    }
}

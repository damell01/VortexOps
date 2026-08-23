<?php

namespace Tests\Feature\Shows;

use App\Filament\Widgets\ShowWhatnotAnalyticsWidget;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Everything Whatnot reported, shown rather than stored and forgotten.
 *
 * Twenty-two fields land on every scrape and seven of them reached a screen.
 * Buyers, first-time buyers, returning buyers, shares, peak viewers, total
 * views, duration and rating all had columns, were all being written, and
 * could only be read by opening the database.
 */
class ShowWhatnotAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);

        $this->actingAs($owner->fresh());

        $channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        // The figures from a real analytics page, so the shapes are the ones
        // that actually arrive.
        $this->show = Show::create([
            'whatnot_channel_id'     => $channel->id,
            'title'                  => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'              => now()->subDay()->toDateString(),
            'gross_revenue'          => 8801.00,
            'whatnot_net'            => 6496.34,
            'completed_earnings'     => -25.58,
            'units_sold'             => 98,
            'avg_order_value'        => 89.81,
            'giveaway_spend'         => 25.58,
            'giveaways_count'        => 59,
            'buyers_count'           => 29,
            'first_time_buyers'      => 4,
            'returning_buyers'       => 25,
            'shares_count'           => 45,
            'max_concurrent_viewers' => 205,
            'total_views'            => 3931,
            'import_source'          => 'auto_whatnot',
            'created_by'             => $owner->id,
        ]);
    }

    /** @return array<string, string|null> every metric keyed by its label */
    private function metrics(): array
    {
        $flat = [];

        foreach ($this->show->fresh()->whatnotAnalytics() as $metrics) {
            foreach ($metrics as $metric) {
                $flat[$metric['label']] = $metric['value'];
            }
        }

        return $flat;
    }

    public function test_the_sales_figures_are_reported(): void
    {
        $m = $this->metrics();

        $this->assertSame('$8,801.00', $m['Estimated sales']);
        $this->assertSame('$6,496.34', $m['Total est. earnings']);
        $this->assertSame('98', $m['Orders']);
        $this->assertSame('$89.81', $m['Average order value']);
        $this->assertSame('59', $m['Giveaways']);
    }

    public function test_the_audience_figures_are_reported(): void
    {
        // The half that had columns and no screen at all.
        $m = $this->metrics();

        $this->assertSame('29', $m['Buyers']);
        $this->assertSame('4', $m['First-time buyers']);
        $this->assertSame('25', $m['Returning buyers']);
        $this->assertSame('45', $m['Shares']);
        $this->assertSame('205', $m['Max concurrent viewers']);
        $this->assertSame('3,931', $m['Total views']);
    }

    public function test_a_negative_settled_figure_is_kept_negative(): void
    {
        // Completed earnings genuinely go negative when refunds outrun
        // settlement, and showing it as a positive would invert the fact.
        $this->assertSame('-$25.58', $this->metrics()['Completed earnings']);
    }

    public function test_a_figure_whatnot_did_not_report_is_a_dash_not_a_zero(): void
    {
        // "No rating yet" and "a rating of zero" are different facts, and a
        // zero here would state the one that is not true.
        //
        // Only the nullable columns can carry this distinction. tips,
        // gross_revenue, whatnot_net and units_sold are NOT NULL and default
        // to zero, so $0.00 tips is a real answer there rather than a missing
        // one — which is why the panel asks the import source, not the values,
        // whether Whatnot reported anything at all.
        $this->assertNull($this->metrics()['Average order rating']);
        $this->assertSame('$0.00', $this->metrics()['Tips']);
    }

    public function test_duration_is_shown_in_hours_and_minutes(): void
    {
        $this->show->update(['show_duration' => 154]);

        $this->assertSame('2h 34m', $this->metrics()['Show duration']);
    }

    public function test_a_short_show_is_shown_in_minutes_alone(): void
    {
        $this->show->update(['show_duration' => 45]);

        $this->assertSame('45m', $this->metrics()['Show duration']);
    }

    // ── The widget ────────────────────────────────────────────────────────────

    private function widget(): ShowWhatnotAnalyticsWidget
    {
        $widget = new ShowWhatnotAnalyticsWidget;
        $widget->record = $this->show->fresh();

        return $widget;
    }

    public function test_a_show_with_analytics_reports_having_them(): void
    {
        $this->assertTrue($this->widget()->getHasAnyProperty());
    }

    public function test_a_hand_made_show_says_there_is_nothing_rather_than_printing_dashes(): void
    {
        // A show added by hand has none of this and never will. Sixteen
        // dashes says the same thing far less clearly than one sentence.
        $channel = WhatnotChannel::create(['name' => 'Other', 'status' => 'active']);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Added by hand',
            'show_date'          => now()->toDateString(),
            'created_by'         => auth()->id(),
        ]);

        // From the column default, so it is only on the instance after a
        // round trip — which is what the widget reads anyway.
        $this->assertSame('manual', $this->show->fresh()->import_source);

        $this->assertFalse($this->widget()->getHasAnyProperty());
    }

    public function test_every_metric_carries_a_label_and_a_hint_slot(): void
    {
        // The view reads all three keys on every row, so a metric added
        // without one of them renders an undefined-index error on a page
        // people open daily.
        foreach ($this->show->whatnotAnalytics() as $group => $metrics) {
            $this->assertNotEmpty($metrics, "group {$group} has no metrics");

            foreach ($metrics as $metric) {
                $this->assertArrayHasKey('label', $metric);
                $this->assertArrayHasKey('value', $metric);
                $this->assertArrayHasKey('hint', $metric);
            }
        }
    }
}

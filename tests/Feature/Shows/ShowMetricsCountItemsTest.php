<?php

namespace Tests\Feature\Shows;

use App\Filament\Widgets\ShowMetricsWidget;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the show reports about a stream: how much went out, and how.
 *
 * There used to be a "Map Items Manually" action that walked the Whatnot
 * orders and wrote a deduction line per lot. It produced rows reading "Lot
 * #42" and "Lot #42 — Item #42" — Whatnot names a lot whether or not a human
 * did — and it fed nothing: inventory is posted from the streamer's End of
 * Stream report, where the person who held the stock records what they
 * actually used. Those lines were a second list to reconcile against the
 * first.
 *
 * A count is what is wanted off Whatnot, so a count is what this checks.
 */
class ShowMetricsCountItemsTest extends TestCase
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

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'          => now()->subDay()->toDateString(),
            'gross_revenue'      => 8801.00,
            'units_sold'         => 98,
            'created_by'         => $owner->id,
        ]);
    }

    /** @return array<string, array{value: string, sub: string}> keyed by label */
    private function stats(): array
    {
        $widget = new ShowMetricsWidget;
        $widget->record = $this->show->fresh();

        return collect($widget->getStats())
            ->keyBy('label')
            ->map(fn ($stat) => ['value' => $stat['value'], 'sub' => $stat['sub']])
            ->all();
    }

    private function order(int $quantity): void
    {
        WhatnotShowOrder::create([
            'show_id'          => $this->show->id,
            'whatnot_order_id' => 'ORD-' . uniqid(),
            'buyer_username'   => 'buyer',
            'item_name'        => 'Lot',
            'quantity'         => $quantity,
            'unit_price'       => 20,
            'total_price'      => 20 * $quantity,
        ]);
    }

    public function test_revenue_is_the_net_whatnot_pays_not_the_gross_buyers_were_charged(): void
    {
        // Gross is what buyers were charged; the business never sees
        // Whatnot's cut of it. Every figure derived from gross — margin most
        // of all — was flattering by the size of the fee.
        $this->show->update(['gross_revenue' => 1000, 'whatnot_net' => 820]);

        $stats = $this->stats();

        $this->assertSame('Net Revenue', array_key_first(
            array_filter($stats, fn ($v, $k) => $k === 'Net Revenue', ARRAY_FILTER_USE_BOTH),
        ));
        $this->assertSame('$820.00', $stats['Net Revenue']['value']);
        $this->assertStringContainsString('180.00', $stats['Net Revenue']['sub']);
        $this->assertStringContainsString('Whatnot fees', $stats['Net Revenue']['sub']);
    }

    public function test_margin_is_measured_against_the_net(): void
    {
        $this->show->update(['gross_revenue' => 1000, 'whatnot_net' => 800]);

        // No streamer log, so cost is zero and the whole net is margin —
        // what matters here is which revenue the percentage is a share of.
        $this->assertStringContainsString('of net', $this->stats()['Margin']['sub']);
    }

    public function test_gross_stands_in_until_the_net_has_been_synced(): void
    {
        // The state every show is in for its first hours. Showing $0.00 there
        // would read as a show that took nothing, so the gross stands in —
        // labelled as gross, so nobody reads a fee-inclusive number as net.
        $this->show->update(['gross_revenue' => 1000, 'whatnot_net' => 0]);

        $stats = $this->stats();

        $this->assertArrayNotHasKey('Net Revenue', $stats);
        $this->assertSame('$1,000.00', $stats['Revenue']['value']);
        $this->assertStringContainsString('not synced', $stats['Revenue']['sub']);
    }

    public function test_the_whatnot_item_count_is_gone(): void
    {
        // Whatnot's count and the streamer's log are two records of the same
        // night that differ for ordinary reasons — giveaways and promos have
        // no Whatnot order at all — so the difference read as a discrepancy
        // on nights where nothing was wrong. The shipment count is the count
        // that matters.
        $this->order(3);

        $stats = $this->stats();

        $this->assertArrayNotHasKey('Items Sold', $stats);
        $this->assertArrayHasKey('Shipments', $stats);
    }

    public function test_the_reconciliation_widget_is_not_on_the_show_page(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(\App\Filament\Resources\ShowResource\Pages\ViewShow::class))->getFileName(),
        );

        $this->assertStringNotContainsString('ShowItemReconciliationWidget::class', $source);
        $this->assertFalse(class_exists(\App\Filament\Widgets\ShowItemReconciliationWidget::class));
    }

    public function test_giveaways_are_reported(): void
    {
        // The other half of what left the shelf. With no figure for it, a
        // stock count that does not match sales looks like a mistake.
        $this->show->update(['giveaways_count' => 59, 'giveaway_spend' => 25.58]);

        $this->assertSame('59', $this->stats()['Giveaways']['value']);
        $this->assertStringContainsString('25.58', $this->stats()['Giveaways']['sub']);
    }

    public function test_a_show_with_no_giveaways_says_so_rather_than_nothing(): void
    {
        $this->assertSame('0', $this->stats()['Giveaways']['value']);
        $this->assertStringContainsString('No giveaway spend', $this->stats()['Giveaways']['sub']);
    }

    public function test_the_per_lot_mapping_action_is_gone(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(\App\Filament\Resources\ShowResource\Pages\ViewShow::class))->getFileName(),
        );

        $this->assertStringNotContainsString("Action::make('map_manually')", $source);
    }
}

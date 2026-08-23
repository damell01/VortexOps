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

    public function test_items_sold_counts_items_not_orders(): void
    {
        // Three of one lot in a single order is three items. Counting rows
        // reported it as one, which understates every multi-buy on the show.
        $this->order(3);
        $this->order(1);

        $this->assertSame('4', $this->stats()['Items Sold']['value']);
    }

    public function test_the_order_count_is_still_shown_underneath(): void
    {
        $this->order(3);
        $this->order(1);

        $this->assertSame('2 orders', $this->stats()['Items Sold']['sub']);
    }

    public function test_whatnots_own_total_stands_in_until_orders_are_imported(): void
    {
        // The state every show is in for its first hours: analytics have
        // landed, the order rows have not. Reporting zero there reads as a
        // show that sold nothing.
        $this->assertSame('98', $this->stats()['Items Sold']['value']);
        $this->assertStringContainsString('not imported yet', $this->stats()['Items Sold']['sub']);
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

<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\ShowResource\Pages\ViewShow;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Opening a show is the page the business spends its day on, and it had no
 * test that simply rendered it.
 *
 * When it broke in production the failure was invisible twice over: the page
 * threw, and then Laravel's own debug renderer threw while trying to display
 * what had gone wrong — so what reached the screen was a syntax error inside
 * the error page, saying nothing about the show. A render test is the cheapest
 * thing that would have caught the first failure before a deploy carried it.
 */
class ViewShowSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->user);

        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
    }

    private function show(array $overrides = []): Show
    {
        return Show::create(array_merge([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'          => '2026-08-20',
            'created_by'         => $this->user->id,
        ], $overrides));
    }

    public function test_a_bare_show_renders(): void
    {
        Livewire::test(ViewShow::class, ['record' => $this->show()->id])->assertOk();
    }

    public function test_a_show_with_orders_renders(): void
    {
        $show = $this->show(['gross_revenue' => 8801.00, 'units_sold' => 98]);

        foreach (range(1, 3) as $i) {
            WhatnotShowOrder::create([
                'show_id'          => $show->id,
                'whatnot_order_id' => "ORD-{$i}",
                'buyer_username'   => "buyer{$i}",
                'item_name'        => "Lot {$i}",
                'quantity'         => 1,
                'unit_price'       => 89.81,
                'total_price'      => 89.81,
            ]);
        }

        Livewire::test(ViewShow::class, ['record' => $show->id])->assertOk();
    }

    public function test_a_show_with_a_streamer_attached_renders(): void
    {
        $show = $this->show();

        $streamer = Streamer::create([
            'whatnot_channel_id' => $this->channel->id,
            'name'               => 'Tyler',
            'payout_type'        => 'profit_share',
            'payout_percentage'  => 20,
        ]);

        $show->streamers()->attach($streamer->id);

        Livewire::test(ViewShow::class, ['record' => $show->id])->assertOk();
    }

    public function test_a_show_with_no_financials_renders(): void
    {
        // The state every scraped-but-unenriched show lands in. gross_revenue,
        // whatnot_net and units_sold are NOT NULL and fall back to zero; the
        // rest genuinely arrive null, and formatting a null as currency is the
        // classic way a view like this throws.
        $show = $this->show([
            'gross_revenue'      => 0,
            'whatnot_net'        => 0,
            'units_sold'         => 0,
            'completed_earnings' => null,
            'avg_order_value'    => null,
            'buyers_count'       => null,
            'total_views'        => null,
            'show_duration'      => null,
            'start_time'         => null,
        ]);

        Livewire::test(ViewShow::class, ['record' => $show->id])->assertOk();
    }
}

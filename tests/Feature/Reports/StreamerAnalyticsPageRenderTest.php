<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\StreamerAnalytics;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The redesigned Streamer Analytics page: header, stat cards, the top-by-gross
 * bars, the searchable / sortable / paged streamer cards, the full comparison
 * table and the weekly tab — all from real rows.
 */
class StreamerAnalyticsPageRenderTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = WhatnotChannel::create(['name' => 'Render Channel', 'status' => 'active']);
        Setting::set('enabled_admin_modules', json_encode(['streams', 'reporting', 'payouts']));
        AdminModules::flushMemo();

        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
    }

    private function streamerWithShow(string $name, float $gross, int $minutes = 120): Streamer
    {
        $streamer = Streamer::create(['name' => $name, 'status' => 'active', 'payout_type' => 'flat_rate', 'flat_rate' => 100]);

        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => $name . ' show',
            'show_date'          => now()->toDateString(),
            'gross_revenue'      => $gross,
            'whatnot_net'        => $gross * 0.8,
            'units_sold'         => 5,
            'show_duration'      => $minutes,
            'status'             => 'pending_approval',
        ]);
        $show->streamers()->attach($streamer->id);

        return $streamer;
    }

    public function test_the_page_renders_every_section_with_real_figures(): void
    {
        $this->streamerWithShow('Joshua Beaupre', 1000);
        $this->streamerWithShow('Ty', 3000);

        $this->get(StreamerAnalytics::getUrl())
            ->assertOk()
            ->assertSee('Streamer analytics')
            ->assertSee('Compare streamers')
            ->assertSee('Export')
            ->assertSee('Overview')
            ->assertSee('Weekly breakdown')
            ->assertSee('Total shows')
            ->assertSee('Gross sales')
            ->assertSee('Whatnot net')
            ->assertSee('Revenue / show hour')
            ->assertSee('Show hours reflect broadcast duration, not hours worked for payroll.')
            ->assertSee('Top streamers')
            ->assertSee('Sales &amp; net trend', false)
            ->assertSee('Streamer performance')
            ->assertSee('$4,000')             // gross total
            ->assertSee('$1,000')             // 4,000 / 4 show hours
            ->assertSee('$2,400')             // Ty's Whatnot net
            ->assertSee('JB')                 // initials
            ->assertSee('View analytics')
            ->assertSee('Open full comparison')
            ->assertSee('Showing 1–2 of 2 streamers')
            ->assertDontSee('Full comparison</h2>', false);
    }

    public function test_cards_search_sort_and_page(): void
    {
        foreach (range(1, 14) as $i) {
            $this->streamerWithShow(sprintf('Streamer %02d', $i), $i * 100);
        }

        $page = Livewire::test(StreamerAnalytics::class);

        $cards = $page->instance()->cardGrid;
        $this->assertSame(14, $cards['total']);
        $this->assertSame(3, $cards['pages']);
        $this->assertSame('Streamer 14', $cards['rows'][0]['name']); // net desc by default
        $this->assertSame(1, $cards['rows'][0]['rank']);
        $page->assertSee('Showing 1–6 of 14 streamers');

        $page->call('goToCardPage', 3);
        $this->assertSame(['Streamer 02', 'Streamer 01'], array_column($page->instance()->cardGrid['rows'], 'name'));
        $this->assertSame([13, 14], array_column($page->instance()->cardGrid['rows'], 'rank'));

        $page->set('cardSort', 'name');
        $this->assertSame(1, $page->instance()->cardGrid['page']);
        $this->assertSame('Streamer 01', $page->instance()->cardGrid['rows'][0]['name']);

        $this->assertNull($page->instance()->cardGrid['rows'][0]['rank']); // no rank when sorted by name

        $page->set('cardSort', 'gross')->set('cardSearch', 'streamer 07');
        $this->assertSame(['Streamer 07'], array_column($page->instance()->cardGrid['rows'], 'name'));
        $this->assertSame(8, $page->instance()->cardGrid['rows'][0]['rank']); // keeps its place when found by search
    }

    public function test_top_streamers_switch_metric_and_cards_carry_business_buddy_and_sparkline(): void
    {
        $business = \App\Models\Business::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $ty = $this->streamerWithShow('Ty', 3000, 60);
        $ty->update(['business_id' => $business->id]);
        $this->streamerWithShow('Joshua', 2000, 240);

        $page = Livewire::test(StreamerAnalytics::class);
        $this->assertSame(['Ty', 'Joshua'], array_column($page->instance()->topStreamers, 'name'));
        $page->set('topMetric', 'sph');
        $this->assertSame([3000.0, 500.0], array_column($page->instance()->topStreamers, 'top_value'));
        $page->set('topMetric', 'bogus');
        $this->assertSame('net', $page->get('topMetric'));

        $row = collect($page->instance()->analyticsRows)->firstWhere('name', 'Ty');
        $this->assertSame('Vortex Breaks', $row['business']);
        $this->assertFileExists(public_path('img/pokemon3d/'.$row['buddy'].'.gif'));
        $this->assertCount(StreamerAnalytics::SPARK_POINTS, $row['spark']);
        $this->assertEqualsWithDelta(2400, array_sum($row['spark']), 0.01);
        $page->assertSee('Vortex Breaks')->assertSee('/img/pokemon3d/'.$row['buddy'].'.gif', false);
    }

    public function test_view_analytics_focuses_one_streamer_and_the_comparison_opens_on_request(): void
    {
        $ty = $this->streamerWithShow('Ty', 3000);
        $this->streamerWithShow('Joshua', 2000);

        $page = Livewire::test(StreamerAnalytics::class)
            ->assertDontSee('Full comparison</h2>', false)
            ->call('focusStreamer', $ty->id);
        $this->assertSame(['Ty'], array_column($page->instance()->cardGrid['rows'], 'name'));
        $page->assertSee('Open profile')->assertSee('Show every streamer');

        $page->toggle('showComparison')->assertSee('Full comparison</h2>', false)->assertSee('$3,000.00');

        $page->call('setRange', 'month');
        $this->assertSame(now()->startOfMonth()->toDateString(), $page->get('dateFrom'));
    }

    public function test_weekly_tab_renders(): void
    {
        $this->streamerWithShow('Weekly One', 1500);

        Livewire::test(StreamerAnalytics::class)
            ->set('activeTab', 'weekly')
            ->assertSee('Weekly Show Performance')
            ->assertSee('Gross Revenue by Week')
            ->assertSee('$1,500.00');
    }

    public function test_comparing_one_streamer_narrows_the_cards(): void
    {
        $a = $this->streamerWithShow('Alpha', 500);
        $this->streamerWithShow('Beta', 900);

        $page = Livewire::test(StreamerAnalytics::class)->set('selectedStreamers', [$a->id]);

        $this->assertSame(['Alpha'], array_column($page->instance()->cardGrid['rows'], 'name'));
        $page->assertSee('Showing 1–1 of 1 streamer');

        $page->call('clearSelectedStreamers');
        $this->assertSame(2, $page->instance()->cardGrid['total']);
    }
}

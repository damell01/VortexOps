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
            ->assertSee('Streamer Analytics')
            ->assertSee('Compare streamers')
            ->assertSee('Export CSV')
            ->assertSee('Streamer Overview')
            ->assertSee('Weekly Breakdown')
            ->assertSee('Total Shows')
            ->assertSee('Gross Revenue')
            ->assertSee('Revenue per Show Hour')
            ->assertSee('Recorded Payouts')
            ->assertSee('Show hours measure broadcast duration, not hours worked for payroll.')
            ->assertSee('Top Streamers by Gross Revenue')
            ->assertSee('Streamers (2)')
            ->assertSee('$4,000.00')          // gross total
            ->assertSee('$1,000.00')          // 4,000 / 4 show hours
            ->assertSee('Whatnot Estimated Earnings')
            ->assertSee('$2,400.00')          // Ty's estimated earnings
            ->assertSee('JB')                 // initials
            ->assertSee('View Details')
            ->assertSee('Full Comparison');
    }

    public function test_cards_search_sort_and_page(): void
    {
        foreach (range(1, 14) as $i) {
            $this->streamerWithShow(sprintf('Streamer %02d', $i), $i * 100);
        }

        $page = Livewire::test(StreamerAnalytics::class);

        $cards = $page->instance()->cardGrid;
        $this->assertSame(14, $cards['total']);
        $this->assertSame(2, $cards['pages']);
        $this->assertSame('Streamer 14', $cards['rows'][0]['name']); // gross desc by default
        $page->assertSee('Showing 1 – 12 of 14');

        $page->call('goToCardPage', 2);
        $this->assertSame(['Streamer 02', 'Streamer 01'], array_column($page->instance()->cardGrid['rows'], 'name'));

        $page->set('cardSort', 'name');
        $this->assertSame(1, $page->instance()->cardGrid['page']);
        $this->assertSame('Streamer 01', $page->instance()->cardGrid['rows'][0]['name']);

        $page->set('cardSearch', 'streamer 07');
        $this->assertSame(['Streamer 07'], array_column($page->instance()->cardGrid['rows'], 'name'));
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
        $page->assertSee('Streamers (1)');

        $page->call('clearSelectedStreamers');
        $this->assertSame(2, $page->instance()->cardGrid['total']);
    }
}

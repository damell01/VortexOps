<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\Shows;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Shows Overview is a scheduling calendar: Month by default, a day never grows
 * past three entries, "+N more" and the date number open that day, an empty
 * day opens Add Show with the date chosen, and every view reads one data set.
 */
class ShowsCalendarTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    private Streamer $jess;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 15:00:00');
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));

        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'whatnot_username' => 'vortexcards', 'status' => 'active']);
        $this->jess = Streamer::create(['whatnot_channel_id' => $this->channel->id, 'name' => 'Jess', 'email' => 'jess@example.com', 'payout_type' => 'profit_share', 'payout_percentage' => 20, 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $date, ?string $start, ?string $end = null, string $title = 'Show'): Show
    {
        return Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title' => $title,
            'show_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'draft',
        ]);
    }

    public function test_it_opens_on_the_current_month(): void
    {
        $this->show('2026-10-07', '14:00', '16:30', 'Vortex Cards Night')->streamers()->attach($this->jess->id);

        Livewire::test(Shows::class)
            ->assertOk()
            ->assertSet('viewMode', 'month')
            ->assertSet('anchor', '2026-10-07')
            ->assertSee('October 2026')
            ->assertSee('Vortex Cards Night');
    }

    public function test_a_busy_day_shows_three_entries_and_more_instead_of_growing(): void
    {
        foreach (['14:00', '16:30', '18:00', '20:30', '22:30', '00:00'] as $i => $t) $this->show('2026-10-07', $t, null, "Slot {$i}");

        Livewire::test(Shows::class)
            // Chips carry the title attribute; the drawer's JSON has every show.
            ->assertSeeHtml('title="Slot 0"')
            ->assertSeeHtml('title="Slot 2"')
            ->assertDontSeeHtml('title="Slot 3"')
            ->assertSee('+3 more');
    }

    public function test_a_day_of_three_shows_them_all(): void
    {
        foreach (['14:00', '16:30', '18:00'] as $i => $t) $this->show('2026-10-08', $t, null, "Thursday {$i}");

        Livewire::test(Shows::class)
            ->assertSeeHtml('title="Thursday 0"')->assertSeeHtml('title="Thursday 1"')->assertSeeHtml('title="Thursday 2"')
            ->assertDontSee('more</button>', false);
    }

    public function test_more_and_the_date_number_open_the_day_view(): void
    {
        foreach (['14:00', '16:30', '18:00', '20:30'] as $i => $t) $this->show('2026-10-07', $t, null, "Slot {$i}");

        Livewire::test(Shows::class)
            ->call('openDay', '2026-10-07')
            ->assertSet('viewMode', 'day')
            ->assertSet('anchor', '2026-10-07')
            ->assertSee('Wednesday, October 7, 2026')
            ->assertSee('Slot 3');
    }

    public function test_an_empty_day_opens_add_show_with_that_date(): void
    {
        Livewire::test(Shows::class)
            ->mountAction('addShow', ['date' => '2026-10-15', 'time' => '19:30'])
            ->assertSchemaStateSet(['show_date' => '2026-10-15', 'start_time' => '19:30'])
            ->set('mountedActions.0.data.title', 'Mid-month Break')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $show = Show::firstWhere('title', 'Mid-month Break');
        $this->assertNotNull($show);
        $this->assertSame('2026-10-15', $show->show_date->toDateString());
        $this->assertSame('manual', $show->import_source);
    }

    public function test_navigation_moves_by_the_active_view(): void
    {
        Livewire::test(Shows::class)
            ->call('goNext')->assertSet('anchor', '2026-11-07')
            ->call('setView', 'week')->call('goPrevious')->assertSet('anchor', '2026-10-31')
            ->call('setView', 'day')->call('goNext')->assertSet('anchor', '2026-11-01')
            ->call('goToToday')->assertSet('anchor', '2026-10-07');
    }

    public function test_after_midnight_shows_close_out_the_evening_they_belong_to(): void
    {
        $this->show('2026-10-07', '00:00', '01:30', 'Late Night');
        $this->show('2026-10-07', '22:30', '23:59', 'Ten Thirty');
        $this->show('2026-10-07', '14:00', '16:30', 'Afternoon');

        $titles = Livewire::test(Shows::class)->instance()->dayShows()->pluck('title')->all();

        $this->assertSame(['Afternoon', 'Ten Thirty', 'Late Night'], $titles);
    }

    public function test_live_upcoming_and_completed_states(): void
    {
        $this->show('2026-10-07', '14:00', '16:30', 'On Air');
        $this->show('2026-10-07', '18:00', '19:30', 'Tonight');
        $this->show('2026-10-06', '18:00', '19:30', 'Yesterday');

        $states = Livewire::test(Shows::class)->instance()->presented->pluck('state', 'title')->all();

        $this->assertSame(['Yesterday' => 'done', 'On Air' => 'live', 'Tonight' => 'upcoming'], $states);
    }

    public function test_week_view_places_overlapping_shows_in_lanes(): void
    {
        $this->show('2026-10-07', '18:00', '20:00', 'A');
        $this->show('2026-10-07', '19:00', '21:00', 'B');

        $page = Livewire::test(Shows::class)->call('setView', 'week')->assertOk()->instance();
        $wed = $page->weekGrid()['columns'][3]['timed']->keyBy('title');

        $this->assertSame(0, $wed['A']['lane']);
        $this->assertSame(1, $wed['B']['lane']);
        $this->assertSame(2, $wed['B']['lanes']);
    }

    public function test_a_show_that_overlaps_nothing_keeps_the_full_week_column(): void
    {
        $this->show('2026-10-07', '14:00', '15:00', 'Solo');
        $this->show('2026-10-07', '18:00', '20:00', 'A');
        $this->show('2026-10-07', '19:00', '21:00', 'B');

        $wed = Livewire::test(Shows::class)->instance()->weekGrid()['columns'][3]['timed']->keyBy('title');

        $this->assertSame(1, $wed['Solo']['lanes']);
        $this->assertSame(2, $wed['A']['lanes']);
    }

    public function test_list_view_groups_the_month_by_day(): void
    {
        $this->show('2026-10-07', '14:00', null, 'Wednesday Show');
        $this->show('2026-10-08', '17:00', null, 'Thursday Show');
        $this->show('2026-11-02', '17:00', null, 'November Show');

        Livewire::test(Shows::class)->call('setView', 'list')
            ->assertSee('Wed, Oct 7')->assertSee('Thu, Oct 8')
            ->assertSee('Wednesday Show')->assertDontSee('November Show');
    }

    public function test_the_drawer_payload_carries_the_show_summary(): void
    {
        $show = $this->show('2026-10-07', '14:00', '16:30', 'Vortex Cards');
        $show->update(['gross_revenue' => 8920, 'whatnot_net' => 6340, 'units_sold' => 186, 'total_views' => 342]);
        $show->streamers()->attach($this->jess->id);

        $p = Livewire::test(Shows::class)->instance()->calendarPayload()['shows'][$show->id];

        $this->assertSame('Vortex Cards', $p['channel']);
        $this->assertSame('Jess', $p['streamers']);
        $this->assertSame('$8,920', $p['gross']);
        $this->assertSame('186', $p['units']);
        $this->assertSame('2h 30m', $p['duration']);
        $this->assertSame('live', $p['state']);
        $this->assertNotNull($p['editUrl']);
    }

    public function test_unassigned_queue_counts_this_month_and_assigning_clears_it(): void
    {
        $show = $this->show('2026-10-20', '18:00', null, 'Needs Host');

        $page = Livewire::test(Shows::class);
        $this->assertSame(1, $page->instance()->unassignedShows->count());

        $page->call('assignStreamer', $show->id, $this->jess->id);
        $this->assertSame(0, $page->instance()->unassignedShows->count());
    }

    public function test_filters_narrow_the_calendar(): void
    {
        $this->show('2026-10-07', '14:00', null, 'Alpha Break');
        $this->show('2026-10-08', '14:00', null, 'Beta Break');

        Livewire::test(Shows::class)
            ->set('searchQuery', 'Alpha')
            ->assertSee('Alpha Break')->assertDontSee('Beta Break')
            ->call('clearFilters')
            ->assertSee('Beta Break');
    }

    public function test_old_period_links_land_on_that_month(): void
    {
        Livewire::withQueryParams(['range' => 'custom', 'from' => '2026-08-01', 'to' => '2026-08-31'])
            ->test(Shows::class)
            ->assertSet('anchor', '2026-08-01')
            ->assertSee('August 2026');
    }
}

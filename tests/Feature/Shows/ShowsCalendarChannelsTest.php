<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\Shows;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Four channels running a dozen shows a day made the month calendar
 * unreadable: three shows per day, titles cut off, the channel only a
 * coloured edge. The pills now switch channels on and off, every show is in
 * its day, and a By channel view lays the week out a row per channel.
 */
class ShowsCalendarChannelsTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $breaks;
    private WhatnotChannel $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($owner->fresh());

        $this->breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $this->shop = WhatnotChannel::create(['name' => 'Vortex Shop', 'status' => 'active']);
        foreach (range(1, 6) as $i) {
            Show::create(['whatnot_channel_id' => $this->breaks->id, 'title' => "Breaks show $i", 'show_date' => '2026-10-14', 'status' => 'draft']);
        }
        Show::create(['whatnot_channel_id' => $this->shop->id, 'title' => 'Shop singles night', 'show_date' => '2026-10-14', 'status' => 'draft']);
        Show::create(['whatnot_channel_id' => null, 'title' => 'Unlabelled stream', 'show_date' => '2026-10-15', 'status' => 'draft']);
    }

    public function test_every_show_in_a_day_is_on_the_month_calendar(): void
    {
        Livewire::test(Shows::class, ['anchor' => '2026-10-14'])
            ->set('anchor', '2026-10-14')
            ->assertSee('Breaks show 6')
            ->assertSee('Shop singles night')
            ->assertDontSee('more</button>', false);
    }

    public function test_a_channel_can_be_hidden_shown_alone_and_brought_back(): void
    {
        $page = Livewire::test(Shows::class)->set('anchor', '2026-10-14')
            ->call('toggleChannel', (string) $this->breaks->id)
            ->assertDontSee('Breaks show 1')
            ->assertSee('Shop singles night')
            ->assertSee('Unlabelled stream')
            ->assertSee('Show all channels');

        // Its pill stays, so it can be turned back on.
        $legend = $page->instance()->channelLegend()->keyBy('key');
        $this->assertTrue($legend[(string) $this->breaks->id]['hidden']);
        $this->assertSame(6, $legend[(string) $this->breaks->id]['count']);

        $page->call('onlyChannel', (string) $this->shop->id)
            ->assertSee('Shop singles night')
            ->assertDontSee('Unlabelled stream')
            ->assertDontSee('Breaks show 1')
            ->call('showAllChannels')
            ->assertSee('Breaks show 1')
            ->assertSee('Unlabelled stream');
    }

    public function test_the_by_channel_view_puts_each_show_in_its_channel_row_and_day(): void
    {
        $page = Livewire::test(Shows::class)->set('anchor', '2026-10-14')->call('setView', 'channels')
            ->assertSee('By channel')
            ->assertSee('Breaks show 3');

        $board = $page->instance()->channelBoard();
        $rows = $board['rows']->keyBy('name');
        $wednesday = $board['days']->search(fn ($d) => $d->toDateString() === '2026-10-14');

        $this->assertSame(6, $rows['Vortex Breaks']['cells'][$wednesday]->count());
        $this->assertSame('Shop singles night', $rows['Vortex Shop']['cells'][$wednesday]->first()['title']);
        $this->assertSame(1, $rows['No channel']['weekCount']);
    }
}

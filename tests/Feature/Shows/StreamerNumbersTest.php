<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\StreamerShows;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** My Shows: a streamer's own numbers and every show they were on — never profit or loss. */
class StreamerNumbersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Streamer $streamer;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 15:00:00');
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $this->user = User::factory()->create(['email' => 'streamer@test.com']);
        $this->user->assignRole('streamer');
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        Setting::set('show_reports_required_from', '2026-10-01');
        AdminModules::flushMemo();
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
        $this->streamer = Streamer::create(['name' => 'Jordan', 'user_id' => $this->user->id, 'status' => 'active']);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $date, array $extra = [], bool $mine = true): Show
    {
        $show = Show::create($extra + [
            'whatnot_channel_id' => $this->channel->id, 'title' => 'Show on '.$date, 'show_date' => $date,
            'status' => 'reconciled', 'gross_revenue' => 1000, 'units_sold' => 40, 'created_by' => $this->user->id,
        ]);
        if ($mine) {
            $show->streamers()->attach($this->streamer->id, ['is_primary' => true]);
        }

        return $show;
    }

    public function test_the_months_numbers_add_up_hours_sales_units_and_pay(): void
    {
        $a = $this->show('2026-10-05');
        $b = $this->show('2026-10-12', ['gross_revenue' => 500, 'units_sold' => 10]);
        $this->show('2026-09-28'); // last month
        $this->show('2026-10-06', [], false); // someone else's
        StreamerLogEntry::create(['show_id' => $a->id, 'streamer_id' => $this->streamer->id, 'hours_streamed' => 3]);
        StreamerLogEntry::create(['show_id' => $b->id, 'streamer_id' => $this->streamer->id, 'hours_streamed' => 2.5]);
        TimeEntry::create(['user_id' => $this->user->id, 'reason' => 'cleanup', 'status' => 'ok', 'clocked_in_at' => '2026-10-07 14:00:00', 'clocked_out_at' => '2026-10-07 14:30:00']);
        Payout::create(['show_id' => $a->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 120, 'gross_show_revenue' => 1000, 'status' => 'paid']);
        Payout::create(['show_id' => $b->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 45.5, 'gross_show_revenue' => 500, 'status' => 'draft']);

        $n = Livewire::test(StreamerShows::class)->instance()->numbers();

        $this->assertSame(2, $n['shows']);
        $this->assertSame(6.0, $n['hours']);
        $this->assertSame(1500.0, $n['gross']);
        $this->assertSame(50, $n['units']);
        $this->assertSame(120.0, $n['paid']);
        $this->assertSame(45.5, $n['awaiting']);
    }

    public function test_the_year_includes_every_month(): void
    {
        $this->show('2026-03-05');
        $this->show('2026-10-05');

        $page = Livewire::test(StreamerShows::class)->call('setPeriod', 'year');

        $this->assertSame(2, $page->instance()->numbers()['shows']);
    }

    public function test_every_show_is_listed_past_and_upcoming_and_can_be_searched(): void
    {
        $this->show('2026-06-10', ['title' => 'Summer Pokemon Night']); // before reports went live
        $this->show('2026-10-25', ['title' => 'Upcoming Breaks']);
        $this->show('2026-10-12', ['title' => 'Mystery Tuesday']);
        $this->show('2026-10-13', ['title' => 'Not mine'], false);

        $page = Livewire::test(StreamerShows::class);
        $this->assertSame(3, $page->instance()->allShows()->total());
        $page->assertSee('Summer Pokemon Night')->assertSee('Upcoming Breaks')->assertDontSee('Not mine');

        $page->set('search', 'pokemon');
        $this->assertSame(['Summer Pokemon Night'], $page->instance()->allShows()->pluck('title')->all());

        $page->set('search', '')->set('when', 'upcoming');
        $this->assertSame(['Upcoming Breaks'], $page->instance()->allShows()->pluck('title')->all());
    }

    public function test_streamers_never_see_profit_or_loss(): void
    {
        $show = $this->show('2026-10-12');
        Payout::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 80, 'gross_show_revenue' => 1000, 'status' => 'draft']);

        Livewire::test(StreamerShows::class)
            ->assertSee('Your numbers')->assertSee('Awaiting payment')
            ->assertDontSee('Profit')->assertDontSee('Loss')->assertDontSee('COGS');
    }
}

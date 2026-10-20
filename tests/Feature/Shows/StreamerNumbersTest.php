<?php

namespace Tests\Feature\Shows;

use App\Livewire\StreamerHome;
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

/** The streamer's dashboard: their own numbers and every show they were on — never profit or loss. */
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

    public function test_the_months_numbers_add_up_sales_and_pay(): void
    {
        $a = $this->show('2026-10-05');
        $b = $this->show('2026-10-12', ['gross_revenue' => 500, 'units_sold' => 10]);
        $c = $this->show('2026-10-14');
        $this->show('2026-09-28'); // last month
        $this->show('2026-10-06', [], false); // someone else's
        Payout::create(['show_id' => $a->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 120, 'gross_show_revenue' => 1000, 'status' => 'paid']);
        Payout::create(['show_id' => $b->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 45.5, 'gross_show_revenue' => 500, 'status' => 'draft']);
        Payout::create(['show_id' => $c->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 30, 'gross_show_revenue' => 1000, 'status' => 'approved']);

        $n = Livewire::test(StreamerHome::class)->set('period', 'month')->instance()->numbers();

        $this->assertSame(3, $n['shows']);
        $this->assertSame(2500.0, $n['gross']);
        $this->assertSame(120.0, $n['paid']);
        // Approved but not paid; a draft is not approved yet.
        $this->assertSame(30.0, $n['unpaid']);
    }

    public function test_the_year_includes_every_month(): void
    {
        $this->show('2026-03-05');
        $this->show('2026-10-05');

        $page = Livewire::test(StreamerHome::class)->set('period', 'year');

        $this->assertSame(2, $page->instance()->numbers()['shows']);
    }

    public function test_every_show_is_listed_past_and_upcoming_and_can_be_searched(): void
    {
        $this->show('2026-06-10', ['title' => 'Summer Pokemon Night']); // before reports went live
        $this->show('2026-10-25', ['title' => 'Upcoming Breaks']);
        $this->show('2026-10-12', ['title' => 'Mystery Tuesday']);
        $this->show('2026-10-13', ['title' => 'Not mine'], false);

        $page = Livewire::test(StreamerHome::class)->call('setTab', 'all');
        $this->assertSame(['Mystery Tuesday', 'Summer Pokemon Night'], collect($page->instance()->reports()->items())->pluck('title')->all());
        $page->assertSee('Previous show');

        $page->set('search', 'pokemon');
        $this->assertSame(['Summer Pokemon Night'], collect($page->instance()->reports()->items())->pluck('title')->all());

        $page->set('search', '')->call('setTab', 'upcoming');
        $this->assertSame(['Upcoming Breaks'], collect($page->instance()->reports()->items())->pluck('title')->all());
    }

    public function test_streamers_never_see_profit_or_loss(): void
    {
        $show = $this->show('2026-10-12');
        Payout::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 80, 'gross_show_revenue' => 1000, 'status' => 'draft']);

        Livewire::test(StreamerHome::class)
            ->assertSee('Welcome back')->assertSee('Awaiting payment')
            ->assertDontSee('Profit')->assertDontSee('Loss')->assertDontSee('COGS');
    }
}

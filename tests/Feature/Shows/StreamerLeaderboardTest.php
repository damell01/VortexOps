<?php

namespace Tests\Feature\Shows;

use App\Livewire\StreamerLeaderboard;
use App\Models\Business;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\StreamerShowLeaderboard;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamerLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;
    private Streamer $mine;
    private Streamer $rival;
    private Streamer $outside;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->enableAdminModules();
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00', 'America/Chicago'));
        $business = Business::create(['name' => 'Vortex Team', 'status' => 'active']);
        $other = Business::create(['name' => 'Other Business', 'status' => 'active']);
        $this->viewer = User::factory()->create(['buddy' => 'pikachu']);
        $this->viewer->assignRole(Role::findOrCreate('streamer', 'web'));
        $this->mine = Streamer::create(['name' => 'Alex', 'user_id' => $this->viewer->id, 'business_id' => $business->id, 'status' => 'active']);
        $this->rival = Streamer::create(['name' => 'Jordan', 'business_id' => $business->id, 'status' => 'active']);
        $this->outside = Streamer::create(['name' => 'Outside Team', 'business_id' => $other->id, 'status' => 'active']);
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $this->actingAs($this->viewer);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(string $title, float $net, ?Streamer $host = null, array $extra = []): Show
    {
        $show = Show::withoutEvents(fn () => Show::create($extra + ['title' => $title, 'show_date' => '2026-10-19', 'start_time' => '18:00:00', 'show_duration' => 120, 'gross_revenue' => 9999, 'whatnot_net' => $net, 'status' => 'reconciled', 'is_operational' => true, 'whatnot_channel_id' => $this->channel->id]));
        $show->streamers()->attach(($host ?? $this->mine)->id, ['is_primary' => true]);
        return $show;
    }

    private function rows(): \Illuminate\Support\Collection
    {
        return app(StreamerShowLeaderboard::class)->rows(auth()->user(), Carbon::parse('2026-10-19'), Carbon::parse('2026-10-20'));
    }

    public function test_rankings_use_net_not_gross_with_settled_priority_known_zeroes_losses_and_ties(): void
    {
        $this->show('Large sales', 100, extra: ['gross_revenue' => 99999]);
        $this->show('Winner', 500, $this->rival, ['completed_earnings' => 200]);
        $this->show('Joint winner', 200);
        $this->show('Confirmed zero', 0, extra: ['completed_earnings' => 0]);
        $this->show('Loss', -10);
        $this->show('Unknown', 0);
        $rows = $this->rows();
        $this->assertSame(['Winner', 'Joint winner', 'Large sales', 'Confirmed zero', 'Loss'], $rows->pluck('title')->all());
        $this->assertSame([1, 1, 3, 4, 5], $rows->pluck('rank')->all());
        $this->assertSame(200.0, $rows->first()['value']);
        $this->assertFalse($rows->first()['estimated']);
    }

    public function test_standings_include_streamers_across_businesses_and_only_public_competition_fields(): void
    {
        $this->show('Team champion', 500, $this->rival);
        $this->show('Other team champion', 900, $this->outside);
        $rows = $this->rows();
        $this->assertSame(['Other team champion', 'Team champion'], $rows->pluck('title')->all());
        Livewire::test(StreamerLeaderboard::class)->assertSee('Team champion')->assertSee('Other team champion')->assertSee('Outside Team')->assertDontSee('Leaderboard business');
        foreach (['email', 'phone', 'payout', 'product_cost', 'business_net', 'salary'] as $private) $this->assertArrayNotHasKey($private, $rows->first());
    }

    public function test_cohosts_share_one_show_entry_and_personal_best_is_highlighted(): void
    {
        $show = $this->show('Team effort', 500, $this->rival);
        $show->streamers()->attach($this->mine->id);
        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()['mine']);
        $this->assertTrue($rows->first()['cohosted']);
        $this->assertEqualsCanonicalizing(['Alex', 'Jordan'], $rows->first()['hosts']);
        Livewire::test(StreamerLeaderboard::class)->assertSee('Your best show: #1')->assertSee('Cohosted');
    }

    public function test_today_week_and_month_have_correct_boundaries_and_only_finished_shows(): void
    {
        $this->show('Yesterday', 200);
        $this->show('Today complete', 300, extra: ['show_date' => '2026-10-20', 'start_time' => '08:00:00']);
        $this->show('Earlier show', 400, extra: ['show_date' => '2026-10-12']);
        $this->show('Future', 999, extra: ['show_date' => '2026-10-21']);
        $this->show('Live now', 999, extra: ['show_date' => '2026-10-20', 'start_time' => '11:30:00']);
        $this->show('Cancelled', 999, extra: ['status' => 'cancelled']);
        $page = Livewire::test(StreamerLeaderboard::class)->assertSee('Yesterday')->assertSee('Today complete')->assertDontSee('Earlier show')->assertDontSee('Live now')->assertDontSee('Cancelled')->assertDontSee('Future');
        $page->set('period', 'today')->assertSee('Today complete')->assertDontSee('Yesterday');
        $page->set('period', 'month')->assertSee('Earlier show')->assertSee('Yesterday');
        $page->set('period', 'bad')->assertSet('period', 'week');
    }

    public function test_seller_hub_past_with_unknown_time_can_rank_today_but_scheduled_unknown_time_cannot(): void
    {
        $this->show('Past imported', 300, extra: ['show_date' => '2026-10-20', 'start_time' => null, 'show_duration' => 0, 'raw_import_payload' => ['_seller_hub_state' => 'past']]);
        $this->show('Scheduled unknown time', 500, extra: ['show_date' => '2026-10-20', 'start_time' => null, 'show_duration' => 0]);
        $this->assertSame(['Past imported'], $this->rows()->pluck('title')->all());
    }

    public function test_owner_and_streamers_share_rankings_and_global_channel_filter_is_respected(): void
    {
        $this->show('Team show', 100);
        $this->show('Other show', 200, $this->outside);
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($owner);
        $page = Livewire::test(StreamerLeaderboard::class)->assertSee('Team show')->assertSee('Other show');
        $emptyChannel = WhatnotChannel::create(['name' => 'Empty channel', 'status' => 'active']);
        ChannelContext::setActive($emptyChannel->id);
        Livewire::test(StreamerLeaderboard::class)->assertSee('The league is warming up')->assertDontSee('Team show');
    }

    public function test_missing_profile_and_unrelated_roles_cannot_open_leaderboard(): void
    {
        $orphan = User::factory()->create();
        $orphan->assignRole('streamer');
        $this->actingAs($orphan);
        $this->assertFalse(StreamerLeaderboard::canView());
        $this->assertCount(0, $this->rows());
        Livewire::test(StreamerLeaderboard::class)->assertForbidden();
        $this->actingAs(User::factory()->create());
        Livewire::test(StreamerLeaderboard::class)->assertForbidden();
    }

    public function test_dashboard_embeds_leaderboard_and_pagination_keeps_my_result_outside_top_five(): void
    {
        for ($i = 0; $i < 8; $i++) $this->show('Rival show '.$i, 1000 - $i, $this->rival);
        $this->show('My best', 100);
        Livewire::test(StreamerLeaderboard::class)->assertSee('Your best show: #9')->assertSee('5 of 9 shows')->call('showMore')->assertSee('9 of 9 shows');
        Livewire::test(\App\Filament\Pages\DashboardImproved::class)->assertSee('Top Net Shows')->assertSee('Vortex League');
    }
}

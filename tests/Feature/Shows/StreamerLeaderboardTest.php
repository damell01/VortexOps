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

    public function test_week_and_month_have_correct_boundaries_and_only_finished_shows(): void
    {
        $this->show('Yesterday', 200);
        $this->show('Today complete', 300, extra: ['show_date' => '2026-10-20', 'start_time' => '08:00:00']);
        $this->show('Earlier show', 400, extra: ['show_date' => '2026-10-12']);
        $this->show('Future', 999, extra: ['show_date' => '2026-10-21']);
        $this->show('Live now', 999, extra: ['show_date' => '2026-10-20', 'start_time' => '11:30:00']);
        $this->show('Cancelled', 999, extra: ['status' => 'cancelled']);
        $page = Livewire::test(StreamerLeaderboard::class)->assertSee('Yesterday')->assertSee('Today complete')->assertDontSee('Earlier show')->assertDontSee('Live now')->assertDontSee('Cancelled')->assertDontSee('Future');
        $page->assertDontSeeHtml('<option value="today">')->assertDontSeeHtml('<option value="year">');
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
        Livewire::test(StreamerLeaderboard::class)->assertSee('No shows on the board yet')->assertDontSee('Team show');
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

    public function test_leaderboard_uses_selected_buddies_and_matching_attack_effects_with_safe_varied_fallbacks(): void
    {
        $rivalUser = User::factory()->create(['buddy' => 'blastoise']);
        $this->rival->update(['user_id' => $rivalUser->id]);
        $this->show('Electric show', 300);
        $this->show('Water show', 200, $this->rival);
        $this->show('Unlinked host', 100, $this->outside);
        $rows = $this->rows()->keyBy('title');
        $this->assertSame('pikachu', $rows['Electric show']['buddy']);
        $this->assertSame('electric', $rows['Electric show']['effect']);
        $this->assertSame('blastoise', $rows['Water show']['buddy']);
        $this->assertSame('water', $rows['Water show']['effect']);
        $fallback = $rows['Unlinked host']['buddy'];
        $this->assertFileExists(public_path('img/pokemon3d/'.$fallback.'.gif'));
        $this->assertSame($fallback, $this->rows()->keyBy('title')['Unlinked host']['buddy']);
        Livewire::test(StreamerLeaderboard::class)->assertSeeHtml('vxl-effect-electric')->assertSeeHtml('vxl-effect-water')->assertSeeHtml('/img/pokemon3d/blastoise.gif');
        $rivalUser->update(['buddy' => '../../invalid']);
        $fallback = $this->rows()->keyBy('title')['Water show']['buddy'];
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $fallback);
        $this->assertFileExists(public_path('img/pokemon3d/'.$fallback.'.gif'));
    }

    public function test_dashboard_embeds_compact_leaderboard_and_pagination_keeps_my_result_visible(): void
    {
        for ($i = 0; $i < 8; $i++) $this->show('Rival show '.$i, 1000 - $i, $this->rival);
        $this->show('My best', 100);
        Livewire::test(StreamerLeaderboard::class)
            ->assertSee('Your best show: #9')
            ->assertSee('Showing 1–6 of 9 shows')
            ->assertSee('Rival show 5')
            ->assertDontSee('Rival show 6')
            ->call('goToPage', 2)
            ->assertSee('Showing 7–9 of 9 shows')
            ->assertSee('Rival show 6')
            ->assertSee('My best')
            ->assertDontSee('Rival show 5')
            ->assertDontSee('Rival show 0')
            ->set('period', 'month')
            ->assertSet('page', 1)
            ->assertSee('Showing 1–6 of 9 shows')
            ->call('goToPage', 999)
            ->assertSet('page', 2)
            ->call('goToPage', -1)
            ->assertSet('page', 1);
        Livewire::test(\App\Filament\Pages\DashboardImproved::class)->assertSee('Top Net Shows')->assertSee('This week')->assertSee('This month');
    }

    public function test_leaderboard_has_its_own_page_linked_under_shows_for_everyone_with_the_tab(): void
    {
        $this->show('Page winner', 300);
        $url = \App\Filament\Pages\ShowLeaderboard::getUrl(panel: 'admin');
        $this->get($url)->assertOk()->assertSee('Top Net Shows')->assertSee('Page winner');
        $this->get(\App\Filament\Pages\Shows::getUrl(panel: 'admin'))->assertOk()->assertSee('Show Leaderboard')->assertSee($url, false);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Page winner');
        $this->get(\App\Filament\Pages\Shows::getUrl(panel: 'admin'))->assertSee($url, false);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_colosseum_is_offered_on_the_leaderboard_page_only_with_the_top_three(): void
    {
        $this->viewer->update(['buddy' => 'charizard']);
        $this->show('Champion', 900);
        $this->show('Second', 500, $this->rival);
        $this->show('Third', 300, $this->outside);
        $this->show('Fourth', 100, $this->rival);

        $page = $this->get(\App\Filament\Pages\ShowLeaderboard::getUrl(panel: 'admin'))->assertOk()
            ->assertSee('data-vxa', false)->assertSee('Replay the battle')->assertSee('Replay animation')->assertSee('vxl-podium-fallback', false);
        preg_match('#<script type="application/json" data-vxa-lineup>(.*?)</script>#s', $page->getContent(), $m);
        $lineup = json_decode($m[1] ?? 'null', true);
        $this->assertSame('week', $lineup['period']);
        $this->assertSame([1, 2, 3], array_column($lineup['rows'], 'rank'));
        $this->assertSame(['Alex', 'Jordan', 'Outside Team'], array_column($lineup['rows'], 'name'));
        $this->assertSame('$900.00', $lineup['rows'][0]['value']);
        $this->assertSame('charizard', $lineup['rows'][0]['buddy']);
        $this->assertSame('Champion', $lineup['rows'][0]['title']);
        // A streamer opens only their own show from the podium.
        $this->assertStringEndsWith('/shows/'.$lineup['rows'][0]['id'], (string) $lineup['rows'][0]['url']);
        $this->assertTrue($lineup['rows'][0]['mine']);
        $this->assertNull($lineup['rows'][1]['url']);

        // Embedded plainly (no flag) there is no arena at all.
        Livewire::test(StreamerLeaderboard::class)->assertDontSee('data-vxa', false);

        // Dashboards get the light 3D podium: no Enter arena button, the same lineup.
        Livewire::test(StreamerLeaderboard::class, ['arenaCompact' => true])
            ->assertSee('data-mode="compact"', false)
            ->assertSee('data-vxa-lineup', false);
    }

    public function test_every_colosseum_fighter_has_its_model_on_disk_and_a_credit(): void
    {
        $roster = file_get_contents(resource_path('js/colosseum/roster.js'));
        preg_match_all("/file: '([a-z0-9-]+\\.glb)'/", $roster, $m);
        $this->assertNotEmpty($m[1]);
        $credits = file_get_contents(public_path('models/colosseum/CREDITS.md'));
        foreach ($m[1] as $file) {
            $this->assertFileExists(public_path('models/colosseum/'.$file));
            $this->assertLessThan(1024 * 1024, filesize(public_path('models/colosseum/'.$file)), "$file should stay under 1 MB");
            $this->assertStringContainsString('| '.$file.' |', $credits, "$file needs a row in CREDITS.md");
        }
        foreach (['pikachu', 'charizard', 'mewtwo'] as $must) {
            $this->assertContains($must.'.glb', $m[1]);
        }
    }

    public function test_pages_carry_the_colosseum_warm_up_with_versioned_model_urls(): void
    {
        $warm = \App\Support\ColosseumAssets::warm();
        $this->assertNotEmpty($warm['models']);
        foreach ($warm['models'] as $url) {
            $this->assertMatchesRegularExpression('#/models/colosseum/[a-z0-9-]+\.glb\?v='.$warm['v'].'$#', $url);
        }

        // Any page after login (here the calendar) tells the browser what to warm.
        $this->get(\App\Filament\Pages\Shows::getUrl(panel: 'admin'))->assertOk()->assertSee('name="vortex-arena-warm"', false);
        // The arena asks for the same versioned URLs, so they come from cache.
        $this->get(\App\Filament\Pages\ShowLeaderboard::getUrl(panel: 'admin'))->assertSee('data-models-v="'.$warm['v'].'"', false);
    }
}

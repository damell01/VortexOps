<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\DashboardImproved;
use App\Livewire\StreamerHome;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The streamer's dashboard, sorting their shows by who is blocked: To complete
 * is anything waiting on them, however old, ahead of everything else.
 */
class StreamerHomeTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Streamer $streamer;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $this->user = User::factory()->create(['email' => 'streamer@test.com']);
        $this->user->assignRole('streamer');

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        $this->channel  = WhatnotChannel::create(['name' => 'Chan', 'status' => 'active']);
        $this->streamer = Streamer::create([
            'name'     => 'Jordan',
            'user_id'  => $this->user->id,
            'pay_type' => 'profit_share',
        ]);

        $this->actingAs($this->user);
    }

    private function show(array $attributes = [], bool $mine = true): Show
    {
        $show = Show::create(array_merge([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Show ' . uniqid(),
            'show_date'          => today()->subDay()->toDateString(),
            'status'             => 'reconciled',
            'created_by'         => $this->user->id,
        ], $attributes));

        if ($mine) {
            $show->streamers()->attach($this->streamer->id);
        }

        return $show;
    }

    private function entry(Show $show, array $attributes = []): StreamerLogEntry
    {
        return StreamerLogEntry::create(array_merge([
            'show_id'     => $show->id,
            'streamer_id' => $this->streamer->id,
            'status'      => 'pending',
        ], $attributes));
    }

    /** The show ids on a tab's cards, in order. */
    private function tab(string $tab): array
    {
        $html = Livewire::test(StreamerHome::class)->call('setTab', $tab)->set('perPage', 24)->html();
        preg_match_all('/wire:key="vsh-show-(\d+)"/', $html, $m);

        return array_map('intval', $m[1]);
    }

    public function test_a_past_show_with_no_report_is_waiting_on_the_streamer(): void
    {
        $show = $this->show();

        $this->assertSame([$show->id], $this->tab('to_complete'));
        Livewire::test(StreamerHome::class)->assertSee('Log sold items');
    }

    public function test_a_saved_draft_still_counts_as_waiting_on_the_streamer(): void
    {
        $show = $this->show();
        $this->entry($show, ['draft_saved_at' => now()]); // no submitted_at

        $this->assertSame([$show->id], $this->tab('to_complete'));
        Livewire::test(StreamerHome::class)->assertSee('Continue report')->assertSee('Draft');
    }

    public function test_changes_requested_comes_back_to_the_streamer(): void
    {
        $show = $this->show();
        $this->entry($show, ['status' => 'changes_requested', 'submitted_at' => now()]);

        $this->assertSame([$show->id], $this->tab('changes'));
        Livewire::test(StreamerHome::class)->call('setTab', 'changes')->assertSee('Fix report');
    }

    public function test_a_submitted_report_is_with_the_office(): void
    {
        $show = $this->show();
        $this->entry($show, ['status' => 'streamer_reviewed', 'submitted_at' => now()]);

        $this->assertSame([], $this->tab('to_complete'));
        $this->assertSame([$show->id], $this->tab('submitted'));
    }

    public function test_an_approved_report_is_done(): void
    {
        $show = $this->show();
        $this->entry($show, ['status' => 'admin_approved', 'submitted_at' => now()]);

        $this->assertSame([], $this->tab('to_complete'));
        Livewire::test(StreamerHome::class)->call('setTab', 'submitted')->assertSee('Approved');
    }

    public function test_a_future_show_is_upcoming_and_asks_for_nothing(): void
    {
        // Nothing to report on a show that has not aired, and offering the
        // report would invite filing one against an empty night.
        $show = $this->show(['show_date' => today()->addWeek()->toDateString(), 'status' => 'draft']);

        $this->assertSame([], $this->tab('to_complete'));
        $this->assertSame([$show->id], $this->tab('upcoming'));
        Livewire::test(StreamerHome::class)->call('setTab', 'upcoming')
            ->assertSee('Your report opens once the show airs.')->assertDontSee('Log sold items');
    }

    public function test_upcoming_shows_read_forwards(): void
    {
        $later = $this->show(['title' => 'Later', 'show_date' => today()->addDays(20)->toDateString(), 'status' => 'draft']);
        $soon = $this->show(['title' => 'Soon', 'show_date' => today()->addDays(2)->toDateString(), 'status' => 'draft']);

        $this->assertSame([$soon->id, $later->id], $this->tab('upcoming'));
    }

    public function test_another_streamers_show_never_appears(): void
    {
        $this->show([], mine: false);

        $this->assertSame([], $this->tab('all'));
        $this->assertSame([], $this->tab('upcoming'));
    }

    public function test_cancelled_shows_are_left_out(): void
    {
        $this->show(['status' => 'cancelled']);

        $this->assertSame([], $this->tab('all'));
    }

    public function test_the_action_links_into_the_report_for_that_show(): void
    {
        $show = $this->show();

        Livewire::test(StreamerHome::class)->assertSeeHtml('end-of-stream')->assertSeeHtml('showId=' . $show->id);
    }

    public function test_the_nav_badge_counts_only_what_needs_the_streamer(): void
    {
        $this->show();
        $done = $this->show();
        $this->entry($done, ['status' => 'admin_approved', 'submitted_at' => now()]);

        $this->assertSame('1', DashboardImproved::getNavigationBadge());
    }

    public function test_shows_before_reporting_went_live_are_not_a_task(): void
    {
        $old = $this->show(['show_date' => today()->subDays(10)->toDateString()]);
        $recent = $this->show(['show_date' => today()->subDay()->toDateString()]);

        Setting::set(\App\Support\ShowReportingGoLive::SETTING, today()->subDays(3)->toDateString());

        $this->assertSame([$recent->id], $this->tab('to_complete'));
        $this->assertSame('1', DashboardImproved::getNavigationBadge());
        // Still listed, as a previous show.
        $this->assertContains($old->id, $this->tab('all'));
    }

    public function test_an_admin_gets_no_streamer_badge(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->assertNull(DashboardImproved::getNavigationBadge());
    }
}

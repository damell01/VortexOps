<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\StreamerShows;
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
 * The page a streamer opens to find out what needs them.
 *
 * Sorted by who is blocked rather than by date: a show from three weeks ago
 * with no report is more urgent than last night's.
 */
class StreamerShowsPageTest extends TestCase
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

    private function shows(): array
    {
        return Livewire::test(StreamerShows::class)->instance()->groups();
    }

    public function test_a_past_show_with_no_report_is_waiting_on_the_streamer(): void
    {
        // The commonest reason a payout stalls, so it is the loudest thing
        // on the page.
        $show = $this->show();

        $needsYou = $this->shows()['needs_you'];

        $this->assertCount(1, $needsYou);
        $this->assertSame($show->id, $needsYou[0]['id']);
        $this->assertSame('Add items', $needsYou[0]['action']);
    }

    public function test_a_saved_draft_still_counts_as_waiting_on_the_streamer(): void
    {
        $show = $this->show();
        $this->entry($show); // no submitted_at

        $needsYou = $this->shows()['needs_you'];

        $this->assertCount(1, $needsYou);
        $this->assertSame('Finish report', $needsYou[0]['action']);
    }

    public function test_changes_requested_comes_back_to_the_streamer(): void
    {
        $show = $this->show();
        $this->entry($show, ['status' => 'changes_requested', 'submitted_at' => now()]);

        $needsYou = $this->shows()['needs_you'];

        $this->assertCount(1, $needsYou);
        $this->assertSame('Review changes', $needsYou[0]['action']);
        $this->assertSame('danger', $needsYou[0]['tone']);
    }

    public function test_a_submitted_report_is_with_the_office(): void
    {
        $show = $this->show();
        $this->entry($show, ['status' => 'streamer_reviewed', 'submitted_at' => now()]);

        $groups = $this->shows();

        $this->assertEmpty($groups['needs_you']);
        $this->assertCount(1, $groups['waiting']);
    }

    public function test_an_approved_report_is_done(): void
    {
        $show = $this->show();
        $this->entry($show, ['status' => 'admin_approved', 'submitted_at' => now()]);

        $groups = $this->shows();

        $this->assertEmpty($groups['needs_you']);
        $this->assertCount(1, $groups['done']);
    }

    public function test_a_future_show_is_upcoming_and_asks_for_nothing(): void
    {
        // Nothing to report on a show that has not aired, and offering the
        // report would invite filing one against an empty night.
        $this->show(['show_date' => today()->addWeek()->toDateString(), 'status' => 'draft']);

        $groups = $this->shows();

        $this->assertEmpty($groups['needs_you']);
        $this->assertCount(1, $groups['upcoming']);
        $this->assertNull($groups['upcoming'][0]['action']);
        $this->assertNull($groups['upcoming'][0]['url']);
    }

    public function test_upcoming_shows_read_forwards(): void
    {
        // The next one you are running is the one you care about.
        $soon  = $this->show(['title' => 'Soon', 'show_date' => today()->addDays(2)->toDateString(), 'status' => 'draft']);
        $later = $this->show(['title' => 'Later', 'show_date' => today()->addDays(20)->toDateString(), 'status' => 'draft']);

        $upcoming = $this->shows()['upcoming'];

        $this->assertSame('Soon', $upcoming[0]['title']);
        $this->assertSame('Later', $upcoming[1]['title']);
    }

    public function test_another_streamers_show_never_appears(): void
    {
        $this->show([], mine: false);

        $groups = $this->shows();

        $this->assertEmpty($groups['needs_you']);
        $this->assertEmpty($groups['waiting']);
        $this->assertEmpty($groups['done']);
        $this->assertEmpty($groups['upcoming']);
    }

    public function test_cancelled_shows_are_left_out(): void
    {
        $this->show(['status' => 'cancelled']);

        $this->assertEmpty($this->shows()['needs_you']);
    }

    public function test_each_show_carries_its_shipment_count(): void
    {
        $show = $this->show();

        foreach (range(1, 3) as $i) {
            \App\Models\Shipment::create([
                'show_id'         => $show->id,
                'tracking_number' => 'TRK-' . $i,
                'status'          => 'pending',
            ]);
        }

        $this->assertSame(3, $this->shows()['needs_you'][0]['shipments']);
    }

    public function test_the_action_links_into_the_report_for_that_show(): void
    {
        $show = $this->show();

        $this->assertStringContainsString('end-of-stream', $this->shows()['needs_you'][0]['url']);
        $this->assertStringContainsString((string) $show->id, $this->shows()['needs_you'][0]['url']);
    }

    public function test_the_nav_badge_counts_only_what_needs_the_streamer(): void
    {
        $needs = $this->show();
        $done  = $this->show();
        $this->entry($done, ['status' => 'admin_approved', 'submitted_at' => now()]);

        $this->assertSame('1', StreamerShows::getNavigationBadge());
    }

    public function test_the_page_renders(): void
    {
        $this->show();

        Livewire::test(StreamerShows::class)
            ->assertSuccessful()
            ->assertSee('Waiting on you');
    }

    public function test_someone_who_is_not_a_streamer_does_not_get_the_link(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        // The link is a streamer's; opening the page is a permissions
        // question, and an explicit grant on Roles & Permissions has to keep
        // winning there — so only the sidebar is checked.
        $this->assertFalse(StreamerShows::shouldRegisterNavigation());
    }

    public function test_a_streamer_gets_the_page_in_their_navigation(): void
    {
        // It used to return false unconditionally, so the page existed and
        // nothing ever linked to it.
        $this->assertTrue(StreamerShows::shouldRegisterNavigation());
    }
}

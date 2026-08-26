<?php

namespace Tests\Feature\Reports;

use App\Filament\Pages\StreamerAnalytics;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\Setting;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Streamer Analytics compares people, which is exactly why it must not be
 * open to the people it compares.
 *
 * The page can be granted to a streamer on Roles & Permissions, and that grant
 * used to hand them the whole team's revenue, margin and payout — including a
 * weekly table built from every show in the business. An admin still sees
 * everyone; anyone else sees themselves.
 */
class StreamerAnalyticsScopeTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = WhatnotChannel::create(['name' => 'Test Channel', 'status' => 'active']);

        // The page lives behind the reporting module, which is off in a fresh
        // database — without this every one of these tests would be asserting
        // against a page nobody can open.
        Setting::set('enabled_admin_modules', json_encode(['streams', 'reporting', 'payouts']));
        AdminModules::flushMemo();
    }

    private function streamer(string $name): Streamer
    {
        return Streamer::create([
            'name'         => $name,
            'status'       => 'active',
            'payout_type'  => 'flat_rate',
            'flat_rate'    => 100,
            'include_tips' => false,
        ]);
    }

    private function show(Streamer $streamer, float $gross, string $title): Show
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => $title,
            'show_date'          => now()->toDateString(),
            'gross_revenue'      => $gross,
            'whatnot_net'        => $gross * 0.9,
            'tips'               => 0,
            'units_sold'         => 10,
            'show_duration'      => 60,
            'status'             => 'pending_approval',
        ]);

        $show->streamers()->attach($streamer->id);

        return $show;
    }

    /** A streamer who has been granted the analytics page by their role. */
    private function grantedStreamer(Streamer $streamer): User
    {
        Role::findOrCreate('streamer', 'web');
        NavVisibility::setVisibleForRole('streamer', [StreamerAnalytics::class]);
        NavVisibility::flushMemo();

        $user = User::factory()->create(['email' => 'own-figures@example.test']);
        $user->assignRole('streamer');

        $streamer->forceFill(['user_id' => $user->id])->save();

        return $user->fresh();
    }

    private function admin(): User
    {
        return (User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh();
    }

    public function test_an_admin_still_sees_everybody(): void
    {
        $mine   = $this->streamer('Mine');
        $theirs = $this->streamer('Theirs');

        $this->show($mine, 1000, 'My show');
        $this->show($theirs, 2000, 'Their show');

        $rows = Livewire::actingAs($this->admin())
            ->test(StreamerAnalytics::class)
            ->instance()
            ->analyticsRows;

        $this->assertEqualsCanonicalizing(['Mine', 'Theirs'], array_column($rows, 'name'));
    }

    public function test_a_streamer_sees_only_their_own_row(): void
    {
        $mine   = $this->streamer('Mine');
        $theirs = $this->streamer('Theirs');

        $this->show($mine, 1000, 'My show');
        $this->show($theirs, 2000, 'Their show');

        $page = Livewire::actingAs($this->grantedStreamer($mine))->test(StreamerAnalytics::class);

        $this->assertSame(['Mine'], array_column($page->instance()->analyticsRows, 'name'));

        // And the picker cannot be used to ask for somebody else.
        $this->assertSame(['Mine'], $page->instance()->streamersList->pluck('name')->all());
    }

    public function test_the_weekly_table_is_not_the_whole_business(): void
    {
        // The largest of the leaks: this table was built from every show in the
        // period, so it reported the company's gross and net to whoever opened
        // the page.
        $mine   = $this->streamer('Mine');
        $theirs = $this->streamer('Theirs');

        $this->show($mine, 1000, 'My show');
        $this->show($theirs, 2000, 'Their show');

        $weekly = Livewire::actingAs($this->grantedStreamer($mine))
            ->test(StreamerAnalytics::class)
            ->instance()
            ->weeklyRows;

        $this->assertSame(1000.0, array_sum(array_column($weekly, 'gross')));
    }

    public function test_selecting_another_streamer_by_hand_does_not_widen_it(): void
    {
        $mine   = $this->streamer('Mine');
        $theirs = $this->streamer('Theirs');

        $this->show($mine, 1000, 'My show');
        $this->show($theirs, 2000, 'Their show');

        $rows = Livewire::actingAs($this->grantedStreamer($mine))
            ->test(StreamerAnalytics::class)
            ->set('selectedStreamers', [$theirs->id])
            ->instance()
            ->analyticsRows;

        $this->assertSame([], array_column($rows, 'name'));
    }

    public function test_someone_who_is_not_a_streamer_sees_nothing_rather_than_everything(): void
    {
        // The dangerous default: "no streamer record" must not fall through to
        // "no restriction".
        $mine = $this->streamer('Mine');
        $this->show($mine, 1000, 'My show');

        Role::findOrCreate('fulfillment', 'web');
        NavVisibility::setVisibleForRole('fulfillment', [StreamerAnalytics::class]);
        NavVisibility::flushMemo();

        $user = User::factory()->create(['email' => 'not-a-streamer@example.test']);
        $user->assignRole('fulfillment');

        $rows = Livewire::actingAs($user->fresh())
            ->test(StreamerAnalytics::class)
            ->instance()
            ->analyticsRows;

        $this->assertSame([], $rows);
    }

    public function test_the_export_carries_the_same_restriction(): void
    {
        $mine   = $this->streamer('Mine');
        $theirs = $this->streamer('Theirs');

        $this->show($mine, 1000, 'My show');
        $this->show($theirs, 2000, 'Their show');

        $page = Livewire::actingAs($this->grantedStreamer($mine))->test(StreamerAnalytics::class);

        // The file is written from the same rows the table renders, so the
        // rows being scoped is what makes the download safe.
        $this->assertSame(['Mine'], array_column($page->instance()->analyticsRows, 'name'));

        $page->call('exportCsv')->assertFileDownloaded();
    }
}

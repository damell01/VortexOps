<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EndOfStreamForm;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Opening End of Stream on a show nobody owns must not be a 500.
 *
 * streamer_log_entries.streamer_id is NOT NULL, and reportStreamerId() is
 * declared ?int — it is allowed to come back empty. logEntry() passed it
 * straight into firstOrCreate() anyway, so the two together produced an
 * integrity-constraint violation rather than a missing row.
 *
 * It needs all three of its fallbacks to fail, which is not exotic: an
 * imported show before streamer detection has run names nobody, and neither
 * an admin nor a streamer whose account was never linked has a profile of
 * their own. Both are ordinary production states, and the whole page 500'd.
 *
 * Every caller in the page already guards on a null entry — that is what the
 * code was written for. Only logEntry() itself did not.
 */
class EndOfStreamNeedsSomeoneToReportForTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('streamer', 'web');
        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
    }

    private function show(string $title = 'Imported show'): Show
    {
        return Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => $title,
            'show_date'          => now()->subDay()->toDateString(),
            'import_source'      => 'auto_whatnot',
        ]);
    }

    public function test_a_show_with_no_streamer_does_not_blow_up(): void
    {
        $user = User::factory()->create(['email' => 'nolink@example.com']);
        $user->assignRole('streamer');

        $show = $this->show();

        Livewire::actingAs($user->fresh())
            ->test(EndOfStreamForm::class, ['showId' => (string) $show->id])
            ->assertOk();

        $this->assertSame(0, StreamerLogEntry::count(), 'a report was created with nobody to own it');
    }

    public function test_it_says_why_instead_of_showing_a_dead_form(): void
    {
        $user = User::factory()->create(['email' => 'nolink@example.com']);
        $user->assignRole('streamer');

        Livewire::actingAs($user->fresh())
            ->test(EndOfStreamForm::class, ['showId' => (string) $this->show()->id])
            ->assertOk()
            ->assertSee('This report cannot be started yet')
            ->assertSee('not linked to a streamer profile');
    }

    public function test_an_admin_is_told_to_assign_a_streamer(): void
    {
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);

        Livewire::actingAs($owner)
            ->test(EndOfStreamForm::class, ['showId' => (string) $this->show()->id])
            ->assertOk()
            ->assertSee('Detect Streamers');
    }

    public function test_a_show_with_a_streamer_still_opens_a_report(): void
    {
        // The guard must not cost the ordinary case, which is the whole point
        // of the page.
        $user = User::factory()->create(['email' => 'linked@example.com']);
        $user->assignRole('streamer');

        $streamer = Streamer::create([
            'user_id' => $user->id, 'name' => 'Tyler', 'email' => 'linked@example.com',
            'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $show = $this->show('Owned show');
        $show->streamers()->attach($streamer->id);

        Livewire::actingAs($user->fresh())
            ->test(EndOfStreamForm::class, ['showId' => (string) $show->id])
            ->assertOk()
            ->assertDontSee('This report cannot be started yet');

        $entry = StreamerLogEntry::firstWhere('show_id', $show->id);

        $this->assertNotNull($entry, 'the report was not created for a show that has a streamer');
        $this->assertSame($streamer->id, $entry->streamer_id);
    }

    public function test_the_show_can_supply_the_streamer_when_the_viewer_cannot(): void
    {
        // An admin filing on someone else's behalf: they have no profile, the
        // show does, and that is enough.
        $other = User::factory()->create(['email' => 'other@example.com']);
        $streamer = Streamer::create([
            'user_id' => $other->id, 'name' => 'Jordan', 'email' => 'other@example.com',
            'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $show = $this->show('Assigned show');
        $show->streamers()->attach($streamer->id);

        Livewire::actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']))
            ->test(EndOfStreamForm::class, ['showId' => (string) $show->id])
            ->assertOk();

        $this->assertSame($streamer->id, StreamerLogEntry::firstWhere('show_id', $show->id)?->streamer_id);
    }
}

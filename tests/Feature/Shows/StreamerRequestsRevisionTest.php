<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\StreamerShows;
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
 * A streamer asking for a filed report back.
 *
 * The edit window closes two hours after submission. After that the only route
 * was to find an admin in person and ask them to press Request Changes — so a
 * wrong number stayed wrong, because the person who spotted it had no way to
 * say so and the person who could fix it never heard.
 */
class StreamerRequestsRevisionTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;
    private User $user;
    private Streamer $streamer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('streamer', 'web');

        $this->user = User::factory()->create(['email' => 'revision@example.test']);
        $this->user->assignRole('streamer');

        $this->streamer = Streamer::create([
            'user_id'     => $this->user->id,
            'name'        => 'Tyler',
            'email'       => 'revision@example.test',
            'status'      => 'active',
            'payout_type' => 'profit_share',
        ]);

        $channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Break #51',
            'show_date'          => now()->subDays(3)->toDateString(),
            'status'             => 'mapping',
        ]);

        $this->show->streamers()->attach($this->streamer->id);
    }

    /** A report filed long enough ago that the edit window has closed. */
    private function filedReport(array $attributes = []): StreamerLogEntry
    {
        return StreamerLogEntry::create(array_merge([
            'show_id'              => $this->show->id,
            'streamer_id'          => $this->streamer->id,
            'status'               => 'streamer_reviewed',
            'submitted_at'         => now()->subDay(),
            'streamer_reviewed_at' => now()->subDay(),
            'edit_window_minutes'  => 120,
        ], $attributes));
    }

    private function page()
    {
        return Livewire::actingAs($this->user->fresh())->test(StreamerShows::class);
    }

    public function test_a_filed_report_past_its_edit_window_can_be_asked_back(): void
    {
        $entry = $this->filedReport();

        $this->assertTrue($entry->canRequestRevision());

        $this->page()
            ->call('askForChanges', $this->show->id)
            ->set('revisionReason', 'I logged 3 of the wrong box')
            ->call('submitRevisionRequest');

        $entry->refresh();

        $this->assertTrue($entry->hasPendingRevisionRequest());
        $this->assertSame('I logged 3 of the wrong box', $entry->revision_reason);
    }

    public function test_the_report_itself_is_not_changed_by_asking(): void
    {
        // The request is a flag on top of a filed report, not a status change:
        // the numbers still count until an admin decides otherwise.
        $entry = $this->filedReport();

        $this->page()
            ->call('askForChanges', $this->show->id)
            ->set('revisionReason', 'Wrong box')
            ->call('submitRevisionRequest');

        $entry->refresh();

        $this->assertSame('streamer_reviewed', $entry->status);
        $this->assertNotNull($entry->submitted_at);
    }

    public function test_it_is_not_offered_while_they_can_simply_edit_it(): void
    {
        // Inside the edit window the answer is "just change it".
        $entry = $this->filedReport(['submitted_at' => now()->subMinutes(5)]);

        $this->assertTrue($entry->canStreamerEdit());
        $this->assertFalse($entry->canRequestRevision());
    }

    public function test_asking_twice_is_not_offered(): void
    {
        $entry = $this->filedReport(['revision_requested_at' => now()]);

        $this->assertFalse($entry->canRequestRevision());
    }

    public function test_someone_elses_show_cannot_be_asked_about(): void
    {
        $other = Streamer::create(['name' => 'Someone Else', 'status' => 'active', 'payout_type' => 'profit_share']);

        $theirShow = Show::create([
            'whatnot_channel_id' => $this->show->whatnot_channel_id,
            'title'              => 'Not mine',
            'show_date'          => now()->subDays(3)->toDateString(),
            'status'             => 'mapping',
        ]);
        $theirShow->streamers()->attach($other->id);

        $theirEntry = StreamerLogEntry::create([
            'show_id'             => $theirShow->id,
            'streamer_id'         => $other->id,
            'status'              => 'streamer_reviewed',
            'submitted_at'        => now()->subDay(),
            'edit_window_minutes' => 120,
        ]);

        $this->page()
            ->set('revisionFor', $theirShow->id)
            ->set('revisionReason', 'let me in')
            ->call('submitRevisionRequest');

        $this->assertFalse($theirEntry->refresh()->hasPendingRevisionRequest());
    }

    public function test_the_card_says_it_has_been_asked_for(): void
    {
        $this->filedReport(['revision_requested_at' => now(), 'revision_reason' => 'Wrong box']);

        $this->page()->assertSee('Changes asked for');
    }

    public function test_approving_the_report_answers_the_request(): void
    {
        // Otherwise it sits in the admin's "asked to reopen" filter for ever,
        // long after the answer was given.
        $entry = $this->filedReport(['revision_requested_at' => now(), 'revision_reason' => 'Wrong box']);

        $entry->approveAutomatically('Approved in test');

        $entry->refresh();

        $this->assertFalse($entry->hasPendingRevisionRequest());
        $this->assertNull($entry->revision_reason);
    }
}

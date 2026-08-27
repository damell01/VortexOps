<?php

namespace Tests\Feature\Shows;

use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The show report from end to end: file it, it locks, ask for it back, an admin
 * answers, sign it off.
 *
 * Every step of this existed before this test; what did not exist was one
 * definition of each step. Approving was written out inline on the list screen,
 * again on the edit page, again on the show widget, and sending one back four
 * more times — so "approved" meant locked and posted on one screen and three
 * columns on another, and "request changes" reopened the report on one and left
 * it locked against the streamer on the next.
 *
 * These tests are about the transitions rather than the buttons, so the model
 * stays the one place that knows what filing, reopening and signing off mean.
 */
class ShowReportFlowTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;
    private Streamer $streamer;
    private User $streamerUser;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('streamer', 'web');

        $this->streamerUser = User::factory()->create(['email' => 'flow-streamer@example.test']);
        $this->streamerUser->assignRole('streamer');

        $this->admin = User::factory()->create(['email' => 'flow-admin@example.test']);
        $this->admin->assignRole('admin');

        $channel = WhatnotChannel::create(['name' => 'Flow Channel', 'status' => 'active']);

        $this->streamer = Streamer::create([
            'user_id'           => $this->streamerUser->id,
            'name'              => 'Flow Streamer',
            'email'             => 'flow-streamer@example.test',
            'status'            => 'active',
            'payout_type'       => 'profit_share',
            'payout_percentage' => 8,
            'include_tips'      => false,
        ]);

        $this->show = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Flow Night',
            'show_date'          => today()->toDateString(),
            'status'             => 'mapping',
        ]);

        $this->show->streamers()->attach($this->streamer->id);

        // Review is a person's job in these tests; the auto-approve policies get
        // their own coverage elsewhere.
        Setting::set('show_report_review_policy', 'required');
        Setting::set('show_inventory_posting_policy', 'never');
    }

    private function entry(array $attributes = []): StreamerLogEntry
    {
        return StreamerLogEntry::create(array_merge([
            'show_id'     => $this->show->id,
            'streamer_id' => $this->streamer->id,
            'status'      => 'pending',
        ], $attributes));
    }

    // ── Filing it ──────────────────────────────────────────────────────────

    public function test_a_filed_report_waits_on_an_admin(): void
    {
        $entry = $this->entry();
        $entry->submitReport();

        $entry->refresh();

        $this->assertTrue($entry->isSubmitted());
        $this->assertSame('streamer_reviewed', $entry->status);
        $this->assertSame('pending_approval', $entry->approval_status);
    }

    public function test_the_edit_window_closes_and_the_report_is_then_locked_to_them(): void
    {
        $entry = $this->entry();
        $entry->submitReport();

        // Inside the window they can still fix a typo without asking anybody.
        $this->assertTrue($entry->fresh()->canStreamerEdit());

        $this->travel($entry->edit_window_minutes + 1)->minutes();

        $this->assertFalse($entry->fresh()->canStreamerEdit());
        $this->assertTrue($entry->fresh()->canRequestRevision());
    }

    // ── Asking for it back ─────────────────────────────────────────────────

    public function test_asking_for_it_back_does_not_change_the_report(): void
    {
        $entry = $this->entry();
        $entry->submitReport();
        $this->travel($entry->edit_window_minutes + 1)->minutes();

        $entry->requestRevision('Missed a giveaway box.');
        $entry->refresh();

        $this->assertTrue($entry->hasPendingRevisionRequest());
        $this->assertSame('streamer_reviewed', $entry->status);
        $this->assertFalse($entry->canStreamerEdit());
    }

    public function test_an_admin_granting_it_hands_the_edit_window_back(): void
    {
        $entry = $this->entry();
        $entry->submitReport();
        $this->travel($entry->edit_window_minutes + 1)->minutes();
        $entry->requestRevision('Missed a giveaway box.');

        $entry->reopenForEditing();
        $entry->refresh();

        $this->assertTrue($entry->canStreamerEdit());
        $this->assertFalse($entry->hasPendingRevisionRequest());
        $this->assertSame('pending_approval', $entry->approval_status);
    }

    public function test_a_reopened_report_is_not_marked_as_faulted(): void
    {
        // Reopening on request used to be done with Reject & Return, which is
        // an admin saying the report is wrong — and which puts the stock back.
        $entry = $this->entry();
        $entry->submitReport();
        $entry->requestRevision('Just a typo.');

        $entry->reopenForEditing();

        $this->assertNotSame('rejected', $entry->fresh()->approval_status);
        $this->assertNotSame('changes_requested', $entry->fresh()->status);
    }

    public function test_declining_answers_the_request_and_leaves_the_report_alone(): void
    {
        $entry = $this->entry();
        $entry->submitReport();
        $this->travel($entry->edit_window_minutes + 1)->minutes();
        $entry->requestRevision('Can I redo this?');

        $entry->declineRevisionRequest('The pay run for that week is already out.');
        $entry->refresh();

        $this->assertFalse($entry->hasPendingRevisionRequest());
        $this->assertFalse($entry->canStreamerEdit());
        $this->assertSame('streamer_reviewed', $entry->status);
    }

    public function test_a_request_can_be_made_again_once_it_has_been_answered(): void
    {
        $entry = $this->entry();
        $entry->submitReport();
        $this->travel($entry->edit_window_minutes + 1)->minutes();

        $entry->requestRevision('First ask.');
        $this->assertFalse($entry->fresh()->canRequestRevision());

        $entry->declineRevisionRequest('Not this time.');
        $this->assertTrue($entry->fresh()->canRequestRevision());
    }

    // ── Sending it back ────────────────────────────────────────────────────

    public function test_sending_a_report_back_actually_reopens_it(): void
    {
        // The bulk version used to reset four columns and leave submitted_at
        // and locked_at alone, so it told the streamer to fix the report and
        // left them locked out of it.
        $entry = $this->entry();
        $entry->submitReport();
        $entry->approveByAdmin();

        $entry->sendBackToStreamer('The hours look wrong.');
        $entry->refresh();

        $this->assertSame('changes_requested', $entry->status);
        $this->assertFalse($entry->isSubmitted());
        $this->assertFalse($entry->isLocked());
        $this->assertTrue($entry->canStreamerEdit());
    }

    public function test_sending_it_back_answers_a_pending_request_too(): void
    {
        $entry = $this->entry();
        $entry->submitReport();
        $entry->requestRevision('Wrong item.');

        $entry->sendBackToStreamer('Agreed — fix the item and resubmit.');

        $this->assertFalse($entry->fresh()->hasPendingRevisionRequest());
    }

    public function test_a_report_sent_back_can_be_filed_again(): void
    {
        $entry = $this->entry();
        $entry->submitReport();
        $entry->sendBackToStreamer('Add the giveaway.');

        $entry->fresh()->submitReport();
        $entry->refresh();

        $this->assertSame('streamer_reviewed', $entry->status);
        $this->assertSame('pending_approval', $entry->approval_status);
    }

    // ── Signing it off ─────────────────────────────────────────────────────

    public function test_approving_locks_the_report(): void
    {
        $this->actingAs($this->admin);

        $entry = $this->entry();
        $entry->submitReport();

        $entry->approveByAdmin();
        $entry->refresh();

        $this->assertSame('admin_approved', $entry->status);
        $this->assertSame('approved', $entry->approval_status);
        $this->assertTrue($entry->isLocked());
        $this->assertFalse($entry->canStreamerEdit());
        $this->assertSame($this->admin->id, $entry->reviewed_by);
    }

    public function test_approving_answers_a_pending_request(): void
    {
        // Otherwise the report sat in the reopen queue after it had been
        // decided, and the streamer's ask never got an answer either way.
        $this->actingAs($this->admin);

        $entry = $this->entry();
        $entry->submitReport();
        $entry->requestRevision('One more look?');

        $entry->approveByAdmin();

        $this->assertFalse($entry->fresh()->hasPendingRevisionRequest());
    }

    public function test_an_approved_report_can_still_be_asked_about(): void
    {
        // The edit window is long gone by the time a pay run is checked, so
        // this is where most requests actually come from.
        $entry = $this->entry();
        $entry->submitReport();
        $entry->approveByAdmin();

        $this->assertTrue($entry->fresh()->canRequestRevision());
    }

    // ── The screens agree with the model ───────────────────────────────────

    public function test_approving_from_the_list_screen_locks_it_like_everywhere_else(): void
    {
        // This screen used to set status, reviewed_by and reviewed_at inline
        // and stop there, so a report approved from the list stayed unlocked
        // and stuck at 'pending_approval' while the same word on the edit page
        // locked it. Anything reading approval_status disagreed with the badge.
        $entry = $this->entry();
        $entry->submitReport();

        \Livewire\Livewire::actingAs($this->admin->fresh())
            ->test(\App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries::class)
            ->loadTable()
            ->callTableAction('admin_approve', $entry);

        $entry->refresh();

        $this->assertSame('approved', $entry->approval_status);
        $this->assertTrue($entry->isLocked());
    }

    public function test_sending_back_from_the_list_screen_reopens_it(): void
    {
        $entry = $this->entry();
        $entry->submitReport();
        $entry->approveByAdmin();

        \Livewire\Livewire::actingAs($this->admin->fresh())
            ->test(\App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries::class)
            ->loadTable()
            ->callTableAction('send_back', $entry, ['notes' => 'Hours look wrong.']);

        $entry->refresh();

        $this->assertSame('changes_requested', $entry->status);
        $this->assertTrue($entry->canStreamerEdit());
    }

    public function test_an_edit_request_can_be_answered_from_the_list_screen(): void
    {
        $entry = $this->entry();
        $entry->submitReport();
        $this->travel($entry->edit_window_minutes + 1)->minutes();
        $entry->requestRevision('Missed a box.');

        \Livewire\Livewire::actingAs($this->admin->fresh())
            ->test(\App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries::class)
            ->loadTable()
            ->callTableAction('approve_edit_request', $entry);

        $entry->refresh();

        $this->assertFalse($entry->hasPendingRevisionRequest());
        $this->assertTrue($entry->canStreamerEdit());
    }

    public function test_pending_edit_requests_have_somewhere_to_be_seen(): void
    {
        // A one-off notification was the whole of it before: miss that and the
        // streamer is waiting on an answer nobody can see they asked for.
        $entry = $this->entry();
        $entry->submitReport();
        $entry->requestRevision('Missed a box.');

        \Livewire\Livewire::actingAs($this->admin->fresh())
            ->test(\App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries::class)
            ->set('activeTab', 'edit_requested')
            ->loadTable()
            ->assertCanSeeTableRecords([$entry]);
    }

    public function test_the_show_pipeline_follows_the_report(): void
    {
        $this->actingAs($this->admin);

        $entry = $this->entry();
        $step = fn (string $key) => collect($this->show->fresh()->pipelineSteps())->firstWhere('key', $key)['status'];

        $this->assertSame('current', $step('streamer_reviewed'));

        $entry->submitReport();
        $this->assertSame('done', $step('streamer_reviewed'));
        $this->assertSame('current', $step('log_approved'));

        $entry->approveByAdmin();
        $this->assertSame('done', $step('log_approved'));
    }
}

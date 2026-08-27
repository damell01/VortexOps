<?php

namespace Tests\Feature\Payouts;

use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The final sign-off: a week cannot be closed over reports still being argued
 * about without somebody saying so.
 *
 * Finalizing approves every payout in the run, moves the money into each
 * streamer's balance and cannot be undone. It used to check one thing — that
 * the batch was a draft — so a week whose reports were unapproved, unfiled, or
 * sitting on an open edit request paid out exactly the same as one everybody
 * had signed off.
 */
class PayRunSignOffTest extends TestCase
{
    use RefreshDatabase;

    private Streamer $streamer;
    private User $admin;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('streamer', 'web');

        $this->admin = User::factory()->create(['email' => 'signoff-admin@example.test']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->channel = WhatnotChannel::create(['name' => 'Sign Off', 'status' => 'active']);

        $streamerUser = User::factory()->create(['email' => 'signoff-streamer@example.test']);
        $streamerUser->assignRole('streamer');

        $this->streamer = Streamer::create([
            'user_id'           => $streamerUser->id,
            'name'              => 'Sign Off Streamer',
            'email'             => 'signoff-streamer@example.test',
            'status'            => 'active',
            'payout_type'       => 'profit_share',
            'payout_percentage' => 8,
            'include_tips'      => false,
        ]);

        Setting::set('show_report_review_policy', 'required');
        Setting::set('show_inventory_posting_policy', 'never');
    }

    private function batchWithShow(): array
    {
        $batch = WeeklyPayoutBatch::create([
            'week_start' => today()->startOfWeek()->toDateString(),
            'week_end'   => today()->endOfWeek()->toDateString(),
            'status'     => 'draft',
        ]);

        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Sign Off Night',
            'show_date'          => today()->toDateString(),
            'status'             => 'mapping',
        ]);

        $show->streamers()->attach($this->streamer->id);

        Payout::create([
            'weekly_payout_batch_id' => $batch->id,
            'show_id'                => $show->id,
            'streamer_id'            => $this->streamer->id,
            'payout_type'            => 'profit_share',
            'calculated_payout'      => 250.00,
            'status'                 => 'pending',
        ]);

        return [$batch, $show];
    }

    private function report(Show $show): StreamerLogEntry
    {
        return StreamerLogEntry::create([
            'show_id'     => $show->id,
            'streamer_id' => $this->streamer->id,
            'status'      => 'pending',
        ]);
    }

    private function service(): PayoutService
    {
        return app(PayoutService::class);
    }

    public function test_a_week_whose_reports_are_all_approved_signs_off_clean(): void
    {
        [$batch, $show] = $this->batchWithShow();

        $entry = $this->report($show);
        $entry->submitReport();
        $entry->approveByAdmin();

        $this->assertSame([], $this->service()->signOffProblems($batch));

        $this->service()->finalizeBatch($batch);

        $this->assertSame('finalized', $batch->fresh()->status);
    }

    public function test_a_report_that_was_never_filed_is_named(): void
    {
        [$batch] = $this->batchWithShow();

        $problems = $this->service()->signOffProblems($batch);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('Sign Off Night', $problems[0]);
        $this->assertStringContainsString('no show report was ever filed', $problems[0]);
    }

    public function test_an_unapproved_report_is_named(): void
    {
        [$batch, $show] = $this->batchWithShow();

        $this->report($show)->submitReport();

        $problems = $this->service()->signOffProblems($batch);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('not approved', $problems[0]);
    }

    public function test_an_open_edit_request_is_named(): void
    {
        // Approved, but the streamer has since asked for it back — the week is
        // being argued about and paying it out settles the argument silently.
        [$batch, $show] = $this->batchWithShow();

        $entry = $this->report($show);
        $entry->submitReport();
        $entry->approveByAdmin();
        $entry->requestRevision('I missed a box.');

        $problems = $this->service()->signOffProblems($batch);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('asked to reopen', $problems[0]);
    }

    public function test_finalizing_over_open_reports_is_refused(): void
    {
        [$batch] = $this->batchWithShow();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not signed off yet');

        $this->service()->finalizeBatch($batch);
    }

    public function test_payroll_can_still_close_a_week_a_streamer_never_filed_for(): void
    {
        [$batch] = $this->batchWithShow();

        $this->service()->finalizeBatch($batch, force: true);

        $this->assertSame('finalized', $batch->fresh()->status);
    }

    public function test_closing_a_week_over_open_reports_is_written_on_the_pay_run(): void
    {
        // The week somebody asks about months later. The answer should be on
        // the pay run, not only in an activity log nobody opens.
        [$batch] = $this->batchWithShow();

        $this->service()->finalizeBatch($batch, force: true);

        $notes = $batch->fresh()->notes;

        $this->assertStringContainsString('not signed off', $notes);
        $this->assertStringContainsString('Sign Off Night', $notes);
    }

    public function test_a_clean_pay_run_gets_no_override_note(): void
    {
        [$batch, $show] = $this->batchWithShow();

        $entry = $this->report($show);
        $entry->submitReport();
        $entry->approveByAdmin();

        $this->service()->finalizeBatch($batch, force: true);

        $this->assertNull($batch->fresh()->notes);
    }

    public function test_a_finalized_run_cannot_be_finalized_again(): void
    {
        [$batch, $show] = $this->batchWithShow();

        $entry = $this->report($show);
        $entry->submitReport();
        $entry->approveByAdmin();

        $this->service()->finalizeBatch($batch);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only draft batches');

        $this->service()->finalizeBatch($batch->fresh());
    }
}

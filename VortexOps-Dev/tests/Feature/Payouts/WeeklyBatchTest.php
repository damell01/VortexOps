<?php

namespace Tests\Feature\Payouts;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\CreateWeeklyPayoutBatch;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLoan;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WeeklyBatchTest extends TestCase
{
    use RefreshDatabase;

    private PayoutService $service;
    private User $user;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayoutService::class);
        $this->user    = User::factory()->create();
        $this->actingAs($this->user);
        $this->channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);
    }

    private function makeShow(array $attrs = []): Show
    {
        return Show::create(array_merge([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Weekly Show',
            'show_date'          => now()->toDateString(),
            'gross_revenue'      => 500,
            'whatnot_net'        => 450,
            'tips'               => 20,
            'units_sold'         => 10,
            'show_duration'      => 60,
            'status'             => 'reconciled',
            'created_by'         => $this->user->id,
        ], $attrs));
    }

    private function makeStreamer(array $attrs = []): Streamer
    {
        return Streamer::create(array_merge([
            'name'         => 'Test Streamer',
            'status'       => 'active',
            'payout_type'  => 'profit_share',
            'payout_percentage' => 30,
            'include_tips' => false,
        ], $attrs));
    }

    public function test_batch_recalculate_total_sums_payouts(): void
    {
        $batch = WeeklyPayoutBatch::create([
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end'   => now()->endOfWeek()->toDateString(),
            'status'     => 'draft',
            'created_by' => $this->user->id,
        ]);

        $show     = $this->makeShow();
        $streamer = $this->makeStreamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $payouts = $this->service->calculateForShow($show);

        $payouts[0]->update(['weekly_payout_batch_id' => $batch->id]);
        $batch->recalculateTotal();

        $this->assertEquals(
            round($payouts[0]->calculated_payout, 2),
            (float) $batch->fresh()->total_payout,
        );
    }

    public function test_create_weekly_batch_pulls_in_draft_payouts_for_the_week(): void
    {
        $show     = $this->makeShow(['show_date' => now()->toDateString()]);
        $streamer = $this->makeStreamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->service->calculateForShow($show);

        $batch = $this->service->createWeeklyBatch(now()->toDateString());

        $this->assertGreaterThan(0, $batch->payouts()->count());
        $this->assertGreaterThan(0, (float) $batch->total_payout);
    }

    public function test_create_weekly_batch_excludes_shows_outside_the_window(): void
    {
        $lastWeekShow = $this->makeShow(['show_date' => now()->subWeek()->toDateString()]);
        $streamer     = $this->makeStreamer();
        $lastWeekShow->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->service->calculateForShow($lastWeekShow);

        $batch = $this->service->createWeeklyBatch(now()->toDateString());

        // The payout from last week's show should not be in this batch
        $this->assertEquals(0, $batch->payouts()->count());
        $this->assertEquals('0.00', $batch->total_payout);
    }

    public function test_preview_matches_finalized_total_and_does_not_mutate(): void
    {
        $show     = $this->makeShow();
        $streamer = $this->makeStreamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->service->calculateForShow($show);

        $batch = $this->service->createWeeklyBatch(now()->toDateString());

        $payoutCountBefore = $batch->payouts()->count();
        $preview = $this->service->previewFinalization($batch);

        // Preview is read-only: it must not add/alter payouts or change status.
        $this->assertEquals($payoutCountBefore, $batch->payouts()->count());
        $this->assertEquals('draft', $batch->fresh()->status);

        // The previewed net equals what finalizing actually produces.
        $this->service->finalizeBatch($batch);
        $this->assertEqualsWithDelta($preview['net'], (float) $batch->fresh()->total_payout, 0.01);
    }

    public function test_preview_accounts_for_loan_repayment_without_mutating_the_loan(): void
    {
        $show     = $this->makeShow();               // gross 500 → profit_share payout well above $50
        $streamer = $this->makeStreamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->service->calculateForShow($show);

        $loan = StreamerLoan::create([
            'streamer_id'        => $streamer->id,
            'label'              => 'Advance',
            'original_amount'    => 200,
            'weekly_repayment'   => 50,
            'remaining_balance'  => 200,
            'deduct_from_payout' => true,
            'status'             => 'active',
        ]);

        $batch   = $this->service->createWeeklyBatch(now()->toDateString());
        $preview = $this->service->previewFinalization($batch);

        // The loan repayment is reflected in the preview...
        $this->assertGreaterThan(0, $preview['loan']);
        // ...but previewing must NOT touch the loan balance.
        $this->assertEqualsWithDelta(200.0, (float) $loan->fresh()->remaining_balance, 0.01);

        // After the real finalize, the net matches the preview and the loan is now paid down.
        $this->service->finalizeBatch($batch);
        $this->assertEqualsWithDelta($preview['net'], (float) $batch->fresh()->total_payout, 0.01);
        $this->assertEqualsWithDelta(200.0 - $preview['loan'], (float) $loan->fresh()->remaining_balance, 0.01);
    }

    public function test_finalize_batch_approves_all_payouts(): void
    {
        $show     = $this->makeShow();
        $streamer = $this->makeStreamer();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->service->calculateForShow($show);

        $batch = $this->service->createWeeklyBatch(now()->toDateString());
        $this->service->finalizeBatch($batch);

        $batch->refresh();

        $this->assertEquals('finalized', $batch->status);
        $this->assertTrue($batch->payouts->every(fn ($p) => $p->status === 'approved'));
    }

    public function test_finalize_increments_streamer_earnings_due(): void
    {
        $show     = $this->makeShow();
        $streamer = $this->makeStreamer(['total_earnings_due' => 0]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $this->service->calculateForShow($show);

        $batch = $this->service->createWeeklyBatch(now()->toDateString());
        $this->service->finalizeBatch($batch);

        $expectedPayout = $batch->payouts()->first()->calculated_payout;
        $streamer->refresh();

        $this->assertEquals(
            round((float) $expectedPayout, 2),
            round((float) $streamer->total_earnings_due, 2),
        );
    }

    public function test_mark_payout_paid_updates_status_and_paid_total(): void
    {
        $show     = $this->makeShow();
        $streamer = $this->makeStreamer(['total_earnings_paid' => 0]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $payouts = $this->service->calculateForShow($show);
        $payout  = $payouts[0];

        $this->service->markPayoutPaid($payout);

        $payout->refresh();
        $streamer->refresh();

        $this->assertEquals('paid', $payout->status);
        $this->assertGreaterThan(0, (float) $streamer->total_earnings_paid);
    }

    public function test_new_pay_run_form_defaults_to_weekly_cadence_streamers_only(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $weeklyStreamer  = $this->makeStreamer(['name' => 'Weekly Wendy', 'payout_type' => 'hourly', 'payout_cadence' => 'weekly', 'hourly_rate' => 15]);
        $monthlyStreamer = $this->makeStreamer(['name' => 'Monthly Mo', 'payout_type' => 'profit_share', 'payout_cadence' => 'monthly']);

        Livewire::actingAs($admin);

        Livewire::test(CreateWeeklyPayoutBatch::class)
            ->assertFormSet(['streamer_ids' => [$weeklyStreamer->id]]);
    }

    public function test_finalize_draft_only_guard(): void
    {
        $batch = WeeklyPayoutBatch::create([
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end'   => now()->endOfWeek()->toDateString(),
            'status'     => 'finalized',
            'created_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Only draft/');

        $this->service->finalizeBatch($batch);
    }
}

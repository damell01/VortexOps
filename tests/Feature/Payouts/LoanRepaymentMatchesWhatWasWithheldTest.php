<?php

namespace Tests\Feature\Payouts;

use App\Models\Payout;
use App\Models\Streamer;
use App\Models\StreamerLoan;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A loan balance may only fall by what the pay run actually withheld.
 *
 * The cash side was always right — the payout floors at zero either way — so
 * nothing looked wrong on a payout run. The damage was to the debt ledger:
 * balances came down faster than money came back, and the difference is debt
 * written off without anyone deciding to.
 *
 * previewFinalization() had both rules already and said in a comment that it
 * matched applyLoanRepayments(). It did not. The preview showed the correct
 * figure and finalizing then wrote a different one to the loan, which is the
 * worst arrangement of the three: the number a human checked was not the
 * number that was kept.
 */
class LoanRepaymentMatchesWhatWasWithheldTest extends TestCase
{
    use RefreshDatabase;

    private PayoutService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $this->service = app(PayoutService::class);
    }

    private function streamer(string $name = 'Tyler'): Streamer
    {
        return Streamer::create([
            'name' => $name, 'email' => strtolower($name) . '@example.com',
            'status' => 'active', 'payout_type' => 'profit_share',
        ]);
    }

    /** @return array{0: WeeklyPayoutBatch, 1: Payout} */
    private function batchPaying(Streamer $streamer, float $amount): array
    {
        $batch = WeeklyPayoutBatch::create([
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end'   => now()->endOfWeek()->toDateString(),
            'status'     => 'draft',
        ]);

        $payout = Payout::create([
            'streamer_id'            => $streamer->id,
            'weekly_payout_batch_id' => $batch->id,
            'payout_type'            => 'profit_share',
            'calculated_payout'      => $amount,
            'total_due'              => $amount,
            'status'                 => 'pending',
        ]);

        return [$batch, $payout];
    }

    /** @param array<string, mixed> $attributes */
    private function loan(Streamer $streamer, array $attributes = []): StreamerLoan
    {
        return StreamerLoan::create(array_merge([
            'streamer_id'        => $streamer->id,
            'label'              => 'Advance',
            'original_amount'    => 1000,
            'weekly_repayment'   => 250,
            'remaining_balance'  => 1000,
            'deduct_from_payout' => true,
            'status'             => 'active',
        ], $attributes));
    }

    public function test_a_loan_set_not_to_auto_deduct_is_left_alone(): void
    {
        // The toggle says "Auto-deduct from payout". Off means the money is
        // collected some other way — so the pay run must not quietly credit
        // a repayment that nobody made.
        $streamer = $this->streamer();
        [$batch, $payout] = $this->batchPaying($streamer, 1000);
        $loan = $this->loan($streamer, ['deduct_from_payout' => false, 'remaining_balance' => 500, 'weekly_repayment' => 100]);

        $this->service->finalizeBatch($batch);

        $this->assertEqualsWithDelta(500.0, (float) $loan->fresh()->remaining_balance, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $payout->fresh()->calculated_payout, 0.01);
    }

    public function test_a_repayment_larger_than_the_payout_only_counts_what_was_taken(): void
    {
        // $250 owed weekly against a $60 payout: $60 is all that can be
        // withheld, so $60 is all that comes off the balance.
        $streamer = $this->streamer();
        [$batch, $payout] = $this->batchPaying($streamer, 60);
        $loan = $this->loan($streamer);

        $this->service->finalizeBatch($batch);

        $this->assertEqualsWithDelta(940.0, (float) $loan->fresh()->remaining_balance, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $payout->fresh()->calculated_payout, 0.01);
        $this->assertEqualsWithDelta(60.0, (float) $payout->fresh()->loan_repayment_deducted, 0.01);
    }

    public function test_several_loans_stop_once_the_payout_runs_out(): void
    {
        $streamer = $this->streamer();
        [$batch, $payout] = $this->batchPaying($streamer, 300);

        $first  = $this->loan($streamer, ['label' => 'First', 'weekly_repayment' => 250]);
        $second = $this->loan($streamer, ['label' => 'Second', 'weekly_repayment' => 250]);

        $this->service->finalizeBatch($batch);

        $taken = (1000 - (float) $first->fresh()->remaining_balance)
               + (1000 - (float) $second->fresh()->remaining_balance);

        $this->assertEqualsWithDelta(300.0, $taken, 0.01, 'more was written off than the payout could cover');
        $this->assertEqualsWithDelta(300.0, (float) $payout->fresh()->loan_repayment_deducted, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $payout->fresh()->calculated_payout, 0.01);
    }

    public function test_an_ordinary_repayment_still_comes_off_in_full(): void
    {
        $streamer = $this->streamer();
        [$batch, $payout] = $this->batchPaying($streamer, 900);
        $loan = $this->loan($streamer);

        $this->service->finalizeBatch($batch);

        $this->assertEqualsWithDelta(750.0, (float) $loan->fresh()->remaining_balance, 0.01);
        $this->assertEqualsWithDelta(650.0, (float) $payout->fresh()->calculated_payout, 0.01);
    }

    public function test_the_preview_and_the_finalize_agree(): void
    {
        // These two are written separately and drifted; the preview is what a
        // human approves, so it is the one that has to be true.
        $streamer = $this->streamer();
        [$batch, $payout] = $this->batchPaying($streamer, 60);
        $this->loan($streamer);
        $this->loan($streamer, ['label' => 'Ignored', 'deduct_from_payout' => false]);

        $preview = $this->service->previewFinalization($batch);

        $this->service->finalizeBatch($batch);

        $this->assertEqualsWithDelta($preview['loan'], (float) $payout->fresh()->loan_repayment_deducted, 0.01);
        $this->assertEqualsWithDelta($preview['net'], (float) $batch->fresh()->total_payout, 0.01);
    }

    public function test_marking_the_same_payout_paid_twice_pays_it_once(): void
    {
        // Outstanding is (due − paid), so a second press of the button wrote
        // off money that was never sent.
        $streamer = $this->streamer();
        [$batch, $payout] = $this->batchPaying($streamer, 400);

        $this->service->finalizeBatch($batch);
        $this->service->markPayoutPaid($payout->fresh());
        $this->service->markPayoutPaid($payout->fresh());

        $this->assertEqualsWithDelta(400.0, (float) $streamer->fresh()->total_earnings_paid, 0.01);
    }
}

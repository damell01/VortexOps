<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\StreamerLoan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Create a ledger entry, auto-computing balance_after.
     */
    public function record(array $data): LedgerEntry
    {
        return DB::transaction(function () use ($data) {
            $previous = $this->getRunningBalance();

            $direction = $data['direction'] ?? 'credit';
            $amount    = (float) ($data['amount'] ?? 0);

            $balanceAfter = $direction === 'credit'
                ? $previous + $amount
                : $previous - $amount;

            return LedgerEntry::create(array_merge($data, [
                'balance_after' => $balanceAfter,
                'created_by'    => $data['created_by'] ?? auth()->id(),
            ]));
        });
    }

    /**
     * Record a payout debit entry.
     */
    public function recordPayout(Payout $payout): void
    {
        $this->record([
            'type'             => 'payout',
            'direction'        => 'debit',
            'amount'           => (float) $payout->calculated_payout,
            'description'      => "Payout for show #{$payout->show_id}" . ($payout->streamer ? " — {$payout->streamer->name}" : ''),
            'reference_type'   => Payout::class,
            'reference_id'     => $payout->id,
            'streamer_id'      => $payout->streamer_id,
            'show_id'          => $payout->show_id,
            'pay_run_id'       => $payout->weekly_payout_batch_id,
            'transaction_date' => now()->toDateString(),
        ]);
    }

    /**
     * Record a loan repayment debit entry.
     */
    public function recordLoanRepayment(StreamerLoan $loan, float $amount): void
    {
        $this->record([
            'type'             => 'loan_repayment',
            'direction'        => 'debit',
            'amount'           => $amount,
            'description'      => "Loan repayment for streamer #{$loan->streamer_id}",
            'reference_type'   => StreamerLoan::class,
            'reference_id'     => $loan->id,
            'streamer_id'      => $loan->streamer_id,
            'transaction_date' => now()->toDateString(),
        ]);
    }

    /**
     * Record a deposit credit entry.
     */
    public function recordDeposit(float $amount, string $description, ?Carbon $date = null): LedgerEntry
    {
        return $this->record([
            'type'             => 'deposit',
            'direction'        => 'credit',
            'amount'           => $amount,
            'description'      => $description,
            'transaction_date' => ($date ?? now())->toDateString(),
        ]);
    }

    /**
     * Get the current running balance (last entry's balance_after, or 0).
     */
    public function getRunningBalance(): float
    {
        $last = LedgerEntry::latest('id')->first();
        return $last ? (float) $last->balance_after : 0.0;
    }

    /**
     * Get the balance as of a specific date (last entry on or before that date).
     */
    public function getBalanceAsOf(Carbon $date): float
    {
        $last = LedgerEntry::where('transaction_date', '<=', $date->toDateString())
            ->latest('id')
            ->first();

        return $last ? (float) $last->balance_after : 0.0;
    }
}

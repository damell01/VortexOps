<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    protected $fillable = [
        'type',
        'direction',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'streamer_id',
        'show_id',
        'pay_run_id',
        'transaction_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'balance_after'    => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function payRun(): BelongsTo
    {
        return $this->belongsTo(WeeklyPayoutBatch::class, 'pay_run_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function typeLabels(): array
    {
        return [
            'deposit'             => 'Deposit',
            'payout'              => 'Payout',
            'loan_repayment'      => 'Loan Repayment',
            'shipping_surcharge'  => 'Shipping Surcharge',
            'adjustment'          => 'Adjustment',
            'owner_fee'           => 'Owner Fee',
            'streamer_fee'        => 'Streamer Fee',
            'tips'                => 'Tips',
            'wholesale_payment'   => 'Wholesale Payment',
            'rebate'              => 'Rebate',
            'collects_transfer'   => 'Collects Transfer',
            'other'               => 'Other',
        ];
    }

    public static function directionLabels(): array
    {
        return [
            'credit' => 'Credit',
            'debit'  => 'Debit',
        ];
    }
}

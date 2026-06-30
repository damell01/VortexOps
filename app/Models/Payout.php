<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $fillable = [
        'show_id',
        'streamer_id',
        'whatnot_channel_id',
        'weekly_payout_batch_id',
        'payout_type',
        'gross_show_revenue',
        'owner_fee_deducted',
        'loan_repayment_deducted',
        'tips_included',
        'pwe_count',
        'label_count',
        'burden_rate_applied',
        'routing_bank_label',
        'calculated_payout',
        'calculation_notes',
        'status',
    ];

    protected $casts = [
        'gross_show_revenue'      => 'decimal:2',
        'owner_fee_deducted'      => 'decimal:2',
        'loan_repayment_deducted' => 'decimal:2',
        'tips_included'           => 'decimal:2',
        'burden_rate_applied'     => 'decimal:4',
        'calculated_payout'       => 'decimal:2',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class, 'show_id');
    }

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WeeklyPayoutBatch::class, 'weekly_payout_batch_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

    public static function statusLabels(): array
    {
        return [
            'draft'    => 'Draft',
            'approved' => 'Approved',
            'paid'     => 'Paid',
        ];
    }
}

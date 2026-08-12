<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamerLoan extends Model
{
    protected $fillable = [
        'streamer_id',
        'label',
        'original_amount',
        'weekly_repayment',
        'remaining_balance',
        'deduct_from_payout',
        'status',
        'notes',
    ];

    protected $casts = [
        'original_amount'     => 'decimal:2',
        'weekly_repayment'    => 'decimal:2',
        'remaining_balance'   => 'decimal:2',
        'deduct_from_payout'  => 'boolean',
    ];

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public static function statusLabels(): array
    {
        return [
            'active'   => 'Active',
            'paid_off' => 'Paid Off',
        ];
    }
}

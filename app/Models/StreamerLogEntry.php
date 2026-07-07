<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamerLogEntry extends Model
{
    protected $fillable = [
        'show_id',
        'streamer_id',
        'hard_copy',
        'hours_streamed',
        'number_of_shipments',
        'number_of_packages_over_500',
        'pwe_pay',
        'hourly_pay',
        'profit_share_amount',
        'profit_share_paid',
        'tips_paid',
        'total_due',
        'total_paid',
        'business_net_rev',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'hard_copy'                    => 'boolean',
        'profit_share_paid'            => 'boolean',
        'hours_streamed'               => 'decimal:2',
        'pwe_pay'                      => 'decimal:2',
        'hourly_pay'                   => 'decimal:2',
        'profit_share_amount'          => 'decimal:2',
        'tips_paid'                    => 'decimal:2',
        'total_due'                    => 'decimal:2',
        'total_paid'                   => 'decimal:2',
        'business_net_rev'             => 'decimal:2',
        'number_of_shipments'          => 'integer',
        'number_of_packages_over_500'  => 'integer',
        'reviewed_at'                  => 'datetime',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingSurcharge extends Model
{
    protected $fillable = [
        'show_id',
        'streamer_id',
        'package_count',
        'rate_per_package',
        'threshold_amount',
        'total_amount',
        'deducted_from_payout',
        'notes',
    ];

    protected $casts = [
        'rate_per_package'     => 'decimal:2',
        'threshold_amount'     => 'decimal:2',
        'total_amount'         => 'decimal:2',
        'deducted_from_payout' => 'boolean',
        'package_count'        => 'integer',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }
}

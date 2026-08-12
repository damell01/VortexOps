<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatnotLedgerEntry extends Model
{
    protected $fillable = [
        'whatnot_channel_id',
        'created_date',
        'completed_date',
        'amount',
        'listing_id',
        'whatnot_order_id',
        'order_hash',
        'message',
        'status',
        'transaction_type',
        'dedup_key',
        'raw_data',
    ];

    protected $casts = [
        'created_date'   => 'datetime',
        'completed_date' => 'datetime',
        'amount'         => 'decimal:2',
        'raw_data'       => 'array',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

    /** Limit to the admin's currently active channel (App\Support\ChannelContext), if any. */
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Support\ChannelContext::isScoped()
            ? $query->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
            : $query;
    }
}

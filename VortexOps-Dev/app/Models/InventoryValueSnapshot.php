<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryValueSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'whatnot_channel_id',
        'total_value',
        'total_quantity',
        'total_items',
    ];

    protected $casts = [
        // Plain 'date' serializes to the connection's full datetime format on
        // storage (e.g. "2026-07-28 00:00:00"), not a bare date — that broke
        // updateOrCreate()'s raw string search in SnapshotInventoryValue,
        // which never matched what actually got stored. Pin the storage
        // format explicitly instead.
        'snapshot_date'  => 'date:Y-m-d',
        'total_value'    => 'decimal:2',
        'total_quantity' => 'decimal:2',
        'total_items'    => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

    /**
     * Average of the daily snapshots captured within a given month, scoped
     * to a channel (or the combined all-channels snapshot when null).
     */
    public static function averageValueForMonth(\Carbon\Carbon $month, ?int $channelId = null): float
    {
        return (float) static::whereBetween('snapshot_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->where('whatnot_channel_id', $channelId)
            ->avg('total_value');
    }
}

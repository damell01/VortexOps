<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamerLogEntry extends Model
{
    protected $fillable = [
        'show_id',
        'streamer_id',
        'status',
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
        'gross_revenue',
        'product_cost',
        'reviewed_by',
        'reviewed_at',
        'streamer_reviewed_at',
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
        'gross_revenue'                => 'decimal:2',
        'product_cost'                 => 'decimal:2',
        'number_of_shipments'          => 'integer',
        'number_of_packages_over_500'  => 'integer',
        'reviewed_at'                  => 'datetime',
        'streamer_reviewed_at'         => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return [
            'pending'           => 'Pending',
            'streamer_reviewed' => 'Streamer Reviewed',
            'admin_approved'    => 'Admin Approved',
        ];
    }

    public function profitShareAmount(): float
    {
        $gross   = (float) ($this->gross_revenue ?? $this->show?->gross_revenue ?? 0);
        $cost    = (float) ($this->product_cost ?? 0);
        $psPct   = (float) ($this->streamer?->payout_percentage ?? 0);
        return round(max(0, $gross - $cost) * ($psPct / 100), 2);
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** Limit to the admin's currently active channel (App\Support\ChannelContext), if any. */
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! \App\Support\ChannelContext::isScoped()) {
            return $query;
        }

        return $query->whereHas('show', fn (\Illuminate\Database\Eloquent\Builder $q) =>
            $q->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
        );
    }

    /**
     * The items sold on this entry's show — matched on show_id so the streamer
     * can enrich them from the log page without opening the show.
     */
    public function showOrders(): HasMany
    {
        return $this->hasMany(WhatnotShowOrder::class, 'show_id', 'show_id');
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

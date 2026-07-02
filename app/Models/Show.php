<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Show extends Model
{
    use LogsActivity;

    protected $fillable = [
        'whatnot_channel_id',
        'title',
        'show_date',
        'start_time',
        'end_time',
        'units_sold',
        'gross_revenue',
        'whatnot_net',
        'tips',
        'paper_sales_gross',
        'paper_sales_units',
        'paper_sales_notes',
        'sales_reconciled',
        'show_duration',
        'import_source',
        'detail_url',
        'completed_earnings',
        'avg_order_value',
        'giveaway_spend',
        'giveaways_count',
        'buyers_count',
        'first_time_buyers',
        'returning_buyers',
        'shares_count',
        'max_concurrent_viewers',
        'total_views',
        'avg_order_rating',
        'raw_import_payload',
        'ai_streamer_suggestion',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'show_date'              => 'date',
        'gross_revenue'          => 'decimal:2',
        'whatnot_net'            => 'decimal:2',
        'tips'                   => 'decimal:2',
        'paper_sales_gross'      => 'decimal:2',
        'sales_reconciled'       => 'boolean',
        'raw_import_payload'     => 'array',
        'ai_streamer_suggestion' => 'array',
        'units_sold'             => 'integer',
        'show_duration'          => 'integer',
        'completed_earnings'     => 'decimal:2',
        'avg_order_value'        => 'decimal:2',
        'giveaway_spend'         => 'decimal:2',
        'avg_order_rating'       => 'decimal:2',
        'giveaways_count'        => 'integer',
        'buyers_count'           => 'integer',
        'first_time_buyers'      => 'integer',
        'returning_buyers'       => 'integer',
        'shares_count'           => 'integer',
        'max_concurrent_viewers' => 'integer',
        'total_views'            => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

    public function streamers(): BelongsToMany
    {
        return $this->belongsToMany(Streamer::class, 'show_streamer')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryStreamer(): ?Streamer
    {
        return $this->streamers()->wherePivot('is_primary', true)->first();
    }

    public function deductionRequests(): HasMany
    {
        return $this->hasMany(DeductionRequest::class);
    }

    public function latestDeductionRequest(): HasOne
    {
        return $this->hasOne(DeductionRequest::class)->latestOfMany();
    }

    public function ingestionLogs(): HasMany
    {
        return $this->hasMany(ShowIngestionLog::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function whatnotOrders(): HasMany
    {
        return $this->hasMany(WhatnotShowOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusLabels(): array
    {
        return [
            'draft'            => 'Draft',
            'pending_review'   => 'Pending Review',
            'mapping'          => 'Mapping',
            'pending_approval' => 'Pending Approval',
            'reconciled'       => 'Reconciled',
            'closed'           => 'Closed',
            'cancelled'        => 'Cancelled',
        ];
    }

    public static function importSourceLabels(): array
    {
        return [
            'manual'       => 'Manual',
            'auto_whatnot' => 'Auto (Whatnot)',
        ];
    }
}

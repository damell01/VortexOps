<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class WhatnotShow extends Model
{
    use LogsActivity;

    protected $fillable = [
        'whatnot_channel_id',
        'title',
        'show_date',
        'started_at',
        'ended_at',
        'status',
        'source',
        'notes',
        'raw_data',
        'created_by',
    ];

    protected $casts = [
        'show_date'  => 'date',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'raw_data'   => 'array',
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

    public function sales(): HasMany
    {
        return $this->hasMany(ShowSale::class);
    }

    public function financial(): HasOne
    {
        return $this->hasOne(ShowFinancial::class);
    }

    public function deductionRequests(): HasMany
    {
        return $this->hasMany(DeductionRequest::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalSales(): float
    {
        return (float) $this->sales()->sum('sale_price');
    }

    public function pendingDeductions(): int
    {
        return $this->deductionRequests()->where('status', 'pending')->count();
    }

    public static function statusLabels(): array
    {
        return [
            'draft'                  => 'Draft',
            'pending_reconciliation' => 'Pending Reconciliation',
            'reconciling'            => 'Reconciling',
            'reconciled'             => 'Reconciled',
            'paid'                   => 'Paid',
        ];
    }

    public static function sourceLabels(): array
    {
        return [
            'manual'     => 'Manual',
            'csv_import' => 'CSV Import',
            'scraper'    => 'Scraper',
        ];
    }
}

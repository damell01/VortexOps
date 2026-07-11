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
        'channel_attribution_suspect',
        'whatnot_show_id',
        'cover_image_url',
        'title',
        'show_date',
        'start_time',
        'end_time',
        'units_sold',
        'gross_revenue',
        'whatnot_net',
        'whatnot_fees',
        'whatnot_payout_amount',
        'last_synced_at',
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
        'shipping_surcharge_count',
        'shipping_surcharge_total',
    ];

    protected $casts = [
        'show_date'                   => 'date',
        'channel_attribution_suspect' => 'boolean',
        'gross_revenue'          => 'decimal:2',
        'whatnot_net'            => 'decimal:2',
        'whatnot_fees'           => 'decimal:2',
        'whatnot_payout_amount'  => 'decimal:2',
        'last_synced_at'         => 'datetime',
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
        'total_views'              => 'integer',
        'shipping_surcharge_count' => 'integer',
        'shipping_surcharge_total' => 'decimal:2',
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

    public function orders(): HasMany
    {
        return $this->hasMany(WhatnotShowOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shippingSurcharges(): HasMany
    {
        return $this->hasMany(ShippingSurcharge::class);
    }

    public function streamerLogEntry(): HasOne
    {
        return $this->hasOne(StreamerLogEntry::class);
    }

    /**
     * Match the show title against active streamer names and auto-attach high-confidence matches.
     * Stores results in ai_streamer_suggestion. Returns the suggestions array.
     */
    public function detectStreamers(): array
    {
        $title = strtolower($this->title ?? '');
        if (empty($title)) {
            return [];
        }

        $suggestions = [];

        foreach (Streamer::where('status', 'active')->get(['id', 'name']) as $streamer) {
            $lowerName = strtolower($streamer->name);

            if (str_contains($title, $lowerName)) {
                $suggestions[] = [
                    'streamer_id'   => $streamer->id,
                    'streamer_name' => $streamer->name,
                    'confidence'    => 'high',
                    'reason'        => "Name \"{$streamer->name}\" found in show title",
                ];
                continue;
            }

            // Check each word of the name (4+ chars to skip short words like "The", "a")
            foreach (explode(' ', $lowerName) as $part) {
                if (strlen($part) >= 4 && str_contains($title, $part)) {
                    $suggestions[] = [
                        'streamer_id'   => $streamer->id,
                        'streamer_name' => $streamer->name,
                        'confidence'    => 'medium',
                        'reason'        => "Name part \"{$part}\" found in show title",
                    ];
                    break;
                }
            }
        }

        if (! empty($suggestions)) {
            $this->update(['ai_streamer_suggestion' => $suggestions]);

            // Auto-attach only when the show has no streamers yet
            if ($this->streamers()->count() === 0) {
                $first = true;
                foreach ($suggestions as $s) {
                    if ($s['confidence'] === 'high') {
                        $this->streamers()->attach($s['streamer_id'], ['is_primary' => $first]);
                        $first = false;
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Return the primary streamer's best inventory location for deduction line defaults.
     * Prefers type=streamer_inventory, falls back to any active location owned by that streamer.
     */
    public function defaultInventoryLocation(): ?InventoryLocation
    {
        $streamer = $this->relationLoaded('streamers')
            ? $this->streamers->first()
            : $this->streamers()->first();

        if (! $streamer) {
            return null;
        }

        return InventoryLocation::where('streamer_id', $streamer->id)
            ->where('status', 'active')
            ->orderByRaw("CASE type WHEN 'streamer_inventory' THEN 0 ELSE 1 END")
            ->first();
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

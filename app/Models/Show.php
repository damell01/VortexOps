<?php

namespace App\Models;

use App\Models\Concerns\AuditsUpdates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Show extends Model
{
    use LogsActivity, AuditsUpdates;

    // AuditsUpdates records the real diff for status transitions; LogsActivity's
    // (empty) auto "updated" entry is suppressed. Scoped to status so frequent
    // metric re-imports don't flood the audit log.
    protected static array $doNotRecordEvents = ['updated'];

    /** @return array<int,string> */
    public function auditableFields(): array
    {
        return ['status'];
    }

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
        'status_changed_at',
        'notes',
        'created_by',
        'shipping_surcharge_count',
        'shipping_surcharge_total',
    ];

    protected $casts = [
        'show_date'                   => 'date',
        'status_changed_at'           => 'datetime',
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

    /**
     * Per-show profit & loss — the single source of truth for the P&L Summary
     * card and the Net Margin column. Margin = (Whatnot net + tips) − approved
     * COGS − streamer payouts, where COGS is the sum of the latest deduction
     * request's line totals. Uses loaded relations / withSum aggregates when
     * present (and explicit queries otherwise) so it stays cheap on list views
     * and safe with lazy loading disabled.
     *
     * @return array{gross: float, net: float, tips: float, cogs: float, payouts: float, margin: float, margin_pct: float}
     */
    public function profitAndLoss(): array
    {
        $gross = (float) $this->gross_revenue;
        $net   = (float) $this->whatnot_net;
        $tips  = (float) $this->tips;

        // Approved COGS from the latest deduction request's lines.
        $dr = $this->relationLoaded('latestDeductionRequest')
            ? $this->getRelation('latestDeductionRequest')
            : $this->latestDeductionRequest()->with('lines')->first();

        $cogs = 0.0;
        if ($dr) {
            $cogs = (float) ($dr->relationLoaded('lines')
                ? $dr->lines->sum('line_total')
                : $dr->lines()->sum('line_total'));
        }

        $payouts = $this->payouts_sum_calculated_payout !== null
            ? (float) $this->payouts_sum_calculated_payout
            : (float) $this->payouts()->sum('calculated_payout');

        $base   = $net + $tips;
        $margin = round($base - $cogs - $payouts, 2);

        return [
            'gross'      => round($gross, 2),
            'net'        => round($net, 2),
            'tips'       => round($tips, 2),
            'cogs'       => round($cogs, 2),
            'payouts'    => round($payouts, 2),
            'margin'     => $margin,
            'margin_pct' => $base > 0.0 ? round($margin / $base * 100, 1) : 0.0,
        ];
    }

    public function getNetProfitAttribute(): float
    {
        return $this->profitAndLoss()['margin'];
    }

    /**
     * Audience engagement, derived from the analytics fields imported with each
     * show. Conversion is buyers ÷ peak viewers (falling back to total views).
     *
     * @return array{peak_viewers:int, total_views:int, shares:int, buyers:int, first_time:int, returning:int, rating:?float, conversion_pct:?float, has_data:bool}
     */
    public function engagement(): array
    {
        $peak      = (int) ($this->max_concurrent_viewers ?? 0);
        $views     = (int) ($this->total_views ?? 0);
        $buyers    = (int) ($this->buyers_count ?? 0);
        $firstTime = (int) ($this->first_time_buyers ?? 0);
        $returning = (int) ($this->returning_buyers ?? 0);

        $denominator = $peak > 0 ? $peak : $views;

        return [
            'peak_viewers'   => $peak,
            'total_views'    => $views,
            'shares'         => (int) ($this->shares_count ?? 0),
            'buyers'         => $buyers,
            'first_time'     => $firstTime,
            'returning'      => $returning,
            'rating'         => $this->avg_order_rating !== null ? (float) $this->avg_order_rating : null,
            'conversion_pct' => $denominator > 0 ? round($buyers / $denominator * 100, 1) : null,
            'has_data'       => ($peak + $views + $buyers + $firstTime + $returning) > 0,
        ];
    }

    protected static function booted(): void
    {
        // Stamp status_changed_at whenever the show enters a new status (and on
        // create), so the pipeline board can show accurate time-in-status.
        static::creating(function (Show $show) {
            $show->status_changed_at ??= now();
        });

        static::updating(function (Show $show) {
            if ($show->isDirty('status')) {
                $show->status_changed_at = now();
            }
        });

        // The AuditsUpdates trait records the populated status-change audit entry.
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

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

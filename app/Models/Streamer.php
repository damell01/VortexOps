<?php

namespace App\Models;

use App\Models\Concerns\AuditsUpdates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Streamer extends Model
{
    use LogsActivity, AuditsUpdates;

    // AuditsUpdates records the real diff (all changed fields); LogsActivity's
    // empty "updated" entry is suppressed. Balance increments use the query
    // builder (no model events), so they don't create audit noise here.
    protected static array $doNotRecordEvents = ['updated'];

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget('filter:streamers');
        static::saved($bust);
        static::deleted($bust);
    }

    protected $fillable = [
        'user_id',
        'whatnot_channel_id',
        'name',
        'legal_name',
        'email',
        'phone',
        'payout_type',
        'payout_cadence',
        'payout_percentage',
        'package_rate',
        'hourly_rate',
        'custom_payout_formula',
        'pwe_rate',
        'label_rate',
        'burden_rate_type',
        'burden_rate_value',
        'include_tips',
        'adp_employee_id',
        'owner_fee_type',
        'owner_fee_value',
        'owner_fee_deduct_from_payout',
        'total_earnings_due',
        'total_earnings_paid',
        'channel_routing_rules',
        'status',
        'member_type',
        'notes',
    ];

    protected $casts = [
        'include_tips'                 => 'boolean',
        'payout_percentage'            => 'decimal:2',
        'package_rate'                 => 'decimal:2',
        'hourly_rate'                  => 'decimal:2',
        'pwe_rate'                     => 'decimal:4',
        'label_rate'                   => 'decimal:4',
        'burden_rate_value'            => 'decimal:4',
        'owner_fee_value'              => 'decimal:2',
        'owner_fee_deduct_from_payout' => 'boolean',
        'total_earnings_due'           => 'decimal:2',
        'total_earnings_paid'          => 'decimal:2',
        'channel_routing_rules'        => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Primary channel this streamer is attributed to for stats/analytics. */
    public function channel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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

    public function inventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class);
    }

    public function shows(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Show::class, 'show_streamer')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(StreamerLoan::class);
    }

    public function streamerLogEntries(): HasMany
    {
        return $this->hasMany(StreamerLogEntry::class);
    }

    public function profitSharePackets(): HasMany
    {
        return $this->hasMany(ProfitSharePacket::class);
    }

    public function outstandingBalance(): float
    {
        return max(0, (float) $this->total_earnings_due - (float) $this->total_earnings_paid);
    }

    /**
     * A performance snapshot across every show this streamer was on: how many
     * shows, total gross they drove, the margin their shows contributed, and
     * their average order rating. Margin sums each show's P&L margin; the whole
     * streamer's payouts already flow into those per-show margins.
     *
     * @return array{shows:int, gross:float, margin:float, avg_rating:?float, rated_shows:int, has_data:bool}
     */
    public function scorecard(): array
    {
        $shows = $this->shows()->get([
            'shows.id', 'shows.gross_revenue', 'shows.whatnot_net',
            'shows.tips', 'shows.avg_order_rating',
        ]);

        $gross     = 0.0;
        $margin    = 0.0;
        $ratingSum = 0.0;
        $rated     = 0;

        foreach ($shows as $show) {
            $gross  += (float) $show->gross_revenue;
            $margin += $show->profitAndLoss()['margin'];

            if ($show->avg_order_rating !== null) {
                $ratingSum += (float) $show->avg_order_rating;
                $rated++;
            }
        }

        return [
            'shows'       => $shows->count(),
            'gross'       => round($gross, 2),
            'margin'      => round($margin, 2),
            'avg_rating'  => $rated > 0 ? round($ratingSum / $rated, 2) : null,
            'rated_shows' => $rated,
            'has_data'    => $shows->isNotEmpty(),
        ];
    }

    /** Gross revenue for a single completed calendar week (Mon–Sun) starting on $weekStart. */
    private function weekRevenue(\Illuminate\Support\Carbon $weekStart): float
    {
        return (float) $this->shows()
            ->whereBetween('show_date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
            ->whereNotIn('shows.status', ['cancelled'])
            ->sum('shows.gross_revenue');
    }

    /**
     * True when this streamer's most recently completed week is down at least
     * $thresholdPct from the average of the 3 completed weeks before it — a
     * quick "worth a check-in" signal, not a hard judgment. Requires both the
     * recent week and the baseline to have real revenue, so a streamer who
     * simply didn't stream a given week (0 baseline) never false-positives.
     */
    public function isPerformanceTrendingDown(float $thresholdPct = 30.0): bool
    {
        $lastWeekStart = now()->copy()->subWeek()->startOfWeek();
        $lastWeekRevenue = $this->weekRevenue($lastWeekStart);

        if ($lastWeekRevenue <= 0) {
            return false;
        }

        $baselineRevenues = [];
        for ($i = 1; $i <= 3; $i++) {
            $baselineRevenues[] = $this->weekRevenue($lastWeekStart->copy()->subWeeks($i));
        }

        $baselineAvg = array_sum($baselineRevenues) / count($baselineRevenues);
        if ($baselineAvg <= 0) {
            return false;
        }

        $changePct = (($lastWeekRevenue - $baselineAvg) / $baselineAvg) * 100;

        return $changePct <= -$thresholdPct;
    }

    /**
     * What this person does, which is separate from how they are paid.
     *
     * Fulfillment staff carry exactly the same pay terms as streamers — the
     * payout pipeline is keyed on streamer_id and every rate column already
     * lives on this row — so the only thing that differs is the work. "Both"
     * covers somebody who streams and also packs.
     */
    public static function memberTypeLabels(): array
    {
        return [
            'streamer'    => 'Streamer',
            'fulfillment' => 'Fulfillment',
            'both'        => 'Streamer + Fulfillment',
        ];
    }

    /** People who go on camera. */
    public function scopeStreamers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('member_type', ['streamer', 'both']);
    }

    /** People who pack and ship. */
    public function scopeFulfillment(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('member_type', ['fulfillment', 'both']);
    }

    public function isFulfillment(): bool
    {
        return in_array($this->member_type, ['fulfillment', 'both'], true);
    }

    public function isStreamer(): bool
    {
        // Rows created before member_type existed are streamers; that is what
        // the table held. Treating a null as neither would hide every one of
        // them from the list they have always appeared in.
        return in_array($this->member_type ?? 'streamer', ['streamer', 'both'], true);
    }

    public static function payoutTypeLabels(): array
    {
        return [
            'profit_share'   => 'Profit Share',
            'package'        => 'Package',
            'hourly'         => 'Hourly',
            'flat_rate'      => 'Flat Rate',
            'pwe_labels'     => 'PWE + Labels',
            'hybrid'         => 'Hybrid (Hourly + Tips + Profit Share)',
            'custom_formula' => 'Custom Formula',
        ];
    }

    public static function payoutCadenceLabels(): array
    {
        return [
            'weekly'  => 'Weekly',
            'monthly' => 'Monthly',
        ];
    }

    public static function ownerFeeTypeLabels(): array
    {
        return [
            'percentage' => 'Percentage (%)',
            'flat'       => 'Flat Amount ($)',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'active'   => 'Active',
            'inactive' => 'Inactive',
            'on_leave' => 'On Leave',
        ];
    }
}

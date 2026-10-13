<?php

namespace App\Models;

use App\Models\Concerns\AuditsUpdates;
use App\Models\Concerns\ResolvesCompensationAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Streamer extends Model
{
    use LogsActivity, AuditsUpdates, ResolvesCompensationAttributes;

    protected static array $doNotRecordEvents = ['updated'];

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget('filter:streamers');
        static::saved($bust);
        static::deleted($bust);
    }

    protected $fillable = [
        'user_id', 'whatnot_channel_id', 'business_id', 'name', 'legal_name', 'email', 'phone',
        'payout_type', 'payout_cadence', 'payout_percentage', 'house_cut_percentage', 'sales_split_basis', 'package_rate', 'hourly_rate',
        'custom_payout_formula', 'pwe_rate', 'label_rate', 'burden_rate_type', 'burden_rate_value',
        'include_tips', 'adp_employee_id', 'owner_fee_type', 'owner_fee_value',
        'owner_fee_deduct_from_payout', 'total_earnings_due', 'total_earnings_paid',
        'channel_routing_rules', 'status', 'member_type', 'streamer_type', 'pay_rate_effective_at', 'compensation_override_fields', 'notes',
    ];

    protected $casts = [
        'include_tips' => 'boolean', 'payout_percentage' => 'decimal:2', 'house_cut_percentage' => 'decimal:2', 'package_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2', 'pwe_rate' => 'decimal:4', 'label_rate' => 'decimal:4',
        'burden_rate_value' => 'decimal:4', 'owner_fee_value' => 'decimal:2',
        'owner_fee_deduct_from_payout' => 'boolean', 'total_earnings_due' => 'decimal:2',
        'total_earnings_paid' => 'decimal:2', 'channel_routing_rules' => 'array',
        'compensation_override_fields' => 'array', 'pay_rate_effective_at' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions { return LogOptions::defaults()->logAll()->logOnlyDirty(); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function aliases(): HasMany { return $this->hasMany(StreamerAlias::class); }
    public function channel(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id'); }

    public function business(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Business::class); }

    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Support\ChannelContext::isScoped() ? $query->where('whatnot_channel_id', \App\Support\ChannelContext::currentId()) : $query;
    }

    /** Limit to the business payroll is being run for (App\Support\BusinessContext), if any. */
    public function scopeInBusinessContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Support\BusinessContext::isScoped()
            ? $query->where($query->qualifyColumn('business_id'), \App\Support\BusinessContext::currentId())
            : $query;
    }

    public function inventoryLocations(): HasMany
    {
        $relation = $this->hasMany(InventoryLocation::class);
        $defaultWarehouseId = InventoryLocation::defaultReceivingId();

        // Step-by-step rollout: a streamer filing a show report can consume
        // directly from shared Main Storage / the configured receiving warehouse.
        // Existing personal streamer locations still remain valid candidates.
        return $relation->orWhere(function ($query) use ($defaultWarehouseId) {
            $query->where(function ($warehouse) {
                $warehouse->where('type', 'main_storage')->where('status', 'active');
            });

            if ($defaultWarehouseId) {
                $query->orWhere('inventory_locations.id', $defaultWarehouseId);
            }
        });
    }

    public function shows(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Show::class, 'show_streamer')->withPivot('is_primary')->withTimestamps();
    }

    public function payouts(): HasMany { return $this->hasMany(Payout::class); }
    public function loans(): HasMany { return $this->hasMany(StreamerLoan::class); }
    public function streamerLogEntries(): HasMany { return $this->hasMany(StreamerLogEntry::class); }
    public function profitSharePackets(): HasMany { return $this->hasMany(ProfitSharePacket::class); }

    public function outstandingBalance(): float { return max(0, (float) $this->total_earnings_due - (float) $this->total_earnings_paid); }
    public function effectiveCompensation(): array { return \App\Support\PaymentStructure::resolve($this); }
    public function compensationValue(string $field, mixed $fallback = null): mixed { return \App\Support\PaymentStructure::effective($this, $field, $fallback); }

    public function scorecard(): array
    {
        $shows = $this->shows()->get(['shows.id', 'shows.gross_revenue', 'shows.whatnot_net', 'shows.tips', 'shows.avg_order_rating']);
        $gross = 0.0; $margin = 0.0; $ratingSum = 0.0; $rated = 0;
        foreach ($shows as $show) {
            $gross += (float) $show->gross_revenue; $margin += $show->profitAndLoss()['margin'];
            if ($show->avg_order_rating !== null) { $ratingSum += (float) $show->avg_order_rating; $rated++; }
        }
        return ['shows' => $shows->count(), 'gross' => round($gross, 2), 'margin' => round($margin, 2),
            'avg_rating' => $rated > 0 ? round($ratingSum / $rated, 2) : null, 'rated_shows' => $rated, 'has_data' => $shows->isNotEmpty()];
    }

    private function weekRevenue(\Illuminate\Support\Carbon $weekStart): float
    {
        return (float) $this->shows()->whereBetween('show_date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
            ->whereNotIn('shows.status', ['cancelled'])->sum('shows.gross_revenue');
    }

    /**
     * Last week's gross against the average of the three weeks before it.
     *
     * @return array{last_week: float, baseline: float, change_pct: float}|null null without both figures
     */
    public function performanceTrend(): ?array
    {
        $lastWeekStart = now()->copy()->subWeek()->startOfWeek(); $lastWeekRevenue = $this->weekRevenue($lastWeekStart);
        if ($lastWeekRevenue <= 0) return null;
        $baselineRevenues = [];
        for ($i = 1; $i <= 3; $i++) $baselineRevenues[] = $this->weekRevenue($lastWeekStart->copy()->subWeeks($i));
        $baselineAvg = array_sum($baselineRevenues) / count($baselineRevenues);
        if ($baselineAvg <= 0) return null;
        return ['last_week' => $lastWeekRevenue, 'baseline' => $baselineAvg, 'change_pct' => (($lastWeekRevenue - $baselineAvg) / $baselineAvg) * 100];
    }

    public function isPerformanceTrendingDown(float $thresholdPct = 30.0): bool
    {
        $trend = $this->performanceTrend();
        return $trend !== null && $trend['change_pct'] <= -$thresholdPct;
    }

    public static function memberTypeLabels(): array { return ['streamer' => 'Streamer', 'fulfillment' => 'Fulfillment']; }
    public static function streamerTypeLabels(): array { return ['in_house' => 'In-House', 'remote' => 'Remote']; }
    public function isRemote(): bool { return $this->streamer_type === 'remote'; }
    public function isInHouse(): bool { return ! $this->isRemote(); }

    /**
     * The hourly rate weekly pay adds on top of an in-house streamer's share,
     * or null. Only rates set the current way count (team default, the Pay
     * section, or a Rates & Adjustments override): a profile still on its old
     * per-person columns may hold a stale hourly rate that never paid anything.
     */
    public function paidHourlyRate(): ?float
    {
        if ($this->isFulfillment() || ! $this->isInHouse()) {
            return null;
        }
        $resolved = \App\Support\PaymentStructure::resolve($this);
        if ($resolved['legacy'] || ! in_array($resolved['effective']['payout_type'] ?? null, ['profit_share', 'custom_formula'], true)) {
            return null;
        }
        $rate = (float) ($resolved['effective']['hourly_rate'] ?? 0);

        return $rate > 0 ? $rate : null;
    }
    public function scopeStreamers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder { return $query->where(fn ($q) => $q->where('member_type', 'streamer')->orWhereNull('member_type')); }
    public function scopeFulfillment(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder { return $query->where('member_type', 'fulfillment'); }
    public function isFulfillment(): bool { return $this->member_type === 'fulfillment'; }
    public function isStreamer(): bool { return ($this->member_type ?? 'streamer') === 'streamer'; }

    public static function payoutTypeLabels(): array
    {
        return ['profit_share' => 'Profit Share', 'package' => 'Package', 'hourly' => 'Hourly', 'flat_rate' => 'Flat Rate',
            'pwe_labels' => 'PWE + Labels', 'hybrid' => 'Hybrid (Hourly + Tips + Profit Share)', 'custom_formula' => 'Custom Formula',
            'sales_split' => 'Sales Split (house keeps a %)'];
    }

    public static function payoutCadenceLabels(): array { return ['weekly' => 'Weekly', 'monthly' => 'Monthly']; }
    public static function salesSplitBasisLabels(): array { return ['net' => 'Estimated net earnings', 'gross' => 'Gross sales']; }
    public static function ownerFeeTypeLabels(): array { return ['percentage' => 'Percentage (%)', 'flat' => 'Flat Amount ($)']; }
    public static function statusLabels(): array { return ['active' => 'Active', 'inactive' => 'Inactive', 'on_leave' => 'On Leave']; }
}

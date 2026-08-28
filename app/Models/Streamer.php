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
        'compensation_override_fields',
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
        'compensation_override_fields' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

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

    /** @return array{member_type:string,structure:string,defaults:array,overrides:array,effective:array,legacy:bool,version:string} */
    public function effectiveCompensation(): array
    {
        return \App\Support\PaymentStructure::resolve($this);
    }

    public function compensationValue(string $field, mixed $fallback = null): mixed
    {
        return \App\Support\PaymentStructure::effective($this, $field, $fallback);
    }

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

    private function weekRevenue(\Illuminate\Support\Carbon $weekStart): float
    {
        return (float) $this->shows()
            ->whereBetween('show_date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
            ->whereNotIn('shows.status', ['cancelled'])
            ->sum('shows.gross_revenue');
    }

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

    public static function memberTypeLabels(): array
    {
        return [
            'streamer'    => 'Streamer',
            'fulfillment' => 'Fulfillment',
        ];
    }

    public function scopeStreamers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(fn ($q) => $q->where('member_type', 'streamer')->orWhereNull('member_type'));
    }

    public function scopeFulfillment(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('member_type', 'fulfillment');
    }

    public function isFulfillment(): bool
    {
        return $this->member_type === 'fulfillment';
    }

    public function isStreamer(): bool
    {
        return ($this->member_type ?? 'streamer') === 'streamer';
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

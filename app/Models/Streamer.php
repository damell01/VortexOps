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
        'name',
        'legal_name',
        'email',
        'phone',
        'payout_type',
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

    public function outstandingBalance(): float
    {
        return max(0, (float) $this->total_earnings_due - (float) $this->total_earnings_paid);
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Streamer extends Model
{
    use LogsActivity;

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
        'include_tips',
        'adp_employee_id',
        'owner_fee_type',
        'owner_fee_value',
        'owner_fee_deduct_from_payout',
        'status',
        'notes',
    ];

    protected $casts = [
        'include_tips'                 => 'boolean',
        'payout_percentage'            => 'decimal:2',
        'package_rate'                 => 'decimal:2',
        'hourly_rate'                  => 'decimal:2',
        'owner_fee_value'              => 'decimal:2',
        'owner_fee_deduct_from_payout' => 'boolean',
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

    public static function payoutTypeLabels(): array
    {
        return [
            'profit_share' => 'Profit Share',
            'package'      => 'Package',
            'hourly'       => 'Hourly',
            'flat_rate'    => 'Flat Rate',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Streamer extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'legal_name',
        'email',
        'phone',
        'payout_type',
        'payout_percentage',
        'package_rate',
        'hourly_rate',
        'include_tips',
        'adp_employee_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'include_tips' => 'boolean',
        'payout_percentage' => 'decimal:2',
        'package_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function inventoryLocations(): HasMany
    {
        return $this->hasMany(InventoryLocation::class);
    }

    public static function payoutTypeLabels(): array
    {
        return [
            'profit_share' => 'Profit Share',
            'package' => 'Package',
            'hourly' => 'Hourly',
            'flat_rate' => 'Flat Rate',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'on_leave' => 'On Leave',
        ];
    }
}

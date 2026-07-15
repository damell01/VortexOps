<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Vendor extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'phone',
        'website',
        'account_number',
        'lead_time_days',
        'status',
        'notes',
    ];

    protected $casts = [
        'lead_time_days' => 'integer',
    ];

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget('filter:vendors');
        static::saved($bust);
        static::deleted($bust);
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'preferred_vendor_id');
    }

    public static function activeOptions(): array
    {
        return cache()->remember('filter:vendors', 300, fn () =>
            static::where('status', 'active')->orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    public static function statusLabels(): array
    {
        return [
            'active'   => 'Active',
            'inactive' => 'Inactive',
        ];
    }
}

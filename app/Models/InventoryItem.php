<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class InventoryItem extends Model
{
    use LogsActivity;

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget('filter:item_categories');
        static::saved($bust);
        static::deleted($bust);
    }

    protected $fillable = [
        'sku',
        'name',
        'category',
        'description',
        'unit_cost',
        'average_cost',
        'total_units_received',
        'reorder_level',
        'is_active',
        'preferred_vendor_id',
        'notes',
    ];

    protected $casts = [
        'unit_cost'           => 'decimal:2',
        'average_cost'        => 'decimal:4',
        'total_units_received' => 'decimal:2',
        'is_active'           => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'preferred_vendor_id');
    }

    public function palletLines(): HasMany
    {
        return $this->hasMany(PalletLine::class);
    }

    public function effectiveCost(): float
    {
        $avg = (float) $this->average_cost;
        return $avg > 0 ? $avg : (float) $this->unit_cost;
    }

    public function totalQuantity(): float
    {
        if ($this->relationLoaded('stock')) {
            return (float) $this->stock->sum('quantity');
        }

        return (float) $this->stock()->sum('quantity');
    }

    public function isLowStock(): bool
    {
        if ($this->reorder_level === null) {
            return false;
        }

        return $this->totalQuantity() <= $this->reorder_level;
    }
}

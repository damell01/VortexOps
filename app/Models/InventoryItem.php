<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'barcode',
        'name',
        'category',
        'description',
        'unit_cost',
        'seller_unit_cost',
        'shipping_unit_cost',
        'other_unit_fees',
        'average_unit_cost',
        'cost_metadata',
        'reorder_level',
        'is_active',
        'notes',
        'cost_notes',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'seller_unit_cost' => 'decimal:2',
        'shipping_unit_cost' => 'decimal:2',
        'other_unit_fees' => 'decimal:2',
        'average_unit_cost' => 'decimal:2',
        'cost_metadata' => 'array',
        'is_active' => 'boolean',
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

    public function containers(): HasMany
    {
        return $this->hasMany(InventoryContainer::class);
    }

    protected function landedUnitCost(): Attribute
    {
        return Attribute::get(function (): float {
            return (float) $this->seller_unit_cost
                + (float) $this->shipping_unit_cost
                + (float) $this->other_unit_fees;
        });
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

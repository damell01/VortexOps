<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class InventoryItem extends Model
{
    use LogsActivity, SoftDeletes;

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

    public static function findByScan(string $code): ?self
    {
        return static::where('barcode', $code)->orWhere('sku', $code)->first();
    }

    /**
     * Suggest inventory items based on a description, using show deduction history.
     *
     * Queries DeductionRequestLine.raw_description for lines that contain the
     * significant words from the given description and returns the most-frequently
     * matched inventory items (ranked by how many show lines mention them).
     *
     * @return array<int, string>  id → name
     */
    public static function suggestForDescription(string $description, int $limit = 8): array
    {
        $words = collect(explode(' ', preg_replace('/[^a-zA-Z0-9\s]/', '', $description)))
            ->map('strtolower')
            ->filter(fn ($w) => strlen($w) >= 3)
            ->unique()
            ->values();

        if ($words->isEmpty()) {
            return [];
        }

        $ids = \App\Models\DeductionRequestLine::query()
            ->selectRaw('inventory_item_id, COUNT(*) as freq')
            ->whereNotNull('inventory_item_id')
            ->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('raw_description', 'like', "%{$word}%");
                }
            })
            ->groupBy('inventory_item_id')
            ->orderByDesc('freq')
            ->limit($limit)
            ->pluck('inventory_item_id');

        if ($ids->isEmpty()) {
            return [];
        }

        return static::whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
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

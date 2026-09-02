<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'products';

    protected static function booted(): void
    {
        $bust = fn () => Cache::forget('filter:item_categories');
        static::saved($bust);
        static::deleted($bust);
    }

    protected $fillable = [
        // Core identity
        'sku',
        'barcode',
        'name',
        'upc',
        // Card-specific fields (all nullable — populated gradually)
        'brand',
        'sport',
        'year',
        'set_name',
        'product_type',
        'configuration',
        'manufacturer',
        // General
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
        'unit_cost'            => 'decimal:2',
        'average_cost'         => 'decimal:4',
        'total_units_received' => 'decimal:2',
        'is_active'            => 'boolean',
        'year'                 => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(ProductIdentity::class);
    }

    public function palletLines(): HasMany
    {
        return $this->hasMany(PalletLine::class, 'inventory_item_id');
    }

    /** Sold order lines mapped to this product (used for margin / sell-through). */
    public function orders(): HasMany
    {
        return $this->hasMany(\App\Models\WhatnotShowOrder::class, 'inventory_item_id');
    }

    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'preferred_vendor_id');
    }

    /** The embedding vector, in its own table so it never bloats product reads. */
    public function embedding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        // Explicit FK: the InventoryItem alias would otherwise infer inventory_item_id.
        return $this->hasOne(ProductEmbedding::class, 'product_id');
    }

    // ── Lookups ────────────────────────────────────────────────────────────────

    /**
     * Find by barcode, SKU, or UPC — used by the scanner.
     */
    public static function findByScan(string $code): ?static
    {
        $direct = static::where('barcode', $code)
            ->orWhere('sku', $code)
            ->orWhere('upc', $code)
            ->first();

        if ($direct) {
            return $direct;
        }

        // Fall through to the identity table for vendor SKUs / aliases / extra
        // barcodes. ProductIdentity::product() is a plain belongsTo(Product::class)
        // — not late-static-bound — so it always hands back a Product instance.
        // Re-fetch through static::find() instead of using that relation directly,
        // or InventoryItem::findByScan() would return a Product where its ?static
        // return type demands an InventoryItem, which is a TypeError.
        $identity = ProductIdentity::where('value', $code)
            ->whereIn('type', ['barcode', 'upc', 'vendor_sku', 'manufacturer_sku'])
            ->first();

        return $identity ? static::find($identity->product_id) : null;
    }

    /**
     * Suggest products based on a description (deduction history ranking).
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

        $ids = DeductionRequestLine::query()
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

    // ── Computed ───────────────────────────────────────────────────────────────

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

    /**
     * Human-readable card label, e.g. "2025 Topps Chrome Baseball Hobby Box".
     */
    public function cardLabel(): string
    {
        return implode(' ', array_filter([
            $this->year,
            $this->manufacturer ?? $this->brand,
            $this->set_name,
            $this->sport,
            $this->product_type,
        ])) ?: $this->name;
    }

    /**
     * Return FIFO-ordered active lots with remaining stock.
     */
    public function activeLotsFifo(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->lots()
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->get();
    }
}

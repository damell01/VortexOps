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
        return static::where('barcode', $code)
            ->orWhere('sku', $code)
            ->orWhere('upc', $code)
            ->first()
            // fall through to identity table for vendor SKUs / aliases
            ?? ProductIdentity::where('value', $code)
                ->whereIn('type', ['barcode', 'upc', 'vendor_sku', 'manufacturer_sku'])
                ->with('product')
                ->first()
                ?->product;
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
     * Suggested reorder quantity from trailing sales velocity, projected across
     * the vendor's lead time plus a safety-stock buffer, minus what's already on
     * hand. Null when there's no recent sales history to estimate a rate from, or
     * when on-hand stock already covers projected demand — a forecast built on
     * zero data is worse than no forecast.
     */
    public function suggestedReorderQuantity(int $trailingDays = 30, int $safetyDays = 7, int $defaultLeadTimeDays = 14): ?int
    {
        $trailingUnitsSold = (float) $this->orders()
            ->where('show_date', '>=', now()->subDays($trailingDays)->toDateString())
            ->sum('quantity');

        $velocity = $trailingUnitsSold / $trailingDays;
        if ($velocity <= 0) {
            return null;
        }

        $leadTimeDays = $this->relationLoaded('preferredVendor')
            ? ($this->preferredVendor?->lead_time_days ?? $defaultLeadTimeDays)
            : ($this->preferredVendor()->value('lead_time_days') ?? $defaultLeadTimeDays);

        $reorderPoint = $velocity * ($leadTimeDays + $safetyDays);
        $onHand       = $this->totalQuantity();

        return $onHand < $reorderPoint ? (int) ceil($reorderPoint - $onHand) : null;
    }

    /** Most recent time stock was added to this item (opening or return), or null if never. */
    public function lastRestockedAt(): ?\Illuminate\Support\Carbon
    {
        $max = $this->relationLoaded('movements')
            ? $this->movements->whereIn('movement_type', ['opening', 'return'])->max('created_at')
            : $this->movements()->whereIn('movement_type', ['opening', 'return'])->max('created_at');

        return $max ? \Illuminate\Support\Carbon::parse($max) : null;
    }

    /**
     * True when this item currently has zero stock everywhere and hasn't been
     * restocked in at least $days days (or ever) — a signal that a sale mapped
     * to it is likely a mis-mapping rather than a real transaction.
     */
    public function hasBeenOutOfStockFor(int $days = 14): bool
    {
        if ($this->totalQuantity() > 0) {
            return false;
        }

        $lastRestock = $this->lastRestockedAt();

        return $lastRestock === null || $lastRestock->lt(now()->subDays($days));
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

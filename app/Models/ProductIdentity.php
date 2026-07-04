<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductIdentity extends Model
{
    protected $fillable = [
        'product_id',
        'vendor_id',
        'type',
        'value',
        'embedding',
        'times_confirmed',
        'last_confirmed_at',
        'auto_confidence',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = [
        'embedding'          => 'array',
        'times_confirmed'    => 'integer',
        'auto_confidence'    => 'float',
        'last_confirmed_at'  => 'datetime',
        'confirmed_at'       => 'datetime',
    ];

    // ── Identity types ─────────────────────────────────────────────────────────

    const TYPE_UPC              = 'upc';
    const TYPE_BARCODE          = 'barcode';
    const TYPE_VENDOR_SKU       = 'vendor_sku';
    const TYPE_MANUFACTURER_SKU = 'manufacturer_sku';
    const TYPE_ALIAS            = 'alias';
    const TYPE_SEARCH_TOKEN     = 'search_token';

    const SCANNABLE_TYPES = [self::TYPE_UPC, self::TYPE_BARCODE, self::TYPE_VENDOR_SKU, self::TYPE_MANUFACTURER_SKU];
    const TEXT_TYPES      = [self::TYPE_ALIAS, self::TYPE_SEARCH_TOKEN];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Record a human confirmation — bumps count and updates confidence toward 1.
     */
    public function recordConfirmation(int $userId): void
    {
        $this->increment('times_confirmed');
        $this->update([
            'last_confirmed_at' => now(),
            'confirmed_by'      => $userId,
            'confirmed_at'      => now(),
            // Confidence approaches 1.0 asymptotically after several confirmations
            'auto_confidence'   => min(1.0, 0.95 + ($this->times_confirmed * 0.01)),
        ]);
    }

    /**
     * Find an existing alias identity for a vendor description.
     */
    public static function findAlias(string $value, ?int $vendorId = null): ?static
    {
        return static::where('type', self::TYPE_ALIAS)
            ->where('value', $value)
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->orderByDesc('times_confirmed')
            ->first();
    }

    /**
     * Find or create an alias identity record.
     */
    public static function recordAlias(
        Product $product,
        string $value,
        float $confidence,
        string $stage,
        ?int $vendorId = null
    ): static {
        $identity = static::firstOrCreate(
            [
                'product_id' => $product->id,
                'vendor_id'  => $vendorId,
                'type'       => self::TYPE_ALIAS,
                'value'      => $value,
            ],
            ['auto_confidence' => $confidence]
        );

        // Update confidence if the new match scored higher
        if ($confidence > (float) $identity->auto_confidence) {
            $identity->update(['auto_confidence' => $confidence]);
        }

        return $identity;
    }
}

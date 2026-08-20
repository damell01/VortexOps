<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PalletLine extends Model
{
    protected $fillable = [
        'pallet_id',
        'receiving_session_id',
        'line_number',
        'description',
        'barcode',
        'photo_path',
        'is_container',
        'vendor_description',
        'inventory_item_id',
        'match_confidence',
        'match_stage',
        'match_reasons',
        'matched_at',
        'matched_by',
        'product_identity_id',
        'case_count',
        'quantity_per_case',
        'unit_cost',
        'inventory_location_id',
        'line_status',
        'preflight_cost',
    ];

    protected $casts = [
        'is_container'      => 'boolean',
        'quantity_per_case' => 'decimal:2',
        'unit_cost'         => 'decimal:4',
        'preflight_cost'    => 'decimal:4',
        'match_confidence'  => 'float',
        'match_reasons'     => 'array',
        'matched_at'        => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $line) {
            if ($line->line_number === null && $line->pallet_id) {
                $line->line_number = (static::where('pallet_id', $line->pallet_id)->max('line_number') ?? 0) + 1;
            }
        });

        // A line staged from a name alone has no counts yet, and both columns
        // are NOT NULL — so leaving them blank threw a constraint violation
        // rather than staging anything. One case of one unit is the only
        // sensible reading of "a thing on this pallet", and it stays editable.
        static::saving(function (self $line) {
            if ($line->case_count === null || $line->case_count === '') {
                $line->case_count = 1;
            }

            if ($line->quantity_per_case === null || $line->quantity_per_case === '') {
                $line->quantity_per_case = 1;
            }

            if ($line->unit_cost === null || $line->unit_cost === '') {
                $line->unit_cost = 0;
            }
        });
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_item_id');
    }

    public function receivingSession(): BelongsTo
    {
        return $this->belongsTo(ReceivingSession::class);
    }

    public function productIdentity(): BelongsTo
    {
        return $this->belongsTo(ProductIdentity::class);
    }

    public function lot(): HasOne
    {
        return $this->hasOne(InventoryLot::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(InventoryCase::class);
    }

    public function totalQuantityExpected(): float
    {
        return (float) $this->case_count * (float) $this->quantity_per_case;
    }

    public function totalCost(): float
    {
        return round($this->totalQuantityExpected() * (float) $this->unit_cost, 2);
    }

    public function receivedCases(): int
    {
        return $this->cases()->where('status', '!=', 'expected')->count();
    }

    /** Whether a photo of the actual thing was taken while staging. */
    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    /**
     * A URL for the staging photo, or null.
     *
     * Deliberately not falling back to the brand mark the way a product does:
     * on a manifest a photo is evidence that somebody looked in the box, so
     * "no photo" has to be distinguishable from "photo of nothing in
     * particular".
     */
    public function photoUrl(): ?string
    {
        return $this->hasPhoto()
            ? \Illuminate\Support\Facades\Storage::disk(Product::IMAGE_DISK)->url($this->photo_path)
            : null;
    }

    public function isFullyMapped(): bool
    {
        return $this->inventory_item_id !== null && $this->inventory_location_id !== null;
    }
}

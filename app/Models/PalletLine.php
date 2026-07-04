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
    ];

    protected $casts = [
        'quantity_per_case' => 'decimal:2',
        'unit_cost'         => 'decimal:4',
        'match_confidence'  => 'float',
        'match_reasons'     => 'array',
        'matched_at'        => 'datetime',
    ];

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

    public function isFullyMapped(): bool
    {
        return $this->inventory_item_id !== null && $this->inventory_location_id !== null;
    }
}

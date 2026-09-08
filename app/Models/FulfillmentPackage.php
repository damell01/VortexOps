<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FulfillmentPackage extends Model
{
    protected $fillable = [
        'show_id',
        'shipment_id',
        'package_code',
        'box_number',
        'box_total',
        'buyer_username',
        'tracking_number',
        'carrier',
        'created_by',
        'packed_by',
        'status',
        'sealed_at',
        'label_printed_at',
        'shipped_at',
        'notes',
    ];

    protected $casts = [
        'box_number' => 'integer',
        'box_total' => 'integer',
        'sealed_at' => 'datetime',
        'label_printed_at' => 'datetime',
        'shipped_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (FulfillmentPackage $package): void {
            if (blank($package->package_code)) {
                $package->package_code = 'BX-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
            }
        });
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function packedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentPackageItem::class);
    }

    public function totalItems(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function getItemCount(): int
    {
        return $this->totalItems();
    }

    public function getTotalValue(): float
    {
        return (float) $this->items()
            ->with(['product', 'streamerLogItem.inventoryItem'])
            ->get()
            ->sum(function (FulfillmentPackageItem $item): float {
                $unitCost = $item->streamerLogItem?->effectiveUnitCost()
                    ?? $item->product?->effectiveCost()
                    ?? 0;

                return ((float) $item->quantity) * (float) $unitCost;
            });
    }

    public function isSealed(): bool
    {
        return $this->status === 'sealed' || $this->sealed_at !== null;
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped' && $this->shipped_at !== null;
    }
}

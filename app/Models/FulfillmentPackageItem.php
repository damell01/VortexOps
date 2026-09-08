<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentPackageItem extends Model
{
    protected $fillable = [
        'fulfillment_package_id',
        'streamer_log_item_id',
        'product_id',
        'quantity',
        'packed_by',
        'packed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'packed_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(FulfillmentPackage::class, 'fulfillment_package_id');
    }

    public function streamerLogItem(): BelongsTo
    {
        return $this->belongsTo(StreamerLogItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function packedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }

    public function getItemValue(): float
    {
        $unitCost = $this->streamerLogItem?->effectiveUnitCost()
            ?? $this->product?->effectiveCost()
            ?? 0;

        return ((float) $this->quantity) * (float) $unitCost;
    }
}

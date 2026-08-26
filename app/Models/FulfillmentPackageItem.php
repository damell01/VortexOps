<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentPackageItem extends Model
{
    protected $fillable = [
        'fulfillment_package_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(FulfillmentPackage::class, 'fulfillment_package_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getItemValue(): float
    {
        return ((float) $this->quantity) * ($this->product?->effectiveCost() ?? 0);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $fillable = [
        'show_id',
        'whatnot_order_id',
        'buyer_username',
        'created_at_whatnot',
        'item_count',
        'shipping_cost',
        'weight_oz',
        'dimensions_json',
        'status',
        'carrier',
        'tracking_number',
        'insurance_added',
        'signature_required',
        'raw_payload',
    ];

    protected $casts = [
        'created_at_whatnot' => 'datetime',
        'shipping_cost'      => 'decimal:2',
        'weight_oz'          => 'decimal:2',
        'dimensions_json'    => 'array',
        'insurance_added'    => 'boolean',
        'signature_required' => 'boolean',
        'raw_payload'        => 'array',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WhatnotShowOrder::class, 'whatnot_order_id');
    }
}

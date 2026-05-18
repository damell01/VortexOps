<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionRequest extends Model
{
    protected $fillable = [
        'whatnot_show_id',
        'show_sale_id',
        'inventory_item_id',
        'inventory_location_id',
        'quantity',
        'unit_cost',
        'status',
        'reviewed_by',
        'reviewed_at',
        'inventory_movement_id',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'quantity'    => 'decimal:2',
        'unit_cost'   => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(WhatnotShow::class, 'whatnot_show_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(ShowSale::class, 'show_sale_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    public static function statusLabels(): array
    {
        return [
            'pending'  => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'executed' => 'Executed',
        ];
    }
}

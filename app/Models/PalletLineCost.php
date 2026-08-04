<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalletLineCost extends Model
{
    protected $fillable = [
        'pallet_line_id',
        'inventory_item_id',
        'unit_cost',
        'previous_unit_cost',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'previous_unit_cost' => 'decimal:2',
    ];

    public function palletLine(): BelongsTo
    {
        return $this->belongsTo(PalletLine::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

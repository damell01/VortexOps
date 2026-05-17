<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class InventoryMovement extends Model
{
    use LogsActivity;

    protected $fillable = [
        'inventory_item_id',
        'from_location_id',
        'to_location_id',
        'quantity',
        'movement_type',
        'reason',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function movementTypeLabels(): array
    {
        return [
            'opening' => 'Opening Stock',
            'transfer' => 'Transfer',
            'adjustment' => 'Adjustment',
            'sale_deduction' => 'Sale Deduction',
            'return' => 'Return',
            'damaged' => 'Damaged',
        ];
    }
}

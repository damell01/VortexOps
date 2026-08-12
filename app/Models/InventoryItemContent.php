<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItemContent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_inventory_item_id',
        'child_inventory_item_id',
        'quantity_per_parent',
        'unit_type',
        'barcode',
        'description',
        'created_by',
    ];

    protected $casts = [
        'quantity_per_parent' => 'integer',
    ];

    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'parent_inventory_item_id');
    }

    public function childItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'child_inventory_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Find a container by its barcode (case barcode)
     */
    public static function findByBarcode(string $barcode): ?self
    {
        return static::where('barcode', $barcode)->first();
    }

    /**
     * Get display label for this relationship
     * e.g., "Case contains 20 boxes"
     */
    public function getDisplayLabel(): string
    {
        $unit = $this->unit_type ?? 'container';
        $child = $this->childItem->name ?? 'item';

        return "{$this->quantity_per_parent} × {$child} per {$unit}";
    }
}

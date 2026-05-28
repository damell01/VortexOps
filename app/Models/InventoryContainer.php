<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class InventoryContainer extends Model
{
    use LogsActivity;

    protected $fillable = [
        'inventory_item_id',
        'inventory_location_id',
        'parent_container_id',
        'container_type',
        'label',
        'barcode',
        'quantity',
        'status',
        'scanner_ready',
        'scanner_metadata',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'scanner_ready' => 'boolean',
        'scanner_metadata' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function parentContainer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_container_id');
    }

    public function childContainers(): HasMany
    {
        return $this->hasMany(self::class, 'parent_container_id');
    }

    public static function typeLabels(): array
    {
        return [
            'pallet' => 'Pallet',
            'case' => 'Case',
            'box' => 'Box',
            'unit' => 'Unit Bundle',
            'other' => 'Other',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'active' => 'Active',
            'broken_down' => 'Broken Down',
            'archived' => 'Archived',
        ];
    }

    public static function schemaReady(): bool
    {
        try {
            return Schema::hasTable('inventory_containers');
        } catch (\Throwable) {
            return false;
        }
    }
}

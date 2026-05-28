<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class InventoryLocation extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'type',
        'streamer_id',
        'status',
        'notes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function movementsFrom(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'from_location_id');
    }

    public function movementsTo(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'to_location_id');
    }

    /** All active locations as id→name array, cached 5 min. */
    public static function activeOptions(): array
    {
        return cache()->remember('inv_loc:active', 300, fn () =>
            static::where('status', 'active')->orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    /** Active locations filtered by type, cached 5 min. */
    public static function activeOptionsByType(string $type): array
    {
        return cache()->remember("inv_loc:type:{$type}", 300, fn () =>
            static::where('type', $type)->where('status', 'active')->orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    /** Bust location option caches when a location is saved or deleted. */
    protected static function booted(): void
    {
        $bust = function () {
            cache()->forget('inv_loc:active');
            foreach (['main_storage', 'streamer_inventory', 'returned', 'damaged', 'fulfillment', 'receiving', 'other'] as $type) {
                cache()->forget("inv_loc:type:{$type}");
            }
        };

        static::saved($bust);
        static::deleted($bust);
    }

    public static function typeLabels(): array
    {
        return [
            'main_storage' => 'Main Storage',
            'streamer_inventory' => 'Streamer Inventory',
            'returned' => 'Returned',
            'damaged' => 'Damaged',
            'fulfillment' => 'Fulfillment',
            'receiving' => 'Receiving',
            'other' => 'Other',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }
}

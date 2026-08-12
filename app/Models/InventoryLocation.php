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
        'whatnot_channel_id',
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

    /** Channel this location's stock is grouped under for reporting/costing. */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(WhatnotChannel::class, 'whatnot_channel_id');
    }

    /** Limit to the admin's currently active channel (App\Support\ChannelContext), if any. */
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return \App\Support\ChannelContext::isScoped()
            ? $query->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
            : $query;
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
            foreach (['main_storage', 'streamer_inventory', 'returned', 'damaged', 'fulfillment', 'other'] as $type) {
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

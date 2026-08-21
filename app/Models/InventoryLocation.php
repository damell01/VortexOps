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

    /**
     * Locations are almost always rendered with their streamer, and lazy
     * loading is disabled outside production, so several pages fataled the
     * first time they touched it. Always loading it removes a whole class of
     * LazyLoadingViolationException without hunting every call site.
     */
    protected $with = ['streamer'];

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

    /**
     * Stock at this location, for items that still exist.
     *
     * Products are soft-deleted, so their stock rows survive them — deliberately,
     * because the movement history behind those rows is the record of what
     * happened and deleting it would be worse. But the rows kept counting: an
     * item deleted from inventory went on being listed here and included in this
     * location's totals, which reads as the deletion having silently failed.
     *
     * whereHas applies the product's own soft-delete scope, so a restored item
     * comes back into the count without anything else being written.
     */
    public function stock(): HasMany
    {
        // The whereHas that used to be here is a global scope on InventoryStock
        // now, so this is every screen's behaviour rather than only this one's.
        return $this->hasMany(InventoryStock::class);
    }

    /**
     * Every stock row, including those belonging to deleted items.
     *
     * For the places that genuinely need the raw rows — reconciling history, or
     * working out what a restore would bring back.
     */
    public function allStock(): HasMany
    {
        return $this->hasMany(InventoryStock::class)
            ->withoutGlobalScope(InventoryStock::LIVE_PRODUCT_SCOPE);
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

    /**
     * Where received stock lands when nothing more specific has been said.
     *
     * A pallet is unloaded into one place and sorted afterwards, so answering
     * "which location?" for every line while holding a box is the same answer
     * typed repeatedly. Set once under Settings — call it Staging, Receiving,
     * Back Room, whatever the place is actually called.
     *
     * Falls through to the only active location when there is just one, which
     * is the case where the question has no content at all.
     */
    public static function defaultReceivingId(): ?int
    {
        $configured = Setting::get('default_receiving_location_id');

        if ($configured && static::where('id', $configured)->where('status', 'active')->exists()) {
            return (int) $configured;
        }

        $options = static::activeOptions();

        return count($options) === 1 ? (int) array_key_first($options) : null;
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class InventoryStock extends Model
{
    use LogsActivity;

    /** Name of the scope that hides stock belonging to deleted products. */
    public const LIVE_PRODUCT_SCOPE = 'liveProduct';

    protected $table = 'inventory_stock';

    protected $fillable = [
        'inventory_item_id',
        'inventory_location_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    protected $appends = [
        'quantity_on_hand',
    ];

    public function getQuantityOnHandAttribute()
    {
        return $this->quantity;
    }

    /**
     * Stock belonging to a deleted product is not stock.
     *
     * Products are soft-deleted so their movement history survives them, and
     * their stock rows survive with them — which meant a deleted item went on
     * being counted in stock levels, location totals, analytics, transfer
     * pickers and reconciliation. Deleting something and then finding it in
     * eight other places reads as the deletion not having worked.
     *
     * Applied as a global scope rather than as a filter on each of those
     * screens. There are two dozen places querying this table and the next one
     * written would have had to remember; a scope is the only version of this
     * rule that stays true. whereHas runs the product's own soft-delete scope,
     * so a restored item comes back everywhere at once with nothing else
     * written.
     *
     * The places that genuinely need every row — reconciling history, merging
     * duplicates, working out what a restore would bring back — ask for it by
     * name with withoutGlobalScope(InventoryStock::LIVE_PRODUCT_SCOPE), which
     * is deliberately harder to type than to leave alone.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(
            self::LIVE_PRODUCT_SCOPE,
            fn (\Illuminate\Database\Eloquent\Builder $query) => $query->whereHas('item'),
        );
    }

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

    /** Limit to the admin's currently active channel (App\Support\ChannelContext), if any. */
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! \App\Support\ChannelContext::isScoped()) {
            return $query;
        }

        return $query->whereHas('location', fn (\Illuminate\Database\Eloquent\Builder $q) =>
            $q->where('whatnot_channel_id', \App\Support\ChannelContext::currentId())
        );
    }
}

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
        'lot_id',
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
        return $this->belongsTo(Product::class, 'inventory_item_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class);
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

    /** Limit to the admin's currently active channel (App\Support\ChannelContext), if any. */
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! \App\Support\ChannelContext::isScoped()) {
            return $query;
        }

        $channelId = \App\Support\ChannelContext::currentId();

        return $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($channelId) {
            $q->whereHas('fromLocation', fn (\Illuminate\Database\Eloquent\Builder $q2) => $q2->where('whatnot_channel_id', $channelId))
                ->orWhereHas('toLocation', fn (\Illuminate\Database\Eloquent\Builder $q2) => $q2->where('whatnot_channel_id', $channelId));
        });
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

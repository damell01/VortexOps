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
        'quantity_before',
        'quantity_after',
        'unit_cost',
        'movement_type',
        'reason',
        'reference_type',
        'reference_id',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    /**
     * How this movement changed the stock, with its sign.
     *
     * `quantity` is stored as an absolute value, so reading it alone shows a
     * reduction as a positive number — which is what made the history claim a
     * removal was an addition. The before and after are recorded now, so for
     * anything written since this became the arithmetic it looks like:
     * new minus previous, and nothing has to be inferred.
     *
     * Rows written before that fall back to the direction encoded in the
     * location columns, which is the best available answer for them: stock
     * arriving somewhere is positive, stock leaving is negative. A transfer has
     * both, and nets to zero across the item — the per-location view is the one
     * that has a sign, so this reports the movement's own quantity for it and
     * leaves the reading to the caller that knows which location it is showing.
     */
    public function signedChange(): float
    {
        if ($this->quantity_before !== null && $this->quantity_after !== null) {
            return (float) $this->quantity_after - (float) $this->quantity_before;
        }

        $quantity = abs((float) $this->quantity);

        // Both set: a transfer. It removes from one place and adds to another,
        // so it has no single sign at item level.
        if ($this->from_location_id && $this->to_location_id) {
            return $quantity;
        }

        return $this->from_location_id ? -$quantity : $quantity;
    }

    /** Whether the change is a real reduction, for anything that colours a row. */
    public function isDecrease(): bool
    {
        return $this->signedChange() < 0;
    }

    /** "+5" / "-3" / "0", formatted once so no view has to decide the sign. */
    public function changeLabel(int $decimals = 0): string
    {
        $change = $this->signedChange();

        if ($this->from_location_id && $this->to_location_id && $this->quantity_before === null) {
            return number_format(abs($change), $decimals);
        }

        return ($change > 0 ? '+' : ($change < 0 ? '-' : ''))
            . number_format(abs($change), $decimals);
    }

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

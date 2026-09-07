<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single item a streamer reported for a show.
 *
 * inventory_item_id is nullable on purpose: a streamer can report something
 * that is not in the catalogue yet. Those rows are surfaced as unmatched for
 * admin reconciliation instead of silently disappearing.
 */
class StreamerLogItem extends Model
{
    use HasFactory;

    public const FULFILLMENT_PENDING = 'pending';
    public const FULFILLMENT_FULFILLED = 'fulfilled';
    public const FULFILLMENT_NOT_FULFILLED = 'not_fulfilled';

    public const DISPOSITIONS = [
        'sold' => 'Sold',
        'giveaway' => 'Giveaway',
        'promo' => 'Promo / Bonus',
        'other' => 'Other',
    ];

    protected $fillable = [
        'streamer_log_entry_id',
        'inventory_item_id',
        'item_name',
        'quantity',
        'packed_quantity',
        'disposition',
        'unit_cost',
        'inventory_location_id',
        'deducted_quantity',
        'notes',
        'fulfillment_status',
        'fulfillment_note',
        'fulfilled_by',
        'fulfilled_at',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'packed_quantity'   => 'integer',
        'deducted_quantity' => 'integer',
        'unit_cost'         => 'decimal:2',
        'fulfilled_at'      => 'datetime',
    ];

    public function logEntry(): BelongsTo
    {
        return $this->belongsTo(StreamerLogEntry::class, 'streamer_log_entry_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function packageItems(): HasMany
    {
        return $this->hasMany(FulfillmentPackageItem::class);
    }

    public function fulfillmentStatus(): string
    {
        if ((int) $this->packed_quantity >= (int) $this->quantity && (int) $this->quantity > 0) {
            return self::FULFILLMENT_FULFILLED;
        }

        return $this->fulfillment_status ?: self::FULFILLMENT_PENDING;
    }

    public function isFulfillmentReviewed(): bool
    {
        return in_array($this->fulfillmentStatus(), [
            self::FULFILLMENT_FULFILLED,
            self::FULFILLMENT_NOT_FULFILLED,
        ], true);
    }

    public function remainingToPack(): int
    {
        return max(0, (int) $this->quantity - (int) $this->packed_quantity);
    }

    public function packingProgressLabel(): string
    {
        return min((int) $this->packed_quantity, (int) $this->quantity) . '/' . (int) $this->quantity . ' packed';
    }

    public static function fulfillmentStatusLabels(): array
    {
        return [
            self::FULFILLMENT_PENDING => 'Packing',
            self::FULFILLMENT_FULFILLED => 'Packaged',
            self::FULFILLMENT_NOT_FULFILLED => 'Issue',
        ];
    }

    public function effectiveUnitCost(): float
    {
        $typed = (float) ($this->unit_cost ?? 0);

        if ($typed > 0) {
            return $typed;
        }

        if ($this->inventory_item_id === null) {
            return 0.0;
        }

        $this->loadMissing('inventoryItem');

        return (float) ($this->inventoryItem?->effectiveCost() ?? 0.0);
    }

    public function costIsFromInventory(): bool
    {
        return $this->inventory_item_id !== null && (float) ($this->unit_cost ?? 0) <= 0;
    }

    public function getTotalCostAttribute(): float
    {
        return round($this->effectiveUnitCost() * (int) $this->quantity, 2);
    }

    public function isMatched(): bool
    {
        return $this->inventory_item_id !== null;
    }

    public function dispositionLabel(): string
    {
        return self::DISPOSITIONS[$this->disposition ?? 'sold'] ?? ucfirst((string) $this->disposition);
    }
}

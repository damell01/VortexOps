<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function fulfillmentStatus(): string
    {
        return $this->fulfillment_status ?: self::FULFILLMENT_PENDING;
    }

    public function isFulfillmentReviewed(): bool
    {
        return in_array($this->fulfillmentStatus(), [
            self::FULFILLMENT_FULFILLED,
            self::FULFILLMENT_NOT_FULFILLED,
        ], true);
    }

    public static function fulfillmentStatusLabels(): array
    {
        return [
            self::FULFILLMENT_PENDING => 'Pending',
            self::FULFILLMENT_FULFILLED => 'Fulfilled',
            self::FULFILLMENT_NOT_FULFILLED => 'Not Fulfilled',
        ];
    }

    /**
     * What one of these costs the business.
     *
     * The catalogue is the source, not the keyboard: a matched line leaves
     * unit_cost null and reads the item's effectiveCost() — the same figure the
     * item screen, the tables and the value snapshots quote, which is the
     * received weighted average once receiving has earned one and the list cost
     * until then. Costs used to be stamped onto the line from average_cost at
     * the moment it was added, so an item nobody had received yet stamped 0.00
     * and the show's product cost — the number the profit share is calculated
     * from — read as if the inventory had been free.
     *
     * A figure typed into the line still wins, because sometimes the person who
     * ran the show knows something the catalogue does not. Zero is read as "no
     * override" rather than "free": it is what the old stamping left behind on
     * every un-received item, and a matched line that genuinely cost nothing is
     * rare enough to be worth the trade.
     */
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

    /** Whether this line's cost is the catalogue's or somebody's correction. */
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

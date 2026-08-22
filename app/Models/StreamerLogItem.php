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
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'deducted_quantity' => 'integer',
        'unit_cost'         => 'decimal:2',
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

    public function getTotalCostAttribute(): float
    {
        return (float) ($this->unit_cost ?? 0) * (int) $this->quantity;
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

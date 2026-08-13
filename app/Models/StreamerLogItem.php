<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single item a streamer logged as sold during a show.
 *
 * inventory_item_id is nullable on purpose: a streamer can log something that
 * is not in the catalogue yet. Those rows are excluded from stock deduction
 * and surfaced as unmatched rather than silently ignored.
 */
class StreamerLogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'streamer_log_entry_id',
        'inventory_item_id',
        'item_name',
        'quantity',
        'unit_cost',
        'inventory_location_id',
        'deducted_quantity',
        'notes',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'deducted_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
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

    /** Line total, used for the log's product cost. */
    public function getTotalCostAttribute(): float
    {
        return (float) ($this->unit_cost ?? 0) * (int) $this->quantity;
    }

    public function isMatched(): bool
    {
        return $this->inventory_item_id !== null;
    }
}

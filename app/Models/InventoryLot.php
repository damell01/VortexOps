<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLot extends Model
{
    const SOURCE_RECEIVED  = 'received';
    const SOURCE_SYNTHETIC = 'synthetic';
    const SOURCE_ADJUSTMENT = 'adjustment';

    const STATUS_ACTIVE     = 'active';
    const STATUS_DEPLETED   = 'depleted';
    const STATUS_QUARANTINED = 'quarantined';

    protected $fillable = [
        'product_id',
        'pallet_line_id',
        'receiving_session_id',
        'received_by',
        'quantity',
        'unit_cost',
        'remaining_quantity',
        'supplier_invoice',
        'purchase_order',
        'vendor_sku',
        'manufacturer_sku',
        'source',
        'status',
        'received_at',
        'expires_at',
    ];

    protected $casts = [
        'quantity'           => 'decimal:2',
        'unit_cost'          => 'decimal:4',
        'remaining_quantity' => 'decimal:2',
        'received_at'        => 'datetime',
        'expires_at'         => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function palletLine(): BelongsTo
    {
        return $this->belongsTo(PalletLine::class);
    }

    public function receivingSession(): BelongsTo
    {
        return $this->belongsTo(ReceivingSession::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'lot_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function totalCost(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost, 2);
    }

    public function isDepleted(): bool
    {
        return (float) $this->remaining_quantity <= 0;
    }

    /**
     * Deduct qty from this lot, marking depleted when exhausted.
     * Returns the actual quantity deducted (may be less than requested if lot runs short).
     */
    public function deduct(float $qty): float
    {
        $available = (float) $this->remaining_quantity;
        $deducted  = min($qty, $available);

        $newRemaining = max(0, $available - $deducted);

        $this->update([
            'remaining_quantity' => $newRemaining,
            'status'             => $newRemaining <= 0 ? self::STATUS_DEPLETED : self::STATUS_ACTIVE,
        ]);

        return $deducted;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class InventoryMovement extends Model
{
    use LogsActivity;

    protected $fillable = [
        'inventory_item_id', 'lot_id', 'from_location_id', 'to_location_id',
        'quantity', 'quantity_before', 'quantity_after', 'unit_cost', 'movement_type',
        'reason', 'reference_type', 'reference_id', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Receiving one pallet line case-by-case used to write one audit row per
        // case, so a 12-case receipt appeared as twelve identical +1 entries.
        // Keep the audit trail but collapse that one business event into a
        // single movement whose quantity grows as more cases are received.
        static::creating(function (InventoryMovement $movement): bool {
            if ($movement->movement_type !== 'opening'
                || ! str_starts_with((string) $movement->reason, 'Received via pallet #')) {
                return true;
            }

            $existing = static::query()
                ->where('inventory_item_id', $movement->inventory_item_id)
                ->where('movement_type', 'opening')
                ->where('reason', $movement->reason)
                ->where(function ($q) use ($movement) {
                    $movement->from_location_id === null
                        ? $q->whereNull('from_location_id')
                        : $q->where('from_location_id', $movement->from_location_id);
                })
                ->where(function ($q) use ($movement) {
                    $movement->to_location_id === null
                        ? $q->whereNull('to_location_id')
                        : $q->where('to_location_id', $movement->to_location_id);
                })
                ->where(function ($q) use ($movement) {
                    $movement->created_by === null
                        ? $q->whereNull('created_by')
                        : $q->where('created_by', $movement->created_by);
                })
                ->latest('id')
                ->first();

            if (! $existing) {
                return true;
            }

            $existing->quantity = (float) $existing->quantity + (float) $movement->quantity;
            if ($movement->unit_cost !== null) $existing->unit_cost = $movement->unit_cost;
            $existing->updated_at = $movement->created_at ?? now();
            $existing->saveQuietly();

            return false;
        });
    }

    public function signedChange(): float
    {
        if ($this->quantity_before !== null && $this->quantity_after !== null) {
            return (float) $this->quantity_after - (float) $this->quantity_before;
        }

        $quantity = abs((float) $this->quantity);
        if ($this->from_location_id && $this->to_location_id) return $quantity;
        return $this->from_location_id ? -$quantity : $quantity;
    }

    public function isDecrease(): bool { return $this->signedChange() < 0; }

    public function changeLabel(int $decimals = 0): string
    {
        $change = $this->signedChange();
        if ($this->from_location_id && $this->to_location_id && $this->quantity_before === null) {
            return number_format(abs($change), $decimals);
        }

        return ($change > 0 ? '+' : ($change < 0 ? '-' : '')) . number_format(abs($change), $decimals);
    }

    public function getActivitylogOptions(): LogOptions { return LogOptions::defaults()->logAll(); }
    public function item(): BelongsTo { return $this->belongsTo(Product::class, 'inventory_item_id'); }
    public function lot(): BelongsTo { return $this->belongsTo(InventoryLot::class); }
    public function fromLocation(): BelongsTo { return $this->belongsTo(InventoryLocation::class, 'from_location_id'); }
    public function toLocation(): BelongsTo { return $this->belongsTo(InventoryLocation::class, 'to_location_id'); }
    public function createdByUser(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (! \App\Support\ChannelContext::isScoped()) return $query;

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
            'adjustment' => 'Inventory Correction',
            'sale_deduction' => 'Sale Deduction',
            'show_sale' => 'Show Sale',
            'giveaway' => 'Giveaway',
            'promo' => 'Promo / Bonus',
            'show_other' => 'Show Other',
            'show_reversal' => 'Show Reversal',
            'internal_use' => 'Internal Use',
            'loss' => 'Lost / Missing',
            'return' => 'Return',
            'damaged' => 'Damaged',
            'breakdown' => 'Container Breakdown',
        ];
    }
}

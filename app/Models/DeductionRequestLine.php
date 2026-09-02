<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionRequestLine extends Model
{
    public const FULFILLMENT_PENDING = 'pending';
    public const FULFILLMENT_FULFILLED = 'fulfilled';
    public const FULFILLMENT_NOT_FULFILLED = 'not_fulfilled';

    protected $fillable = [
        'deduction_request_id',
        'inventory_item_id',
        'inventory_location_id',
        'quantity_suggested',
        'quantity_approved',
        'unit_cost_snapshot',
        'line_total',
        'raw_description',
        'ai_confidence',
        'ai_reason',
        'match_stage',
        'ops_overridden',
        'fulfillment_status',
        'fulfillment_note',
        'fulfilled_by',
        'fulfilled_at',
    ];

    protected $casts = [
        'quantity_suggested' => 'decimal:2',
        'quantity_approved'  => 'decimal:2',
        'unit_cost_snapshot' => 'decimal:2',
        'line_total'         => 'decimal:2',
        'ops_overridden'     => 'boolean',
        'fulfilled_at'       => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(DeductionRequest::class, 'deduction_request_id');
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

    public function recalculateLineTotal(): void
    {
        $this->line_total = round((float) $this->quantity_approved * (float) $this->unit_cost_snapshot, 2);
        $this->save();
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

    public static function confidenceLabels(): array
    {
        return [
            'high'   => 'High',
            'medium' => 'Medium',
            'low'    => 'Low',
            'manual' => 'Manual',
        ];
    }
}

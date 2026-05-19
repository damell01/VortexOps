<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionRequestLine extends Model
{
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
        'ops_overridden',
    ];

    protected $casts = [
        'quantity_suggested' => 'decimal:2',
        'quantity_approved'  => 'decimal:2',
        'unit_cost_snapshot' => 'decimal:2',
        'line_total'         => 'decimal:2',
        'ops_overridden'     => 'boolean',
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

    public function recalculateLineTotal(): void
    {
        $this->line_total = round((float) $this->quantity_approved * (float) $this->unit_cost_snapshot, 2);
        $this->save();
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

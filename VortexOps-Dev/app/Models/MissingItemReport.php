<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MissingItemReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pallet_id',
        'inventory_item_id',
        'expected_quantity',
        'unit_cost',
        'total_value',
        'notes',
        'reported_by',
    ];

    protected $casts = [
        'expected_quantity' => 'integer',
        'unit_cost'         => 'decimal:2',
        'total_value'       => 'decimal:2',
    ];

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_item_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public static function findMissingForPallet(Pallet $pallet): array
    {
        // For each line on the pallet, check if all expected cases were received
        $missing = [];

        foreach ($pallet->lines as $line) {
            if (! $line->isFullyMapped()) {
                continue;
            }

            $expected = $line->case_count;
            $received = $line->cases()->where('status', '!=', 'expected')->count();

            if ($received < $expected) {
                $missingCount = $expected - $received;
                $totalQty     = $missingCount * $line->quantity_per_case;

                $missing[] = [
                    'item_id'        => $line->inventory_item_id,
                    'item_name'      => $line->inventoryItem?->name,
                    'sku'            => $line->inventoryItem?->sku,
                    'expected_qty'   => $totalQty,
                    'unit_cost'      => $line->unit_cost,
                    'total_value'    => $totalQty * $line->unit_cost,
                    'missing_cases'  => $missingCount,
                ];
            }
        }

        return $missing;
    }
}

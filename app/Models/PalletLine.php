<?php

namespace App\Models;

use App\Jobs\GeneratePalletReceivingReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PalletLine extends Model
{
    protected $fillable = [
        'pallet_id',
        'receiving_session_id',
        'line_number',
        'description',
        'is_container',
        'vendor_description',
        'inventory_item_id',
        'match_confidence',
        'match_stage',
        'match_reasons',
        'matched_at',
        'matched_by',
        'product_identity_id',
        'case_count',
        'quantity_per_case',
        'unit_cost',
        'inventory_location_id',
        'line_status',
        'preflight_cost',
    ];

    protected $casts = [
        'is_container'      => 'boolean',
        'quantity_per_case' => 'decimal:2',
        'unit_cost'         => 'decimal:4',
        'preflight_cost'    => 'decimal:4',
        'match_confidence'  => 'float',
        'match_reasons'     => 'array',
        'matched_at'        => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $line) {
            if ($line->line_number === null && $line->pallet_id) {
                $line->line_number = (static::where('pallet_id', $line->pallet_id)->max('line_number') ?? 0) + 1;
            }
        });

        static::saving(function (self $line) {
            if ($line->case_count === null || $line->case_count === '') {
                $line->case_count = 1;
            }

            if ($line->quantity_per_case === null || $line->quantity_per_case === '') {
                $line->quantity_per_case = 1;
            }

            if ($line->unit_cost === null || $line->unit_cost === '') {
                $line->unit_cost = 0;
            }
        });

        // `received` is the final operational pallet state. Whenever a line is
        // completed, check whether it was the last outstanding line. This makes
        // scanning, Receive All, partial receive, and whole-pallet receive all
        // converge on the same result without a second "mark processed" step.
        static::saved(function (self $line): void {
            if ($line->line_status === 'received' && $line->pallet_id) {
                $hasOutstanding = static::query()
                    ->where('pallet_id', $line->pallet_id)
                    ->where(function ($query) {
                        $query->whereNull('line_status')
                            ->orWhere('line_status', '!=', 'received');
                    })
                    ->exists();

                if (! $hasOutstanding) {
                    $pallet = Pallet::find($line->pallet_id);
                    if ($pallet && $pallet->status !== 'received') {
                        $pallet->forceFill([
                            'status' => 'received',
                            'received_date' => $pallet->received_date ?? today(),
                        ])->save();
                    }
                }
            }

            static::queuePreparedReportRefresh($line->pallet_id);
        });

        static::deleted(fn (self $line) => static::queuePreparedReportRefresh($line->pallet_id));
    }

    private static function queuePreparedReportRefresh(?int $palletId): void
    {
        if (! $palletId) return;

        $pallet = Pallet::find($palletId);
        if (! $pallet || ! in_array($pallet->status, ['received', 'processed'], true)) return;

        GeneratePalletReceivingReport::dispatch($palletId)->afterCommit();
    }

    public function pallet(): BelongsTo
    {
        return $this->belongsTo(Pallet::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_item_id');
    }

    public function receivingSession(): BelongsTo
    {
        return $this->belongsTo(ReceivingSession::class);
    }

    public function productIdentity(): BelongsTo
    {
        return $this->belongsTo(ProductIdentity::class);
    }

    public function lot(): HasOne
    {
        return $this->hasOne(InventoryLot::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(InventoryCase::class);
    }

    public function totalQuantityExpected(): float
    {
        return (float) $this->case_count * (float) $this->quantity_per_case;
    }

    public function totalCost(): float
    {
        return round($this->totalQuantityExpected() * (float) $this->unit_cost, 2);
    }

    public function receivedCases(): int
    {
        return $this->cases()->where('status', '!=', 'expected')->count();
    }

    public function isFullyMapped(): bool
    {
        return $this->inventory_item_id !== null && $this->inventory_location_id !== null;
    }

    /**
     * Supplier-facing quantity terminology. A manifest line whose pack size is
     * one is not really "500 cases" — it is 500 single units. Keep the storage
     * model intact while presenting the same language as the source document.
     */
    public function quantityLabel(): string
    {
        return (float) $this->quantity_per_case <= 1
            ? 'Single Units'
            : 'Cases';
    }

    public function displayQuantity(): float
    {
        return (float) $this->case_count;
    }

    public function packSizeLabel(): ?string
    {
        $pack = (float) $this->quantity_per_case;

        return $pack > 1 ? rtrim(rtrim(number_format($pack, 2, '.', ''), '0'), '.') . ' / case' : null;
    }
}

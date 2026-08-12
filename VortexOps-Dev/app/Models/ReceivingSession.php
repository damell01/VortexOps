<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ReceivingSession extends Model
{
    use LogsActivity, SoftDeletes;

    // Status constants
    const STATUS_PENDING   = 'pending';
    const STATUS_PARSING   = 'parsing';
    const STATUS_REVIEWING = 'reviewing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'vendor_id',
        'received_by',
        'invoice_number',
        'purchase_order',
        'uploaded_file_path',
        'file_type',
        'status',
        'total_lines',
        'auto_matched_count',
        'review_required_count',
        'new_product_count',
        'notes',
    ];

    protected $casts = [
        'total_lines'          => 'integer',
        'auto_matched_count'   => 'integer',
        'review_required_count' => 'integer',
        'new_product_count'    => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }

    public function palletLines(): HasMany
    {
        return $this->hasMany(PalletLine::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    // ── Computed ───────────────────────────────────────────────────────────────

    public function matchRatePct(): int
    {
        if ($this->total_lines === 0) {
            return 0;
        }

        return (int) round($this->auto_matched_count / $this->total_lines * 100);
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function needsReview(): bool
    {
        return $this->review_required_count > 0 || $this->new_product_count > 0;
    }

    /**
     * Reopen a completed session for corrections.
     */
    public function reopen(): void
    {
        $this->update(['status' => self::STATUS_REVIEWING]);
    }

    /**
     * Tally match counts from pallet lines and update summary columns.
     */
    public function recalculateSummary(): void
    {
        $lines = $this->palletLines()->get(['match_confidence', 'inventory_item_id']);

        $auto    = 0;
        $review  = 0;
        $newProd = 0;

        foreach ($lines as $line) {
            $confidence = (float) $line->match_confidence;

            if ($line->inventory_item_id && $confidence >= 0.95) {
                $auto++;
            } elseif ($line->inventory_item_id && $confidence >= 0.80) {
                $review++;
            } else {
                $newProd++;
            }
        }

        $this->update([
            'total_lines'          => $lines->count(),
            'auto_matched_count'   => $auto,
            'review_required_count' => $review,
            'new_product_count'    => $newProd,
        ]);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING   => 'Pending',
            self::STATUS_PARSING   => 'Parsing',
            self::STATUS_REVIEWING => 'Needs Review',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}

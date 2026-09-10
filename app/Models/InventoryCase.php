<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCase extends Model
{
    protected $fillable = [
        'pallet_line_id',
        'barcode',
        'status',
        'quantity_received',
        'received_at',
        'received_by',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
        'received_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        // Some scanner paths receive a case directly rather than going through
        // confirmOneCase(). Make the case itself responsible for closing its
        // line when the final expected case is received. PalletLine then closes
        // the pallet, so every receiving path reaches the same final state.
        static::saved(function (self $case): void {
            if ($case->status === 'expected' || ! $case->pallet_line_id) return;

            $line = PalletLine::find($case->pallet_line_id);
            if (! $line || $line->line_status === 'received') return;

            $totalCases = $line->cases()->count();
            if ($totalCases < (int) $line->case_count) return;

            if ($line->cases()->where('status', 'expected')->exists()) return;

            $line->update(['line_status' => 'received']);
        });
    }

    public function palletLine(): BelongsTo
    {
        return $this->belongsTo(PalletLine::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public static function statusLabels(): array
    {
        return [
            'expected' => 'Expected',
            'received' => 'Received',
            'opened'   => 'Opened',
        ];
    }

    public static function findByBarcode(string $barcode): ?self
    {
        return static::where('barcode', $barcode)->first();
    }
}

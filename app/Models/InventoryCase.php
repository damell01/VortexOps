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

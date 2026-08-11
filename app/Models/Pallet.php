<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Pallet extends Model
{
    use LogsActivity, SoftDeletes;

    const STAGE_CREATED = 'created';
    const STAGE_STAGED = 'staged';
    const STAGE_RECEIVING = 'receiving';
    const STAGE_RECEIVED = 'received';
    const STAGE_PROCESSED = 'processed';

    protected $fillable = [
        'vendor_id',
        'receiving_session_id',
        'reference',
        'received_date',
        'status',
        'carrier',
        'tracking_number',
        'expected_delivery_date',
        'shipped_at',
        'total_cost',
        'shipping_cost',
        'notes',
        'created_by',
        'stage',
        'packing_slip_path',
        'staged_at',
        'receiving_started_at',
        'line_items_total',
        'line_items_received',
        'signature_path',
        'signature_timestamp',
        'received_by_name',
        'attachments_count',
    ];

    protected $casts = [
        'received_date'           => 'date',
        'expected_delivery_date'  => 'date',
        'shipped_at'              => 'datetime',
        'staged_at'               => 'datetime',
        'receiving_started_at'    => 'datetime',
        'total_cost'              => 'decimal:2',
        'shipping_cost'           => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function receivingSession(): BelongsTo
    {
        return $this->belongsTo(ReceivingSession::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PalletLine::class)->orderBy('line_number');
    }

    public function cases(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryCase::class, PalletLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scannerSessions(): HasMany
    {
        return $this->hasMany(ScannerReceivingSession::class);
    }

    public function packingSlips(): HasMany
    {
        return $this->hasMany(PalletPackingSlip::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PalletAttachment::class);
    }

    public function totalCasesCount(): int
    {
        return $this->lines()->sum('case_count');
    }

    public function receivedCasesCount(): int
    {
        return $this->cases()->where('status', '!=', 'expected')->count();
    }

    public function isFullyReceived(): bool
    {
        return $this->cases()->where('status', 'expected')->doesntExist();
    }

    public static function statusLabels(): array
    {
        return [
            'pending'    => 'Pending',
            'shipped'    => 'Shipped',
            'receiving'  => 'Receiving',
            'received'   => 'Received',
            'processed'  => 'Processed',
        ];
    }
}

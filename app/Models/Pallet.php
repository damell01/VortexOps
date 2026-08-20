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
        'name',
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
        'payment_fees',
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
        'payment_fees'            => 'decimal:2',
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

    public function missingItems(): HasMany
    {
        return $this->hasMany(MissingItemReport::class);
    }

    /**
     * Costs of acquiring this pallet that are not on any invoice line, and so
     * have to be spread across the units received: carrier charges and payment
     * processing fees.
     *
     * Defined once here rather than summed at each call site, so adding a third
     * kind of fee later cannot end up counted in receiving but missed in the
     * figures shown on screen.
     */
    public function landedCostExtras(): float
    {
        return (float) ($this->shipping_cost ?? 0) + (float) ($this->payment_fees ?? 0);
    }

    /**
     * Cases expected across every line.
     *
     * Prefers an aggregate the query already selected. A table listing pallets
     * asks each row for this, and answering with a fresh SUM every time is one
     * query per row per column — which is how the list page reached 490
     * queries for four pallets.
     */
    public function totalCasesCount(): int
    {
        return (int) ($this->expected_cases_sum ?? $this->lines()->sum('case_count'));
    }

    /** Cases actually confirmed. Same aggregate-first rule as above. */
    public function receivedCasesCount(): int
    {
        return (int) ($this->received_cases_count ?? $this->cases()->where('status', '!=', 'expected')->count());
    }

    public function isFullyReceived(): bool
    {
        return $this->cases()->where('status', 'expected')->doesntExist();
    }

    public static function statusLabels(): array
    {
        return [
            'staged'     => 'Staged (Waiting for Arrival)',
            'receiving'  => 'Receiving (In Progress)',
            'received'   => 'Received (All Items In)',
            'processed'  => 'Processed (Complete)',
        ];
    }

    public static function statusPhases(): array
    {
        return [
            'staged'     => ['number' => 1, 'label' => 'Manifest Staged'],
            'receiving'  => ['number' => 2, 'label' => 'Actively Receiving'],
            'received'   => ['number' => 3, 'label' => 'All Received'],
            'processed'  => ['number' => 4, 'label' => 'Complete'],
        ];
    }
    /**
     * What to call this pallet on screen.
     *
     * The name if it has one, else the vendor's reference, else its id. Every
     * list and heading goes through here so a pallet is not "Topps Chrome" in
     * one place and "#17" in another — which is how two people end up certain
     * they are talking about different shipments.
     */
    public function displayName(): string
    {
        return $this->name
            ?: ($this->reference ?: ('Pallet #' . $this->getKey()));
    }

}

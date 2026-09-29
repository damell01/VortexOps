<?php

namespace App\Models;

use App\Jobs\GeneratePalletReceivingReport;
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
    const STAGE_PROCESSED = 'processed'; // legacy only

    protected $fillable = [
        'vendor_id','name','receiving_session_id','reference','received_date','status','carrier','tracking_number',
        'expected_delivery_date','shipped_at','total_cost','shipping_cost','payment_fees','notes','created_by','stage',
        'packing_slip_path','staged_at','receiving_started_at','line_items_total','line_items_received','signature_path',
        'signature_timestamp','received_by_name','attachments_count',
    ];

    protected $casts = [
        'received_date'=>'date','expected_delivery_date'=>'date','shipped_at'=>'datetime','staged_at'=>'datetime',
        'receiving_started_at'=>'datetime','total_cost'=>'decimal:2','shipping_cost'=>'decimal:2','payment_fees'=>'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $pallet): void {
            if (! in_array($pallet->status, ['received', 'processed'], true)) {
                return;
            }

            $reportFields = [
                'status', 'received_date', 'vendor_id', 'name', 'reference',
                'shipping_cost', 'payment_fees', 'total_cost',
            ];

            if (! $pallet->wasRecentlyCreated && ! $pallet->wasChanged($reportFields)) {
                return;
            }

            GeneratePalletReceivingReport::dispatch($pallet->getKey())->afterCommit();
        });
    }

    public function getActivitylogOptions(): LogOptions { return LogOptions::defaults()->logAll()->logOnlyDirty(); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function receivingSession(): BelongsTo { return $this->belongsTo(ReceivingSession::class); }
    public function lines(): HasMany { return $this->hasMany(PalletLine::class)->orderBy('line_number'); }
    public function cases(): HasManyThrough { return $this->hasManyThrough(InventoryCase::class, PalletLine::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function scannerSessions(): HasMany { return $this->hasMany(ScannerReceivingSession::class); }
    public function packingSlips(): HasMany { return $this->hasMany(PalletPackingSlip::class); }
    public function attachments(): HasMany { return $this->hasMany(PalletAttachment::class); }
    public function missingItems(): HasMany { return $this->hasMany(MissingItemReport::class); }

    public function landedCostExtras(): float
    {
        return (float) ($this->shipping_cost ?? 0) + (float) ($this->payment_fees ?? 0);
    }

    public function totalCasesCount(): int
    {
        return (int) ($this->expected_cases_sum ?? $this->lines()->sum('case_count'));
    }

    public function receivedCasesCount(): int
    {
        return (int) ($this->received_cases_count ?? $this->cases()->where('status', '!=', 'expected')->count());
    }

    public function isFullyReceived(): bool
    {
        return $this->lines()->exists()
            && ! $this->lines()->where(function ($query) {
                $query->whereNull('line_status')->orWhere('line_status', '!=', 'received');
            })->exists();
    }

    public static function statusLabels(): array
    {
        return [
            'staged'    => 'Staged (Waiting for Arrival)',
            'receiving' => 'Receiving (In Progress)',
            'received'  => 'Received (Complete)',
        ];
    }

    public static function statusPhases(): array
    {
        return [
            'staged'    => ['number' => 1, 'label' => 'Manifest Staged'],
            'receiving' => ['number' => 2, 'label' => 'Actively Receiving'],
            'received'  => ['number' => 3, 'label' => 'Complete'],
        ];
    }

    /**
     * Reconcile line/pallet completion from the cases that were actually received.
     *
     * Some receiving paths update InventoryCase rows in bulk, which deliberately
     * bypasses Eloquent model events. Keeping this reconciliation on the pallet
     * means the scanner, Receive Some, Receive Line and Receive Entire Pallet all
     * converge on the same final state: received is complete; processed is legacy.
     */
    public function syncReceivingCompletion(): bool
    {
        $this->loadMissing('lines.cases');

        if ($this->lines->isEmpty()) {
            return false;
        }

        $allComplete = true;

        foreach ($this->lines as $line) {
            $expected = (int) $line->case_count;
            $received = $line->cases->where('status', '!=', 'expected')->count();
            $complete = $expected > 0 && $received >= $expected;

            if ($complete && $line->line_status !== 'received') {
                $line->forceFill(['line_status' => 'received'])->save();
            }

            if (! $complete) {
                $allComplete = false;
            }
        }

        if ($allComplete && $this->status !== 'received') {
            $this->forceFill([
                'status' => 'received',
                'received_date' => $this->received_date ?? today(),
            ])->save();
        }

        return $allComplete;
    }

    public function receivingProgress(): array
    {
        $lineIds = $this->lines()->pluck('id');
        $expected = (int) $this->lines()->sum('case_count');

        $receivedQuery = InventoryCase::query()
            ->whereIn('pallet_line_id', $lineIds)
            ->where('status', '!=', 'expected');

        $received = (int) (clone $receivedQuery)->count();
        $receivedTimes = (clone $receivedQuery)
            ->whereNotNull('received_at')
            ->selectRaw('MIN(received_at) as first_received_at, MAX(received_at) as last_received_at')
            ->first();

        $receivedByLine = InventoryCase::query()
            ->selectRaw('pallet_line_id, COUNT(*) as received_count')
            ->whereIn('pallet_line_id', $lineIds)
            ->where('status', '!=', 'expected')
            ->groupBy('pallet_line_id')
            ->pluck('received_count', 'pallet_line_id');

        $linesOutstanding = $this->lines()
            ->get(['id', 'case_count'])
            ->filter(fn (PalletLine $line) => (int) ($receivedByLine[$line->id] ?? 0) < (int) $line->case_count)
            ->count();

        $complete = $expected > 0 && $received >= $expected;

        if ($complete && $this->status !== 'received') {
            $this->forceFill([
                'status' => 'received',
                'received_date' => $this->received_date ?? today(),
            ])->save();
        }

        return [
            'expected' => $expected,
            'received' => $received,
            'outstanding' => max(0, $expected - $received),
            'complete' => $complete,
            'started' => $received > 0 || $this->receiving_started_at !== null,
            'started_at' => $this->receiving_started_at,
            'first_received_at' => $receivedTimes?->first_received_at,
            'last_received_at' => $receivedTimes?->last_received_at,
            'lines_outstanding' => $linesOutstanding,
        ];
    }

    public function markReceivingStarted(): void
    {
        if ($this->receiving_started_at !== null && $this->status === 'receiving') return;

        $this->forceFill([
            'receiving_started_at' => $this->receiving_started_at ?? now(),
            'status' => in_array($this->status, ['received', 'processed'], true) ? $this->status : 'receiving',
        ])->save();
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->reference ?: ('Pallet #' . $this->getKey()));
    }
}

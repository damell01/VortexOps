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

    protected $fillable = [
        'vendor_id',
        'reference',
        'received_date',
        'status',
        'total_cost',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'total_cost'    => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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
            'receiving'  => 'Receiving',
            'received'   => 'Received',
            'processed'  => 'Processed',
        ];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\AuditsUpdates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class WeeklyPayoutBatch extends Model
{
    use LogsActivity, AuditsUpdates;

    // See Payout: AuditsUpdates records real diffs; LogsActivity skips "updated".
    protected static array $doNotRecordEvents = ['updated'];

    /** @return array<int,string> */
    public function auditableFields(): array
    {
        return ['status', 'total_payout', 'finalized_by', 'finalized_at'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_payout', 'finalized_by', 'finalized_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pay_run');
    }

    protected $fillable = [
        'week_start',
        'week_end',
        'status',
        'total_payout',
        'notes',
        'created_by',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'week_start'   => 'date',
        'week_end'     => 'date',
        'total_payout' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function recalculateTotal(): void
    {
        $this->total_payout = $this->payouts()->sum('calculated_payout');
        $this->save();
    }

    public static function statusLabels(): array
    {
        return [
            'draft'            => 'Draft',
            'finalized'        => 'Finalized',
            'submitted_to_adp' => 'Submitted to ADP',
            'paid'             => 'Paid',
        ];
    }
}

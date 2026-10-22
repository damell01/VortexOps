<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StreamerLoan extends Model
{
    protected $fillable = [
        'streamer_id',
        'label',
        'original_amount',
        'weekly_repayment',
        'repayment_frequency',
        'last_repayment_period',
        'remaining_balance',
        'deduct_from_payout',
        'status',
        'notes',
    ];

    protected $casts = [
        'original_amount'     => 'decimal:2',
        'weekly_repayment'    => 'decimal:2',
        'remaining_balance'   => 'decimal:2',
        'deduct_from_payout'  => 'boolean',
        'last_repayment_period' => 'date',
    ];

    public function streamer(): BelongsTo
    {
        return $this->belongsTo(Streamer::class);
    }

    public function recordManualPayment(float $amount, string $date, ?string $note = null): StreamerLoanPayment
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($amount, $date, $note) {
            $loan = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $amount = round($amount, 2);
            if ($amount <= 0 || $loan->status !== 'active' || $amount > (float) $loan->remaining_balance) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Payment must be positive and cannot exceed the outstanding loan balance.']);
            }
            $newBalance = round((float) $loan->remaining_balance - $amount, 2);
            $payment = $loan->payments()->create([
                'amount' => $amount, 'payment_date' => $date, 'source' => 'manual',
                'note' => $note, 'recorded_by' => auth()->id(),
            ]);
            $loan->update(['remaining_balance' => $newBalance, 'status' => $newBalance <= 0 ? 'paid_off' : 'active']);
            $this->refresh();
            return $payment;
        });
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StreamerLoanPayment::class)->orderByDesc('payment_date');
    }

    public static function statusLabels(): array
    {
        return [
            'active'   => 'Active',
            'paid_off' => 'Paid Off',
        ];
    }
}

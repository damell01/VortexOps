<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamerLoanPayment extends Model
{
    protected $fillable = ['streamer_loan_id', 'amount', 'payment_date', 'source', 'note', 'recorded_by'];
    protected $casts = ['amount' => 'decimal:2', 'payment_date' => 'date'];
    public function loan(): BelongsTo { return $this->belongsTo(StreamerLoan::class, 'streamer_loan_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentFlag extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime', 'blocking' => 'boolean'];

    public function show(): BelongsTo { return $this->belongsTo(Show::class); }
    public function run(): BelongsTo { return $this->belongsTo(FulfillmentRun::class, 'run_id'); }
    public function buyer(): BelongsTo { return $this->belongsTo(FulfillmentRunBuyer::class, 'buyer_id'); }
    public function station(): BelongsTo { return $this->belongsTo(FulfillmentStation::class, 'station_id'); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}

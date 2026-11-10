<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentRunPackage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['order_ids' => 'array', 'packed_at' => 'datetime', 'checked_at' => 'datetime', 'is_pwe' => 'boolean'];

    public function run(): BelongsTo { return $this->belongsTo(FulfillmentRun::class, 'run_id'); }
    public function buyer(): BelongsTo { return $this->belongsTo(FulfillmentRunBuyer::class, 'buyer_id'); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function packer(): BelongsTo { return $this->belongsTo(User::class, 'packed_by'); }
    public function checker(): BelongsTo { return $this->belongsTo(User::class, 'checked_by'); }
}

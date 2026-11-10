<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FulfillmentRunBuyer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'order_ids' => 'array', 'packing_started_at' => 'datetime', 'packed_at' => 'datetime', 'checked_at' => 'datetime',
        'item_count' => 'integer', 'order_count' => 'integer', 'package_count' => 'integer',
    ];

    public function run(): BelongsTo { return $this->belongsTo(FulfillmentRun::class, 'run_id'); }
    public function packages(): HasMany { return $this->hasMany(FulfillmentRunPackage::class, 'buyer_id')->orderBy('id'); }
    public function packer(): BelongsTo { return $this->belongsTo(User::class, 'packed_by'); }
    public function checker(): BelongsTo { return $this->belongsTo(User::class, 'checked_by'); }
    public function flags(): HasMany { return $this->hasMany(FulfillmentFlag::class, 'buyer_id'); }
}

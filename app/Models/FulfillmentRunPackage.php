<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentRunPackage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['order_ids' => 'array', 'packed_at' => 'datetime', 'is_pwe' => 'boolean'];

    public function run(): BelongsTo { return $this->belongsTo(FulfillmentRun::class, 'run_id'); }
    public function buyer(): BelongsTo { return $this->belongsTo(FulfillmentRunBuyer::class, 'buyer_id'); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function packer(): BelongsTo { return $this->belongsTo(User::class, 'packed_by'); }

    /** The Whatnot shipping label for this package, as the Seller API sync stored it (https only). */
    public function labelUrl(): ?string
    {
        return self::documentUrl($this->shipment?->raw_payload, 'label_url');
    }

    public static function documentUrl(mixed $payload, string $key): ?string
    {
        $docs = is_array($payload) && is_array($payload['documents'] ?? null) ? $payload['documents'] : [];

        return \App\Filament\Resources\FulfillmentResource\Pages\ShippingDesk::safeDocumentUrl($docs[$key] ?? null);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only activity log for fulfillment. Events are written once and never
 * changed or removed: the show log and the camera lookup depend on them being
 * a faithful record of what happened when.
 */
class FulfillmentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Fulfillment events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Fulfillment events are append-only.'));
    }

    public function run(): BelongsTo { return $this->belongsTo(FulfillmentRun::class, 'run_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function station(): BelongsTo { return $this->belongsTo(FulfillmentStation::class, 'station_id'); }
    public function show(): BelongsTo { return $this->belongsTo(Show::class); }

    public static function label(string $type): string
    {
        return match ($type) {
            'ready' => 'Show ready to pack',
            'assigned' => 'Assigned',
            'started' => 'Packing started',
            'buyers_loaded' => 'Buyers loaded',
            'buyer_selected' => 'Buyer selected',
            'package_packed' => 'Package packed',
            'buyer_packed' => 'Buyer packed',
            'buyer_reopened' => 'Buyer reopened',
            'item_verified' => 'Item verified',
            'scan_rejected' => 'Scan rejected',
            'scan_override' => 'Scan exception approved',
            'issue_flagged' => 'Issue flagged',
            'issue_resolved' => 'Issue resolved',
            'paused' => 'Packing paused',
            'resumed' => 'Packing resumed',
            'finished' => 'Packing completed',
            'acknowledged' => 'Packer confirmed the show is done',
            'approved' => 'Final sign-off',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}

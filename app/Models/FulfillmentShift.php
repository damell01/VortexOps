<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FulfillmentShift extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function station(): BelongsTo { return $this->belongsTo(FulfillmentStation::class, 'station_id'); }
    public function shows(): BelongsToMany { return $this->belongsToMany(Show::class, 'fulfillment_shift_shows', 'shift_id', 'show_id')->withTimestamps(); }

    /** A shift that ends on a later calendar day than it starts. */
    public function crossesMidnight(): bool
    {
        return $this->starts_at && $this->ends_at && ! $this->starts_at->isSameDay($this->ends_at);
    }
}

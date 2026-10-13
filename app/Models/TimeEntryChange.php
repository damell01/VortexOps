<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who changed a time entry or a report's hours, from what to what, and why. */
class TimeEntryChange extends Model
{
    protected $fillable = ['time_entry_id', 'streamer_log_entry_id', 'user_id', 'action', 'old_values', 'new_values', 'reason'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }
}

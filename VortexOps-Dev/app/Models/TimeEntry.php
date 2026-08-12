<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $fillable = [
        'user_id',
        'clocked_in_at',
        'clocked_out_at',
        'notes',
    ];

    protected $casts = [
        'clocked_in_at'  => 'datetime',
        'clocked_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->clocked_out_at) {
            return null;
        }

        return (int) $this->clocked_in_at->diffInMinutes($this->clocked_out_at);
    }

    public function getIsOpenAttribute(): bool
    {
        return $this->clocked_out_at === null;
    }

    public static function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
}

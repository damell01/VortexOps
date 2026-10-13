<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business payroll is run for (Vortex Collects, Estill Distribution).
 * Both sell on the same channels; each team member works for one.
 */
class Business extends Model
{
    protected $fillable = ['name', 'short_name', 'color', 'sort_order'];

    public function streamers(): HasMany { return $this->hasMany(Streamer::class); }
    public function payRuns(): HasMany { return $this->hasMany(WeeklyPayoutBatch::class); }

    public function label(): string { return $this->short_name ?: $this->name; }

    /** @return \Illuminate\Database\Eloquent\Collection<int, self> */
    public static function ordered(): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()->orderBy('sort_order')->orderBy('name')->get();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Mystery Egg (App\Support\PokemonGames): it hatches after a few more catches. */
class PokemonEgg extends Model
{
    protected $fillable = ['streamer_id', 'user_id', 'reason', 'reason_key', 'steps_needed', 'steps', 'hatched_catch_id', 'hatched_at'];

    protected $casts = ['hatched_at' => 'datetime'];

    public function hatched(): BelongsTo { return $this->belongsTo(PokemonCatch::class, 'hatched_catch_id'); }
}

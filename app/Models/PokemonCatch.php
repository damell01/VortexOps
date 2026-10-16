<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Pokémon a streamer caught by filing a show report (see App\Support\Pokedex). */
class PokemonCatch extends Model
{
    protected $fillable = ['streamer_id', 'streamer_log_entry_id', 'show_id', 'species', 'shiny', 'caught_at'];

    protected $casts = ['shiny' => 'boolean', 'caught_at' => 'datetime'];

    public function streamer(): BelongsTo { return $this->belongsTo(Streamer::class); }
    public function show(): BelongsTo { return $this->belongsTo(Show::class); }
}

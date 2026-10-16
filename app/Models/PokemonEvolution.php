<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A catch that evolved, and what set it off (an on-time streak). */
class PokemonEvolution extends Model
{
    protected $fillable = ['pokemon_catch_id', 'from_species', 'to_species', 'trigger_key'];
}

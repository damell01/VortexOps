<?php

namespace App\Models;

use App\Support\Trainer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Pokémon a trainer caught (see App\Support\Pokedex and PokemonGames).
 * A streamer's catches carry streamer_id, a staff member's user_id, and a
 * streamer with a login both. source says what earned it; source_key makes
 * each one earnable once.
 */
class PokemonCatch extends Model
{
    protected $fillable = ['streamer_id', 'user_id', 'streamer_log_entry_id', 'show_id', 'source', 'source_key', 'species', 'evolved_from', 'shiny', 'caught_at', 'released_at', 'traded_at'];

    protected $casts = ['shiny' => 'boolean', 'caught_at' => 'datetime', 'released_at' => 'datetime', 'traded_at' => 'datetime'];

    public function streamer(): BelongsTo { return $this->belongsTo(Streamer::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function show(): BelongsTo { return $this->belongsTo(Show::class); }

    /** Still in the trainer's Pokédex (not released). */
    public function scopeKept(Builder $q): Builder
    {
        return $q->whereNull('released_at');
    }

    public function scopeOwnedBy(Builder $q, Trainer $trainer): Builder
    {
        return $q->where(function (Builder $w) use ($trainer) {
            $trainer->streamerId ? $w->where('streamer_id', $trainer->streamerId) : $w->whereRaw('1 = 0');
            if ($trainer->userId) {
                $w->orWhere('user_id', $trainer->userId);
            }
        });
    }
}

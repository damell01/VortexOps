<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One trainer offers a duplicate for one of another's (App\Support\PokemonGames::trade). */
class PokemonTrade extends Model
{
    protected $fillable = ['proposer_id', 'recipient_id', 'offered_catch_id', 'requested_catch_id', 'status', 'responded_at'];

    protected $casts = ['responded_at' => 'datetime'];

    public function proposer(): BelongsTo { return $this->belongsTo(User::class, 'proposer_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
    public function offered(): BelongsTo { return $this->belongsTo(PokemonCatch::class, 'offered_catch_id'); }
    public function requested(): BelongsTo { return $this->belongsTo(PokemonCatch::class, 'requested_catch_id'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMessageAttachment extends Model
{
    protected $guarded = ['id'];
    public function message(): BelongsTo { return $this->belongsTo(TeamMessage::class, 'message_id'); }
}

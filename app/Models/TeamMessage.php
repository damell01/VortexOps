<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMessage extends Model
{
    protected $guarded = ['id'];
    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(TeamMessageAttachment::class, 'message_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
}

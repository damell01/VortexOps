<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamConversation extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['is_preview' => 'boolean', 'last_message_at' => 'datetime']; }
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_conversation_members', 'conversation_id', 'user_id')->withPivot('last_read_message_id')->withTimestamps();
    }
    public function latestMessage(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(TeamMessage::class, 'conversation_id')->latestOfMany(); }
    public function messages(): HasMany { return $this->hasMany(TeamMessage::class, 'conversation_id'); }
    public function labelFor(User $viewer): string
    {
        return $this->kind === 'group' ? $this->title : ($this->members->firstWhere('id', '!=', $viewer->id)?->name ?? 'Deleted account');
    }
}

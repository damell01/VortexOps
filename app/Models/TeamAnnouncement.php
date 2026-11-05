<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamAnnouncement extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['in_app_requested' => 'boolean', 'dismissible' => 'boolean', 'role_ids' => 'array', 'user_ids' => 'array', 'show_banner' => 'boolean', 'email_requested' => 'boolean', 'push_requested' => 'boolean', 'is_preview' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime'];
    }
    public function acknowledgments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\TeamAnnouncementAcknowledgment::class, 'announcement_id');
    }

    public function phase(): string
    {
        if ($this->status === 'draft') return 'Draft';
        if ($this->status === 'archived') return 'Archived';
        if ($this->ends_at->lte(now())) return 'Expired';
        return $this->starts_at->gt(now()) ? 'Scheduled' : 'Active preview';
    }
}

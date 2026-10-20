<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamAnnouncement extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['role_ids' => 'array', 'user_ids' => 'array', 'show_banner' => 'boolean', 'email_requested' => 'boolean', 'push_requested' => 'boolean', 'is_preview' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime'];
    }
    public function phase(): string
    {
        if ($this->status === 'draft') return 'Draft';
        if ($this->status === 'archived') return 'Archived';
        if ($this->ends_at->lte(now())) return 'Expired';
        return $this->starts_at->gt(now()) ? 'Scheduled' : 'Active preview';
    }
}

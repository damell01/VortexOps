<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'summary',
        'status',
        'phase',
        'progress_percent',
        'launch_date',
        'current_focus',
        'client_needs',
        'owner_user_id',
        'manager_user_id',
        'is_active',
        'client_visible',
    ];

    protected $casts = [
        'launch_date' => 'date',
        'is_active' => 'boolean',
        'client_visible' => 'boolean',
        'progress_percent' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            if (blank($project->slug)) {
                $project->slug = Str::slug($project->name);
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('due_date');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ProjectApproval::class)->latest('requested_at')->latest();
    }

    public function statusUpdates(): HasMany
    {
        return $this->hasMany(ProjectStatusUpdate::class)->latest();
    }

    public function reviewSessions(): HasMany
    {
        return $this->hasMany(ReviewSession::class)->latest();
    }

    public function reviewItems(): HasManyThrough
    {
        return $this->hasManyThrough(ReviewItem::class, ReviewSession::class, 'project_id', 'review_session_id');
    }

    public static function statusLabels(): array
    {
        return [
            'planning' => 'Planning',
            'implementation' => 'Implementation',
            'review' => 'In Review',
            'blocked' => 'Blocked',
            'ready_to_launch' => 'Ready to Launch',
            'launched' => 'Launched',
            'archived' => 'Archived',
        ];
    }
}

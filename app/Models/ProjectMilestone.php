<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'status',
        'sort_order',
        'due_date',
        'completed_at',
        'approved_at',
        'visible_to_client',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'visible_to_client' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function statusLabels(): array
    {
        return [
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'blocked' => 'Blocked',
            'completed' => 'Completed',
            'approved' => 'Approved',
        ];
    }
}

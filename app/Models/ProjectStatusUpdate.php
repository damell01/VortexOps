<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStatusUpdate extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'body',
        'status',
        'visible_to_client',
        'created_by',
    ];

    protected $casts = [
        'visible_to_client' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusLabels(): array
    {
        return [
            'note' => 'Note',
            'in_progress' => 'In Progress',
            'blocked' => 'Blocked',
            'completed' => 'Completed',
            'needs_client' => 'Needs Client',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectUpdate extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'body',
        'type',
        'is_resolved',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'is_resolved' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function typeLabels(): array
    {
        return [
            'update'   => 'Update',
            'feature'  => 'Feature',
            'blocker'  => 'Blocker',
            'decision' => 'Decision',
        ];
    }
}

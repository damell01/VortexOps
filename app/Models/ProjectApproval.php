<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectApproval extends Model
{
    protected $fillable = [
        'project_id',
        'label',
        'description',
        'status',
        'requested_at',
        'approved_by',
        'approved_at',
        'notes',
        'visible_to_client',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'visible_to_client' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public static function statusLabels(): array
    {
        return [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'changes_requested' => 'Changes Requested',
        ];
    }
}

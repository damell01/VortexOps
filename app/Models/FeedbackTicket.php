<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackTicket extends Model
{
    protected $fillable = [
        'title',
        'description',
        'screenshot_path',
        'page_url',
        'status',
        'priority',
        'submitted_by',
        'submitted_name',
        'submitted_email',
        'assigned_to',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public static function statusLabels(): array
    {
        return [
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'resolved'    => 'Resolved',
            'closed'      => 'Closed',
        ];
    }

    public static function priorityLabels(): array
    {
        return [
            'low'    => 'Low',
            'medium' => 'Medium',
            'high'   => 'High',
        ];
    }
}

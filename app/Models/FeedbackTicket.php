<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackTicket extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'screenshot_path',
        'annotation_json',
        'page_url',
        'resource_type',
        'resource_id',
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

    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackTicketComment::class)->latest();
    }

    public static function typeLabels(): array
    {
        return [
            'bug'        => 'Bug',
            'suggestion' => 'Suggestion',
            'question'   => 'Question',
            'other'      => 'Other',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'needs_info'  => 'Needs Info',
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

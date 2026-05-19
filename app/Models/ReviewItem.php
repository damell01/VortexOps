<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewItem extends Model
{
    protected $fillable = [
        'review_session_id',
        'page_url',
        'page_title',
        'screenshot',
        'fabric_json',
        'comment',
        'type',
        'status',
        'priority',
        'created_by',
        'assigned_to',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ReviewSession::class, 'review_session_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReviewItemComment::class);
    }

    public static function typeLabels(): array
    {
        return [
            'annotation' => 'Annotation',
            'bug'        => 'Bug',
            'suggestion' => 'Suggestion',
            'question'   => 'Question',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'open'        => 'Open',
            'in_progress' => 'In Progress',
            'fixed'       => 'Fixed',
            'approved'    => 'Approved',
            'rejected'    => 'Rejected',
            'wont_fix'    => "Won't Fix",
        ];
    }

    public static function priorityLabels(): array
    {
        return [
            'low'    => 'Low',
            'normal' => 'Normal',
            'high'   => 'High',
        ];
    }
}

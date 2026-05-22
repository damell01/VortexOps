<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    public function getScreenshotAttribute(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (
            str_starts_with($value, 'data:image/')
            || str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, '/storage/')
        ) {
            return $value;
        }

        return Storage::disk('public')->url($value);
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

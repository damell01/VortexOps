<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One email handed to the mailer: who it went to, what it said, whether the
 * mail server took it. Written by App\Listeners\LogOutgoingEmail for every
 * message, not only notifications, so this is the full record of what left.
 */
class EmailLog extends Model
{
    protected $fillable = [
        'event', 'user_id', 'to_email', 'to_name', 'subject', 'html', 'text',
        'status', 'error', 'is_test', 'message_id', 'sent_at',
    ];

    protected $casts = [
        'is_test' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public const STATUSES = [
        'sent' => 'Sent',
        'failed' => 'Failed',
        'sending' => 'Not confirmed',
        'held' => 'Held (limit)',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

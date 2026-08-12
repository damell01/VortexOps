<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowIngestionLog extends Model
{
    protected $fillable = [
        'show_id',
        'source',
        'status',
        'raw_payload',
        'error_message',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public static function statusLabels(): array
    {
        return [
            'success' => 'Success',
            'failed'  => 'Failed',
            'partial' => 'Partial',
        ];
    }
}

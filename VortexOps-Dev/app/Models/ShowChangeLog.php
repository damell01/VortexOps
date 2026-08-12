<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowChangeLog extends Model
{
    protected $fillable = ['show_id', 'field_name', 'old_value', 'new_value', 'changed_by', 'source', 'notes'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public static function logChange(Show $show, string $field, mixed $oldValue, mixed $newValue, ?string $source = 'manual'): self
    {
        return self::create([
            'show_id' => $show->id,
            'field_name' => $field,
            'old_value' => is_scalar($oldValue) ? (string) $oldValue : json_encode($oldValue),
            'new_value' => is_scalar($newValue) ? (string) $newValue : json_encode($newValue),
            'changed_by' => auth()->user()?->email ?? 'system',
            'source' => $source,
        ]);
    }
}

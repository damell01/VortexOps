<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewSession extends Model
{
    protected $fillable = ['title', 'status', 'created_by', 'submitted_at'];

    protected $casts = ['submitted_at' => 'datetime'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReviewItem::class);
    }

    public static function statusLabels(): array
    {
        return [
            'open'      => 'Open',
            'submitted' => 'Submitted',
            'closed'    => 'Closed',
        ];
    }
}

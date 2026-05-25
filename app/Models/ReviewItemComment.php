<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewItemComment extends Model
{
    protected $fillable = ['review_item_id', 'user_id', 'body'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ReviewItem::class, 'review_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

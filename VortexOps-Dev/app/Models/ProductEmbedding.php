<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's embedding vector, kept in its own table so the hot `products`
 * table stays lean. A vector is several KB of JSON; with hundreds of thousands
 * of products, carrying it on every `SELECT *` (list views, joins, global
 * search) would bloat every read. It's loaded only when semantic matching
 * actually needs it.
 */
class ProductEmbedding extends Model
{
    protected $fillable = ['product_id', 'vector'];

    protected $casts = ['vector' => 'array'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

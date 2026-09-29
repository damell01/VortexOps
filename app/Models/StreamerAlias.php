<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamerAlias extends Model
{
    protected $fillable = ['streamer_id', 'alias', 'normalized_alias', 'source'];

    protected static function booted(): void
    {
        static::saving(function (self $alias): void {
            $alias->normalized_alias = self::normalize($alias->alias);
        });
    }

    public function streamer(): BelongsTo { return $this->belongsTo(Streamer::class); }

    public static function normalize(?string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', ' ', strtolower((string) $value)));
    }
}

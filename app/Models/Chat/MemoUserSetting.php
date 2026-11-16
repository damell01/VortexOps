<?php

namespace App\Models\Chat;

use Chatify\Models\UserSetting;
use Illuminate\Database\Eloquent\Builder;

/**
 * Chatify looks up each person's settings (avatar, online visibility) with a
 * firstOrCreate every time it shows them: 150+ queries for one inbox. This
 * loads everyone's settings once and hands back the same row; saving one
 * clears the copy, and it never lives longer than a couple of seconds.
 */
class MemoUserSetting extends UserSetting
{
    /** @var array<int, static>|null */
    protected static ?array $byUser = null;

    protected static float $loadedAt = 0;

    protected static function booted(): void
    {
        static::saved(fn () => static::forget());
        static::deleted(fn () => static::forget());
    }

    public static function forget(): void
    {
        static::$byUser = null;
    }

    /** @return array<int, static> */
    public static function byUser(): array
    {
        if (static::$byUser === null || microtime(true) - static::$loadedAt > 2) {
            static::$byUser = static::query()->getQuery()->get()
                ->mapWithKeys(fn ($row) => [(int) $row->user_id => (new static)->newFromBuilder($row)])->all();
            static::$loadedAt = microtime(true);
        }

        return static::$byUser;
    }

    public function newEloquentBuilder($query): Builder
    {
        return new class($query) extends Builder
        {
            public function firstOrCreate(array $attributes = [], $values = [])
            {
                if (array_keys($attributes) === ['user_id'] && $this->getQuery()->wheres === []) {
                    $hit = MemoUserSetting::byUser()[(int) $attributes['user_id']] ?? null;
                    if ($hit) return $hit;
                }

                return parent::firstOrCreate($attributes, $values);
            }
        };
    }
}

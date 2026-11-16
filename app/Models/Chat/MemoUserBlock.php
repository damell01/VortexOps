<?php

namespace App\Models\Chat;

use Chatify\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Chatify asks "has A blocked B?" once per person per conversation when it
 * builds the inbox: two queries each, 200+ for a dozen conversations. The
 * blocks table is tiny, so this loads it once and answers from memory. Any
 * block or unblock clears the copy, and it never lives longer than a couple
 * of seconds (queue workers are long-running).
 */
class MemoUserBlock extends UserBlock
{
    /** @var array<string, true>|null */
    protected static ?array $pairs = null;

    protected static float $loadedAt = 0;

    protected static function booted(): void
    {
        static::saved(fn () => static::forget());
        static::deleted(fn () => static::forget());
    }

    public static function forget(): void
    {
        static::$pairs = null;
    }

    /** @return array<string, true> "blocker:blocked" pairs */
    public static function pairs(): array
    {
        if (static::$pairs === null || microtime(true) - static::$loadedAt > 2) {
            static::$pairs = DB::table((new static)->getTable())->get(['blocker_id', 'blocked_user_id'])
                ->mapWithKeys(fn ($row) => [$row->blocker_id.':'.$row->blocked_user_id => true])->all();
            static::$loadedAt = microtime(true);
        }

        return static::$pairs;
    }

    public function newEloquentBuilder($query): Builder
    {
        return new class($query) extends Builder
        {
            public function exists()
            {
                $pair = MemoUserBlock::pairLookup($this->getQuery()->wheres);

                return $pair === null ? parent::exists() : isset(MemoUserBlock::pairs()[$pair]);
            }

            public function delete()
            {
                try {
                    return parent::delete();
                } finally {
                    MemoUserBlock::forget();
                }
            }
        };
    }

    /** "blocker:blocked" when the query is exactly that pair lookup, else null. */
    public static function pairLookup(array $wheres): ?string
    {
        if (count($wheres) !== 2) return null;
        $by = [];
        foreach ($wheres as $where) {
            if (($where['type'] ?? null) !== 'Basic' || ($where['operator'] ?? null) !== '=' || ($where['boolean'] ?? 'and') !== 'and') return null;
            $column = str_contains($where['column'], '.') ? substr($where['column'], strrpos($where['column'], '.') + 1) : $where['column'];
            $by[$column] = $where['value'];
        }

        return isset($by['blocker_id'], $by['blocked_user_id']) ? $by['blocker_id'].':'.$by['blocked_user_id'] : null;
    }
}

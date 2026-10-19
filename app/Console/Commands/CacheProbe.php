<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Proves a cache store works before the app is pointed at it: writes a value,
 * reads it back, takes and releases a lock. Used by the deploy script to move
 * the cache from the database to Redis only once Redis answers.
 *
 * --locks-free also refuses while the database store holds an unexpired lock
 * (a Whatnot browser run, a cover download): those locks would not carry over,
 * and a second scraper could start beside the first.
 */
class CacheProbe extends Command
{
    protected $signature = 'cache:probe {store=redis : The cache store to test} {--locks-free : Also fail while a database cache lock is held}';

    protected $description = 'Check a cache store can write, read and lock (exit 0 = usable)';

    public function handle(): int
    {
        $store = (string) $this->argument('store');

        if ($this->option('locks-free') && $this->databaseLocksHeld() > 0) {
            $this->warn('A database cache lock is held (a Whatnot run or similar); not switching yet.');

            return 2;
        }

        try {
            $cache = Cache::store($store);
            $key = 'cache-probe:'.Str::random(12);
            $value = Str::random(24);
            $cache->put($key, $value, 30);
            $read = $cache->get($key);
            $cache->forget($key);

            $lock = $cache->lock($key.':lock', 10);
            $locked = $lock->get();
            if ($locked) {
                $lock->release();
            }
        } catch (\Throwable $e) {
            $this->error("{$store}: ".$e->getMessage());

            return 1;
        }

        if ($read !== $value || ! $locked) {
            $this->error("{$store}: wrote a value but ".($read !== $value ? 'read back something else' : 'could not take a lock'));

            return 1;
        }

        $this->info("{$store}: write, read and lock all work");

        return 0;
    }

    private function databaseLocksHeld(): int
    {
        $table = config('cache.stores.database.lock_table') ?: 'cache_locks';
        try {
            return Schema::hasTable($table) ? DB::table($table)->where('expiration', '>', time())->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}

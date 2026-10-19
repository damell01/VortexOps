<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** cache:probe — the deploy's gate before moving the cache to Redis. */
class CacheProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_working_store_passes(): void
    {
        $this->artisan('cache:probe', ['store' => 'array'])->assertExitCode(0);
    }

    public function test_a_store_that_cannot_connect_fails_instead_of_throwing(): void
    {
        config(['cache.stores.broken' => ['driver' => 'redis', 'connection' => 'nowhere']]);

        $this->artisan('cache:probe', ['store' => 'broken'])->assertExitCode(1);
    }

    public function test_it_waits_while_a_database_lock_is_held(): void
    {
        $lock = Cache::store('database')->lock('whatnot-browser', 60);
        $this->assertTrue($lock->get());

        $this->artisan('cache:probe', ['store' => 'array', '--locks-free' => true])->assertExitCode(2);

        $lock->release();
        $this->artisan('cache:probe', ['store' => 'array', '--locks-free' => true])->assertExitCode(0);
    }
}

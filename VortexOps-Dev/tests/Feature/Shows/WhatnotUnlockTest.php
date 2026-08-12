<?php

namespace Tests\Feature\Shows;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatnotUnlockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::lock('whatnot:browser')->forceRelease();
        Cache::forget('whatnot:browser:holder_pid');
        parent::tearDown();
    }

    public function test_releases_a_lock_with_no_recorded_holder(): void
    {
        Cache::lock('whatnot:browser', 13800)->get();

        $this->artisan('whatnot:unlock')
            ->assertExitCode(0);

        $this->assertTrue(Cache::lock('whatnot:browser')->get());
    }

    public function test_releases_a_lock_whose_holder_pid_is_dead(): void
    {
        Cache::lock('whatnot:browser', 13800)->get();
        // PID 999999 is well beyond any realistic live PID on the test host.
        Cache::put('whatnot:browser:holder_pid', 999999, 13800);

        $this->artisan('whatnot:unlock')
            ->assertExitCode(0);

        $this->assertTrue(Cache::lock('whatnot:browser')->get());
    }

    public function test_refuses_to_release_when_the_holder_pid_is_alive(): void
    {
        Cache::lock('whatnot:browser', 13800)->get();
        Cache::put('whatnot:browser:holder_pid', getmypid(), 13800);

        $this->artisan('whatnot:unlock')
            ->assertExitCode(1);

        $this->assertFalse(Cache::lock('whatnot:browser')->get(), 'lock should still be held');
    }

    public function test_force_releases_even_when_the_holder_pid_is_alive(): void
    {
        Cache::lock('whatnot:browser', 13800)->get();
        Cache::put('whatnot:browser:holder_pid', getmypid(), 13800);

        $this->artisan('whatnot:unlock', ['--force' => true])
            ->assertExitCode(0);

        $this->assertTrue(Cache::lock('whatnot:browser')->get());
    }
}

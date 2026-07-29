<?php

namespace Tests;

use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ChannelContext memoizes per-request (a real request only ever runs
        // once, so this is invisible in production) — but PHPUnit runs many
        // test methods in the same PHP process, so a leftover memo from a
        // previous test would otherwise leak into this one's session state.
        ChannelContext::flushMemo();
    }
}

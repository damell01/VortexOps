<?php

namespace Tests;

use App\Support\ChannelContext;
use App\Support\NavVisibility;
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

        // Same hazard, and a nastier one: role visibility is memoised too, and
        // RefreshDatabase rolls back the settings row without touching the
        // static holding its value. A test that configured a role left the next
        // one reading an allow-list whose backing row no longer exists, which
        // surfaced as unrelated pages 403ing several files later.
        NavVisibility::flushMemo();
    }
}

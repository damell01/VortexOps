<?php

namespace Tests;

use App\Support\AdminModules;
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

        // And the third of the same kind. Which modules are switched on is
        // memoised per request as well, so a test that turned modules off left
        // every module-gated page closed for whatever ran next — which reads as
        // dozens of unrelated pages suddenly answering 403, in files that never
        // mention modules. Exactly the shape of the visibility leak above, and
        // just as misleading to chase.
        AdminModules::flushMemo();

        // The same hazard one layer down, and the one the memo flushes above
        // cannot reach: Setting::get() caches every key for an hour, the test
        // store is `array` and so lives as long as the PHP process, and
        // RefreshDatabase rolls back the rows behind it without touching it. A
        // test that changed a setting left the next reading a cached value
        // whose row no longer exists — modules appearing switched off in files
        // that never mention modules, and pages 403ing for the owner, who the
        // visibility gate exempts entirely.
        \Illuminate\Support\Facades\Cache::flush();
    }

    /**
     * Switch modules on for a test that exercises their pages.
     *
     * A migration seeds enabled_admin_modules with a shell-phase subset that
     * leaves purchasing, reporting and shipments off, so those pages answer 403
     * for everyone — the owner included — the moment the module gate is read
     * honestly. Tests covering them used to pass anyway: the enabled list is
     * memoised, the panel reads it while booting (before migrations have run
     * and the row exists), and the defaults it fell back to then stood for the
     * whole process. Say which modules the test needs instead of inheriting
     * whatever that race produced.
     *
     * @param array<int, string>|null $slugs  null enables every defined module
     */
    protected function enableAdminModules(?array $slugs = null): void
    {
        \App\Models\Setting::set(
            'enabled_admin_modules',
            json_encode($slugs ?? array_keys(\App\Support\AdminModules::definitions())),
        );

        \App\Support\AdminModules::flushMemo();
    }
}

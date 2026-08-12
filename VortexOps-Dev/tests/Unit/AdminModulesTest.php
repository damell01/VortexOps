<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('setting:enabled_admin_modules');
        AdminModules::flushMemo();
    }

    protected function tearDown(): void
    {
        // flushMemo() alone only clears the static in-process memo —
        // Setting::get()'s own cache()->remember() layer isn't test-scoped,
        // so a value this class wrote could otherwise leak into whatever
        // test runs next in this PHPUnit process.
        Cache::forget('setting:enabled_admin_modules');
        AdminModules::flushMemo();
        parent::tearDown();
    }

    public function test_definitions_returns_all_expected_modules(): void
    {
        $defs = AdminModules::definitions();

        $expected = ['streams', 'payouts', 'inventory', 'purchasing', 'shipments', 'operations', 'reporting', 'ai', 'timekeeping', 'fulfillment'];

        foreach ($expected as $slug) {
            $this->assertArrayHasKey($slug, $defs, "Module '{$slug}' missing from definitions");
        }

        $this->assertCount(count($expected), $defs);
    }

    public function test_each_definition_has_required_keys(): void
    {
        foreach (AdminModules::definitions() as $slug => $def) {
            $this->assertArrayHasKey('label', $def,       "Module '{$slug}' missing label");
            $this->assertArrayHasKey('description', $def, "Module '{$slug}' missing description");
            $this->assertArrayHasKey('group', $def,       "Module '{$slug}' missing group");
            $this->assertArrayHasKey('order', $def,       "Module '{$slug}' missing order");
        }
    }

    public function test_default_enabled_slugs_excludes_advanced_modules(): void
    {
        $defaults = AdminModules::defaultEnabledSlugs();

        $this->assertNotContains('ai', $defaults, "Advanced module 'ai' should not be a default");
        $this->assertContains('purchasing', $defaults, "Core module 'purchasing' must be a default");
    }

    public function test_normalize_filters_out_removed_modules(): void
    {
        $result = AdminModules::normalizeEnabledSlugs(['streams', 'projects', 'reviews']);

        $this->assertContains('streams', $result);
        $this->assertNotContains('projects', $result, 'projects module has been removed');
        $this->assertNotContains('reviews', $result, 'reviews module has been removed');
    }

    public function test_normalize_filters_out_unknown_slugs(): void
    {
        $result = AdminModules::normalizeEnabledSlugs(['streams', 'not_a_real_module', 'inventory']);

        $this->assertContains('streams', $result);
        $this->assertContains('inventory', $result);
        $this->assertNotContains('not_a_real_module', $result);
    }

    public function test_normalize_deduplicates(): void
    {
        $result = AdminModules::normalizeEnabledSlugs(['streams', 'streams', 'inventory']);

        $this->assertEquals(count($result), count(array_unique($result)));
    }

    public function test_is_enabled_returns_true_for_stored_module(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams', 'inventory']));
        AdminModules::flushMemo();

        $this->assertTrue(AdminModules::isEnabled('streams'));
        $this->assertTrue(AdminModules::isEnabled('inventory'));
        $this->assertFalse(AdminModules::isEnabled('payouts'));
    }

    public function test_flush_memo_causes_re_read_from_settings(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams']));
        AdminModules::flushMemo();
        $this->assertTrue(AdminModules::isEnabled('streams'));
        $this->assertFalse(AdminModules::isEnabled('payouts'));

        // Change settings, flush, re-check
        Setting::set('enabled_admin_modules', json_encode(['payouts']));
        AdminModules::flushMemo();
        $this->assertFalse(AdminModules::isEnabled('streams'));
        $this->assertTrue(AdminModules::isEnabled('payouts'));
    }

    public function test_visible_navigation_groups_always_includes_settings(): void
    {
        $groups = AdminModules::visibleNavigationGroups();

        $this->assertContains('Settings', $groups);
    }

    public function test_navigation_group_for_returns_null_with_single_operational_group(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams']));
        AdminModules::flushMemo();

        // Only 'Streams' group visible operationally → groups collapse to null
        $this->assertNull(AdminModules::navigationGroupFor('streams'));
    }

    public function test_navigation_group_for_returns_group_name_with_multiple_groups(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams', 'payouts', 'inventory']));
        AdminModules::flushMemo();

        $this->assertEquals('Streams', AdminModules::navigationGroupFor('streams'));
        $this->assertEquals('Payouts & Pay Runs', AdminModules::navigationGroupFor('payouts'));
    }

    public function test_presets_only_reference_real_module_slugs(): void
    {
        $valid = array_keys(AdminModules::definitions());

        foreach (AdminModules::presets() as $key => $preset) {
            foreach ($preset['slugs'] as $slug) {
                $this->assertContains($slug, $valid, "Preset '{$key}' references unknown module '{$slug}'");
            }
        }
    }

    public function test_basics_preset_is_shows_payouts_and_inventory_only(): void
    {
        $this->assertEqualsCanonicalizing(
            ['streams', 'payouts', 'inventory'],
            AdminModules::presets()['basics']['slugs']
        );
    }

    public function test_everything_preset_covers_every_definition(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(AdminModules::definitions()),
            AdminModules::presets()['everything']['slugs']
        );
    }
}

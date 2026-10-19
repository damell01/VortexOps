<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A page read the same handful of settings dozens of times, each a cache query
 * (the cache is the database in production), and an unset key was never cached
 * at all. A web request now reads every small setting in one query.
 */
class SettingMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::$memoize = null;
        Setting::flushMemo();
        parent::tearDown();
    }

    public function test_a_web_request_reads_its_settings_in_one_query(): void
    {
        Setting::set('brand_name', 'Vortex');
        Setting::set('primary_color', '#7c3aed');
        Setting::set('whatnot_ui_log', str_repeat('line of scraper output' . "\n", 400));
        Setting::$memoize = true;
        Setting::flushMemo();

        DB::enableQueryLog();
        foreach (range(1, 20) as $_) {
            $this->assertSame('Vortex', Setting::get('brand_name'));
            $this->assertSame('#7c3aed', Setting::get('primary_color'));
            $this->assertSame('fallback', Setting::get('logo_path', 'fallback'));
            $this->assertNull(Setting::get('logo_path'));
        }
        $this->assertCount(1, DB::getQueryLog(), 'one preload, nothing per read');

        // A large value is read on its own, only when asked for.
        $this->assertStringStartsWith('line of scraper output', Setting::get('whatnot_ui_log'));

        // A write in the same request is seen straight away.
        Setting::set('logo_path', 'logos/v.png');
        $this->assertSame('logos/v.png', Setting::get('logo_path'));
    }

    public function test_an_unset_key_is_cached_instead_of_requeried(): void
    {
        DB::enableQueryLog();
        Setting::get('never_set');
        $first = count(DB::getQueryLog());
        Setting::get('never_set');
        Setting::get('never_set', 'x');

        $this->assertSame('x', Setting::get('never_set', 'x'));
        $this->assertLessThanOrEqual($first, count(DB::getQueryLog()) - $first, 'later reads hit the cached "unset", not the settings table');
        $this->assertSame(0, collect(DB::getQueryLog())->slice($first)->filter(fn ($q) => str_contains($q['query'], 'settings'))->count());
    }
}

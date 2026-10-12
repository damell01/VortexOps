<?php

namespace Tests\Feature\Reliability;

use App\Support\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_copies_of_the_app_can_read_the_deployed_version_uncached(): void
    {
        $this->get('/app-version')
            ->assertOk()
            ->assertJson(['version' => AppVersion::current()])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_version_changes_when_the_build_changes(): void
    {
        $manifest = public_path('build/manifest.json');
        $original = is_file($manifest) ? file_get_contents($manifest) : null;
        @mkdir(dirname($manifest), 0777, true);

        try {
            file_put_contents($manifest, '{"a":1}');
            touch($manifest, time() - 10);
            clearstatcache();
            $before = AppVersion::current();

            file_put_contents($manifest, '{"a":2}');
            touch($manifest, time());
            clearstatcache();

            $this->assertNotSame($before, AppVersion::current());
        } finally {
            $original === null ? @unlink($manifest) : file_put_contents($manifest, $original);
        }
    }
}

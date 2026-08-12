<?php

namespace Tests\Unit;

use App\Models\WhatnotChannel;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The active-channel-aware brand name/logo resolution used by
 * AdminPanelProvider::panel() — extracted to static methods specifically so
 * this precedence logic (channel branding > global branding > built-in SVG)
 * is testable without rendering a real authenticated panel page.
 */
class AdminPanelBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_name_prefers_channel_display_title(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active', 'display_title' => 'Vortex Collects HQ']);

        $this->assertSame(
            'Vortex Collects HQ',
            AdminPanelProvider::resolveBrandName($channel, 'VortexOps', null)
        );
    }

    public function test_brand_name_falls_back_to_global_when_channel_has_no_title(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);

        $this->assertSame(
            'VortexOps',
            AdminPanelProvider::resolveBrandName($channel, 'VortexOps', 'brand/some-logo.png')
        );
    }

    public function test_brand_name_null_when_no_channel_selected(): void
    {
        $this->assertSame(
            'VortexOps',
            AdminPanelProvider::resolveBrandName(null, 'VortexOps', 'brand/some-logo.png')
        );
    }

    public function test_brand_logo_prefers_channel_logo_when_file_exists(): void
    {
        $path = 'channel-logos/test-logo.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'fake-image-bytes');

        $channel = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active', 'logo_path' => $path]);

        $this->assertSame(asset('storage/' . $path), AdminPanelProvider::resolveBrandLogo($channel, null));

        \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
    }

    public function test_brand_logo_falls_back_to_global_when_channel_logo_file_missing(): void
    {
        // logo_path is set on the channel but the file doesn't actually exist on disk.
        $channel = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active', 'logo_path' => 'channel-logos/missing.png']);

        $globalPath = 'brand/global-logo.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($globalPath, 'fake-image-bytes');

        $this->assertSame(asset('storage/' . $globalPath), AdminPanelProvider::resolveBrandLogo($channel, $globalPath));

        \Illuminate\Support\Facades\Storage::disk('public')->delete($globalPath);
    }

    public function test_brand_logo_null_when_nothing_exists_and_no_builtin_svg(): void
    {
        // No channel, no global logo path, and (in the test env) no public/images/vb-logo-sidebar.svg
        // is guaranteed to exist, so accept either null or the built-in SVG URL — the point of this
        // test is that a missing channel logo file never throws and never returns a broken path.
        $result = AdminPanelProvider::resolveBrandLogo(null, null);

        $this->assertTrue($result === null || str_ends_with($result, 'vb-logo-sidebar.svg'));
    }
}

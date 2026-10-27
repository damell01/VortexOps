<?php

namespace Tests\Feature\Branding;

use App\Models\Setting;
use App\Support\BrandIcons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** The tab, home-screen and notification icons come from the uploaded logo. */
class BrandIconsTest extends TestCase
{
    use RefreshDatabase;

    private string $logo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logo = 'brand/test-logo-' . uniqid() . '.png';
        File::ensureDirectoryExists(storage_path('app/public/brand'));
        $image = imagecreatetruecolor(300, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 124, 58, 237));
        imagepng($image, storage_path('app/public/' . $this->logo));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/public/' . $this->logo));
        File::deleteDirectory(storage_path('app/brand-icons'));
        parent::tearDown();
    }

    public function test_without_an_uploaded_logo_the_bundled_icons_are_used(): void
    {
        $this->assertSame('/icons/icon-32.png', BrandIcons::url('32'));
        $this->assertSame('/icons/icon-maskable.png', BrandIcons::url('maskable'));
        $this->get('/brand/icon-192.png')->assertOk();
    }

    public function test_icons_are_drawn_from_the_uploaded_logo(): void
    {
        Setting::set('logo_path', $this->logo);

        foreach (['32' => 32, '180' => 180, '192' => 192, '512' => 512, 'maskable' => 512] as $size => $px) {
            $url = BrandIcons::url((string) $size);
            $this->assertStringContainsString("/brand/icon-{$size}.png?v=", $url);
            $file = BrandIcons::render((string) $size);
            [$w, $h] = getimagesize($file);
            $this->assertSame([$px, $px], [$w, $h]);
            // The logo's purple sits in the middle.
            $rgb = imagecolorat(imagecreatefrompng($file), intdiv($px, 2), intdiv($px, 2));
            $this->assertSame([124, 58, 237], [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255]);
        }

        $this->get('/brand/icon-32.png')->assertOk()->assertHeader('Content-Type', 'image/png');

        $manifest = $this->get('/brand/manifest.json')->assertOk()->json();
        $this->assertStringContainsString('/brand/icon-512.png?v=', collect($manifest['icons'])->firstWhere('sizes', '512x512')['src']);
        $this->assertStringContainsString('/brand/icon-192.png?v=', $manifest['shortcuts'][0]['icons'][0]['src']);
    }

    public function test_an_svg_logo_falls_back_to_the_bundled_icons(): void
    {
        Setting::set('logo_path', 'brand/logo.svg');

        $this->assertSame('/icons/icon-180.png', BrandIcons::url('180'));
    }
}

<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Browser and app icons drawn from the logo uploaded in Settings, so the tab,
 * the home-screen app and notifications show the real Vortex mark. The logo
 * sits on the header's navy, so a white-and-purple mark stays legible on a
 * light tab bar too. Without a usable upload (none, missing, or an SVG GD
 * cannot read) the bundled icons in public/icons are used.
 */
class BrandIcons
{
    /** size => [canvas px, logo share of the canvas, rounded corners] */
    public const SIZES = [
        '32' => [32, 0.86, true],
        '180' => [180, 0.74, false],
        '192' => [192, 0.74, true],
        '512' => [512, 0.74, true],
        'maskable' => [512, 0.58, false],
    ];

    private const BACKGROUND = [15, 23, 42]; // #0f172a, the header navy

    /** Public URL for one icon, versioned by the logo so a new upload shows at once. */
    public static function url(string $size): string
    {
        $logo = static::logoFile();

        if ($logo === null || ! isset(static::SIZES[$size])) {
            return '/icons/' . ($size === 'maskable' ? 'icon-maskable.png' : "icon-{$size}.png");
        }

        return route('brand.icon', ['size' => $size, 'v' => static::version($logo)]);
    }

    /** Absolute path of a readable raster logo, or null. */
    public static function logoFile(): ?string
    {
        $path = (string) Setting::get('logo_path', '');
        if ($path === '') {
            return null;
        }

        $file = storage_path('app/public/' . ltrim($path, '/'));
        if (! is_file($file) || ! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return null;
        }

        return $file;
    }

    /** The PNG for one size, rendered once per logo version and kept on disk. */
    public static function render(string $size): ?string
    {
        $logo = static::logoFile();
        if ($logo === null || ! isset(static::SIZES[$size]) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $cache = storage_path('app/brand-icons/' . static::version($logo) . "-{$size}.png");
        if (is_file($cache)) {
            return $cache;
        }

        $source = @imagecreatefromstring((string) file_get_contents($logo));
        if ($source === false) {
            return null;
        }

        [$px, $share, $rounded] = static::SIZES[$size];
        $canvas = imagecreatetruecolor($px, $px);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        $navy = imagecolorallocate($canvas, ...static::BACKGROUND);
        if ($rounded) {
            static::roundedSquare($canvas, $px, (int) round($px * 0.22), $navy);
        } else {
            imagefilledrectangle($canvas, 0, 0, $px - 1, $px - 1, $navy);
        }

        // Fit the logo inside the box, keeping its proportions, centred.
        $w = imagesx($source);
        $h = imagesy($source);
        $box = $px * $share;
        $scale = min($box / $w, $box / $h);
        $dw = max(1, (int) round($w * $scale));
        $dh = max(1, (int) round($h * $scale));
        imagecopyresampled($canvas, $source, (int) (($px - $dw) / 2), (int) (($px - $dh) / 2), 0, 0, $dw, $dh, $w, $h);

        if (! is_dir(dirname($cache))) {
            @mkdir(dirname($cache), 0775, true);
        }
        $ok = imagepng($canvas, $cache);
        imagedestroy($canvas);
        imagedestroy($source);

        return $ok ? $cache : null;
    }

    private static function version(string $logo): string
    {
        return substr(sha1($logo . '|' . filemtime($logo) . '|' . filesize($logo)), 0, 12);
    }

    private static function roundedSquare(\GdImage $image, int $px, int $radius, int $color): void
    {
        imagefilledrectangle($image, $radius, 0, $px - 1 - $radius, $px - 1, $color);
        imagefilledrectangle($image, 0, $radius, $px - 1, $px - 1 - $radius, $color);
        foreach ([[$radius, $radius], [$px - 1 - $radius, $radius], [$radius, $px - 1 - $radius], [$px - 1 - $radius, $px - 1 - $radius]] as [$x, $y]) {
            imagefilledellipse($image, $x, $y, $radius * 2, $radius * 2, $color);
        }
    }
}

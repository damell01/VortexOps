<?php

namespace App\Http\Controllers;

use App\Support\BrandIcons;

class BrandIconController extends Controller
{
    public function icon(string $size)
    {
        $file = BrandIcons::render($size);

        if ($file === null) {
            abort_unless(isset(BrandIcons::SIZES[$size]), 404);
            $file = public_path('icons/' . ($size === 'maskable' ? 'icon-maskable.png' : "icon-{$size}.png"));
        }

        return response()->file($file, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /** The web app manifest, with icons pointing at the uploaded logo. */
    public function manifest()
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.json')), true) ?: [];

        $map = [
            '/icons/icon-32.png' => '32', '/icons/icon-180.png' => '180', '/icons/icon-192.png' => '192',
            '/icons/icon-512.png' => '512', '/icons/icon-maskable.png' => 'maskable',
        ];
        $swap = fn (array $icon) => isset($map[$icon['src'] ?? '']) ? ['src' => BrandIcons::url($map[$icon['src']])] + $icon : $icon;

        $manifest['icons'] = array_map($swap, $manifest['icons'] ?? []);
        foreach ($manifest['shortcuts'] ?? [] as $i => $shortcut) {
            $manifest['shortcuts'][$i]['icons'] = array_map($swap, $shortcut['icons'] ?? []);
        }

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES);
    }
}
